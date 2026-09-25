<?php

namespace App\Http\Resources\Employees;

use App\Models\User;
use App\Services\Employees\EmployeeDirectory;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the employees directory, in the shape
 * resources/js/types/employees.ts calls EmployeeRecord (spec 0003 Part D).
 *
 * Shapes only. The row was selected by EmployeeDirectory::query() with its
 * derived figures alongside, so nothing here queries. The View dialog reads
 * the same row (scores, consent, participant code), which is why those ride
 * along on every row rather than behind a second request.
 *
 * @property User $resource
 */
class EmployeeRowResource extends JsonResource
{
    public const DATE_FORMAT = 'd M Y';

    public function __construct(User $user, private readonly int $rank)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $status = EmployeeDirectory::statusOf($user);

        return [
            'id' => $user->id,
            'rank' => $this->rank,
            'name' => $user->name,
            'username' => (string) $user->username,
            'hotel' => $user->hotel->name ?? '—',
            'department' => $user->department->name ?? '—',
            'email' => $user->email ?? '—',
            'progress' => EmployeeDirectory::progressOf($user),
            'status' => $status->value,
            'lastLogin' => self::formatDate($user->last_login_at),
            // What the dialogs need beyond the table (spec 0003 Part D).
            'hotelId' => $user->hotel_id,
            'departmentId' => $user->department_id,
            'emailAddress' => $user->email,
            'accountStatus' => $user->status->value,
            'emailConsent' => $user->email_consent_at !== null,
            'canRemind' => $user->consentsToReminders(),
            'participantCode' => $user->participant_code,
            'lastActivity' => self::formatDate($user->last_activity_at),
            'trainingStarted' => self::formatDate($user->training_started_at),
            'trainingCompleted' => self::formatDate($user->training_completed_at),
            'lessonsCompleted' => (int) $user->getAttribute('lessons_completed_count'),
            'lessonsTotal' => (int) $user->getAttribute('lessons_total'),
            'preTestScore' => self::percent($user->getAttribute('pre_test_percent')),
            'postTestScore' => self::percent($user->getAttribute('post_test_percent')),
        ];
    }

    public static function formatDate(?CarbonInterface $date): string
    {
        return $date === null ? '-' : $date->format(self::DATE_FORMAT);
    }

    private static function percent(mixed $value): ?int
    {
        return $value === null ? null : (int) round((float) $value);
    }
}
