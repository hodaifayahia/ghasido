<?php

namespace App\Services\Hotels;

use App\Enums\AccountStatus;
use App\Enums\HotelAccessState;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TrainingStatus;
use App\Http\Resources\Hotels\HotelOverviewResource;
use App\Models\Attempt;
use App\Models\Hotel;
use App\Models\PaymentSubmission;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Employees\EmployeeDirectory;
use App\Services\Reports\ReportPopulation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The read side of one hotel's detail page (ORG-01, ORG-03, DATA-06,
 * DATA-07, DATA-08; spec 0002 and spec 0003).
 *
 * Activity and time are derived from the same rows used by the Employees and
 * Reports screens. "Recorded time" deliberately means answer time plus
 * completed role-play duration; browser dwell time is not persisted by the
 * current domain model and must not be presented as if it were.
 */
final class HotelDetails
{
    private const ACTIVE_DAYS = 7;

    private const INACTIVE_DAYS = 30;

    public function __construct(private readonly EmployeeDirectory $directory) {}

    /**
     * @return array{
     *     hotel: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     activity: array<string, int>,
     *     employees: list<array<string, mixed>>
     * }
     */
    public function build(Request $request, Hotel $hotel, User $viewer): array
    {
        $employees = $this->employees($hotel, $viewer);
        /** @var list<int> $employeeIds */
        $employeeIds = array_values(array_map(
            static fn (int|string $id): int => (int) $id,
            $employees->modelKeys(),
        ));
        $timeByEmployee = $this->recordedTimeByEmployee($employeeIds);

        $employeeRows = [];
        $activity = [
            'activeThisWeek' => 0,
            'activeThisMonth' => 0,
            'inactive' => 0,
        ];
        $started = 0;
        $completed = 0;
        $totalTimeMs = 0;

        foreach ($employees as $employee) {
            $trainingStatus = EmployeeDirectory::statusOf($employee);
            $activityStatus = $this->activityStatus($employee);
            $recordedTimeMs = $timeByEmployee[$employee->id] ?? 0;

            $activity[$activityStatus]++;
            $started += in_array($trainingStatus, [TrainingStatus::InProgress, TrainingStatus::Completed], true) ? 1 : 0;
            $completed += $trainingStatus === TrainingStatus::Completed ? 1 : 0;
            $totalTimeMs += $recordedTimeMs;

            $employeeRows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'username' => (string) $employee->username,
                'department' => $employee->department->name ?? __('Unassigned'),
                'accountStatus' => $employee->status->value,
                'trainingStatus' => $trainingStatus->value,
                'trainingStatusLabel' => $trainingStatus->label(),
                'activityStatus' => $activityStatus,
                'activityStatusLabel' => $this->activityLabel($activityStatus),
                'progress' => EmployeeDirectory::progressOf($employee),
                'lessonsCompleted' => (int) $employee->getAttribute('lessons_completed_count'),
                'lessonsTotal' => (int) $employee->getAttribute('lessons_total'),
                'lastActivity' => $this->formatDateTime($employee->last_activity_at),
                'timeSpentMinutes' => (int) round($recordedTimeMs / 60000),
                'timeSpent' => $this->formatDuration($recordedTimeMs),
            ];
        }

        $employeeCount = count($employeeRows);
        $employeesWithRecordedTime = count(array_filter(
            $timeByEmployee,
            static fn (int $milliseconds): bool => $milliseconds > 0,
        ));

