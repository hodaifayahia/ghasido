<?php

namespace App\Services\Subscriptions;

use App\Enums\Role;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Monthly employee AI point allocations and balances (AIL-01, AIL-04). */
final class EmployeeAiPointsService
{
    public function __construct(private readonly HotelAiPointTopUpService $topUps) {}

    /** @return array<string, mixed> */
    public function forHotel(Hotel $hotel): array
    {
        $plan = $hotel->subscriptionPlan;
        abort_if($plan === null, 409, __('This hotel does not have a subscription plan.'));
        $monthlyPointPool = $this->topUps->poolFor($hotel, $plan);

        $employees = User::query()
            ->where('hotel_id', $hotel->id)
            ->whereHas('roles', fn ($query) => $query->where('name', Role::Employee->value))
            ->with('department')
            ->orderBy('name')
            ->get();
        $ids = $employees->modelKeys();
        $monthStart = Date::now()->startOfMonth();

        $used = AiUsage::query()
            ->whereIn('user_id', $ids)
            ->where('occurred_at', '>=', $monthStart)
            ->selectRaw('user_id, SUM(points_charged) as points')
            ->groupBy('user_id')
            ->pluck('points', 'user_id')
            ->map(fn ($points): int => (int) $points)
            ->all();

        $voiceUsed = RoleplayAttempt::query()
            ->whereIn('user_id', $ids)
            ->where('started_at', '>=', $monthStart)
            ->selectRaw('user_id, SUM(ai_points_charged) as points')
            ->groupBy('user_id')
            ->pluck('points', 'user_id')
            ->map(fn ($points): int => (int) $points)
            ->all();

        $rows = $employees->map(function (User $employee) use ($used, $voiceUsed): array {
            $consumed = ($used[$employee->id] ?? 0) + ($voiceUsed[$employee->id] ?? 0);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'username' => $employee->username,
                'department' => $employee->department->name ?? __('Unassigned'),
                'status' => $employee->status->value,
                'allocated' => $employee->ai_points_allocated,
                'used' => $consumed,
                'remaining' => $employee->ai_points_allocated - $consumed,
            ];
        })->values()->all();

        $allocated = $employees->sum('ai_points_allocated');

        return [
            'hotel' => ['id' => $hotel->id, 'name' => $hotel->name],
            'plan' => [
                'name' => $plan->name,
                'employeeLimit' => $plan->employee_limit,
                'monthlyPointPool' => $monthlyPointPool,
                'baseMonthlyPointPool' => $plan->pointsPool(),
                'paidTopUpPoints' => $monthlyPointPool - $plan->pointsPool(),
                'pointsPerEmployee' => $plan->points_per_employee,
                'bonusPointsPerEmployee' => $plan->bonus_points_per_employee,
                'voicePointsPer10Minutes' => $plan->voice_points_per_10_minutes,
                'aiActionPoints' => $plan->ai_action_points,
            ],
            'summary' => [
                'employees' => $employees->count(),
                'allocated' => $allocated,
                'available' => $monthlyPointPool - $allocated,
                'used' => array_sum($used) + array_sum($voiceUsed),
                'remaining' => max(0, $monthlyPointPool - array_sum($used) - array_sum($voiceUsed)),
            ],
            'topUpRequestPending' => $hotel->aiPointTopUpRequests()
                ->where('status', 'pending')
                ->exists(),
            'employees' => $rows,
        ];
    }

    public function updateAllocation(Hotel $hotel, User $employee, int $points, User $actor): void
    {
        DB::transaction(function () use ($hotel, $employee, $points, $actor): void {
            /** @var Hotel $lockedHotel */
            $lockedHotel = Hotel::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($hotel->id);

            /** @var User $lockedEmployee */
            $lockedEmployee = User::query()->lockForUpdate()->findOrFail($employee->id);

            if ($lockedEmployee->hotel_id !== $lockedHotel->id || ! $lockedEmployee->hasRole(Role::Employee->value)) {
                abort(403);
            }

            $plan = $lockedHotel->subscriptionPlan;

            if ($plan === null) {
                throw ValidationException::withMessages(['ai_points_allocated' => __('This hotel does not have an active subscription plan.')]);
            }

            $monthlyPointPool = $this->topUps->poolFor($lockedHotel, $plan);

            $monthStart = Date::now()->startOfMonth();
            $spentActions = (int) AiUsage::query()
                ->where('user_id', $lockedEmployee->id)
                ->where('occurred_at', '>=', $monthStart)
                ->sum('points_charged');
            $spentVoice = (int) RoleplayAttempt::query()
                ->where('user_id', $lockedEmployee->id)
                ->where('started_at', '>=', $monthStart)
                ->sum('ai_points_charged');

            if ($points < ($spentActions + $spentVoice)) {
                throw ValidationException::withMessages([
                    'ai_points_allocated' => __('The allocation cannot be lower than points already used this month (:used).', ['used' => $spentActions + $spentVoice]),
                ]);
            }

            $currentlyAllocated = (int) $lockedHotel->users()
                ->whereHas('roles', fn ($query) => $query->where('name', Role::Employee->value))
                ->sum('ai_points_allocated');
            $newTotal = $currentlyAllocated - $lockedEmployee->ai_points_allocated + $points;

            if ($newTotal > $monthlyPointPool && $newTotal > $currentlyAllocated) {
                throw ValidationException::withMessages([
                    'ai_points_allocated' => __('This allocation exceeds the hotel plan point pool (:available points remain).', [
                        'available' => max(0, $monthlyPointPool - $currentlyAllocated + $lockedEmployee->ai_points_allocated),
                    ]),
                ]);
            }

            if ($lockedEmployee->ai_points_allocated === $points) {
                return;
            }

            $before = $lockedEmployee->ai_points_allocated;
            $lockedEmployee->ai_points_allocated = $points;
            $lockedEmployee->save();

            AuditLog::record($lockedEmployee, 'employee.ai_points_updated', [
                'hotel_id' => $lockedHotel->id,
                'from' => $before,
                'to' => $points,
                'changed_by' => $actor->id,
            ]);
        });
    }
}