        return [
            'hotel' => (new HotelOverviewResource($hotel))->resolve($request),
            'summary' => [
                'totalEmployees' => $employeeCount,
                'activeAccounts' => $employees->filter(fn (User $employee): bool => $employee->status === AccountStatus::Active)->count(),
                'activeUsers' => $activity['activeThisWeek'],
                'startedTraining' => $started,
                'completedTraining' => $completed,
                'inactiveUsers' => $activity['inactive'],
                'totalTimeSpentMinutes' => (int) round($totalTimeMs / 60000),
                'totalTimeSpent' => $this->formatDuration($totalTimeMs),
                'averageTimeSpent' => $this->formatDuration(
                    $employeesWithRecordedTime === 0 ? 0 : (int) round($totalTimeMs / $employeesWithRecordedTime),
                ),
            ],
            'activity' => $activity,
            'employees' => $employeeRows,
            // A hotel that bought a plan online waits here for approval
            // (client request 2026-09-27): who asked, what they paid and
            // the receipt, next to the Approve and Reject buttons.
            'approval' => $this->approval($hotel, $viewer),
        ];
    }

    /**
     * @return array{pending: bool, canApprove: bool, requester: array{name: string, email: string|null, phone: string|null}|null, payments: list<array<string, mixed>>}
     */
    private function approval(Hotel $hotel, User $viewer): array
    {
        $pending = $hotel->access_state === HotelAccessState::Pending;
        $canSeePayments = $viewer->can(Permission::SubscriptionsManage->value);

        $requester = $pending
            ? User::query()
                ->where('hotel_id', $hotel->id)
                ->whereNull('created_by')
                ->whereHas('roles', fn ($roles) => $roles->where('name', Role::Manager->value))
                ->orderBy('id')
                ->first()
            : null;

        return [
            'pending' => $pending,
            'canApprove' => $pending && $viewer->can(Permission::HotelsApprove->value),
            'requester' => $requester === null ? null : [
                'name' => $requester->name,
                'email' => $requester->email,
                'phone' => $requester->phone,
            ],
            'payments' => $canSeePayments
                ? array_values($hotel->paymentSubmissions()->orderByDesc('id')->get()->map(fn (PaymentSubmission $payment): array => [
                    'id' => $payment->id,
                    'planName' => $payment->plan_name,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'method' => $payment->payment_method_name,
                    'reference' => $payment->reference,
                    'receiptUrl' => $payment->proof_path !== null ? route('payments.receipt', $payment) : null,
                    'isImage' => $payment->isImageProof(),
                    'status' => $payment->status->value,
                    'statusLabel' => $payment->status->label(),
                    'submittedAt' => $payment->created_at?->toIso8601String(),
                    'reviewUrl' => route('payments', ['payment' => $payment->id, 'status' => 'all']),
                ])->all())
                : [],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function employees(Hotel $hotel, User $viewer): Collection
    {
        return $this->directory->query($viewer, [
            'search' => '',
            'hotel' => $hotel->id,
            'department' => null,
            'status' => null,
        ])
            ->with([
                'department:id,name',
            ])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<int, int>
     */
    private function recordedTimeByEmployee(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $time = [];

        Attempt::query()
            ->whereIn('user_id', $employeeIds)
            ->whereNotNull('time_taken_ms')
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(time_taken_ms) as time_taken_ms')
            ->pluck('time_taken_ms', 'user_id')
            ->each(function (mixed $milliseconds, mixed $userId) use (&$time): void {
                $time[(int) $userId] = (int) $milliseconds;
            });

        RoleplayAttempt::query()
            ->whereIn('user_id', $employeeIds)
            ->where('is_preview', false)
            ->whereNotNull('duration_ms')
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(duration_ms) as duration_ms')
            ->pluck('duration_ms', 'user_id')
            ->each(function (mixed $milliseconds, mixed $userId) use (&$time): void {
                $time[(int) $userId] = ($time[(int) $userId] ?? 0) + (int) $milliseconds;
            });

        return $time;
    }

    private function activityStatus(User $employee): string
    {
        $days = ReportPopulation::daysSinceActivity($employee);

        if (
            $employee->status !== AccountStatus::Active
            || $days === null
            || $days > self::INACTIVE_DAYS
        ) {
            return 'inactive';
        }

        return $days <= self::ACTIVE_DAYS ? 'activeThisWeek' : 'activeThisMonth';
    }

    private function activityLabel(string $status): string
    {
        return match ($status) {
            'activeThisWeek' => __('Active this week'),
            'activeThisMonth' => __('Active this month'),
            default => __('Inactive'),
        };
    }

    private function formatDateTime(?CarbonInterface $date): string
    {
        return $date === null ? __('Never') : $date->format('d M Y, H:i');
    }

    private function formatDuration(int $milliseconds): string
    {
        $minutes = max(0, (int) round($milliseconds / 60000));
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return __(':minutes min', ['minutes' => $remainingMinutes]);
        }

        if ($remainingMinutes === 0) {
            return __(':hours h', ['hours' => $hours]);
        }

        return __(':hours h :minutes min', [
            'hours' => $hours,
            'minutes' => $remainingMinutes,
        ]);
    }
}
