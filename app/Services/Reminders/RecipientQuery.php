<?php

namespace App\Services\Reminders;

use App\Enums\Role;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Date;

/**
 * The employees a reminder may reach, and the filters the Messages screen
 * puts on them (REM-01, REM-02, REM-07; spec 0003 Part D).
 *
 * One place builds the recipient query for the recipients table, the
 * "select all matching" send and the automation runner, so the four activity
 * filters can never disagree with the automation triggers of the same name.
 *
 * Tenancy is applied first: a manager's query starts from their own hotel and
 * nothing else (REM-07, ROLE-02). This is the safety net; the policy and the
 * 403 in ReminderBatchService are the authorization.
 */
final class RecipientQuery
{
    public const ALL_HOTELS = 'all-hotels';

    public const ALL_DEPARTMENTS = 'all-departments';

    public const CONSENT_GRANTED = 'consent-granted';

    public const CONSENT_MISSING = 'consent-missing';

    public const ALL_CONSENT = 'all-consent';

    public const ACTIVITY_INACTIVE = 'inactive-5-days';

    public const ACTIVITY_NOT_STARTED = 'not-started';

    public const ACTIVITY_PRETEST_ONLY = 'pretest-only';

    public const ALL_ACTIVITY = 'all-activity';

    /**
     * The learner tables that prove somebody has started (spec 0003 B.6).
     *
     * @var list<string>
     */
    private const ACTIVITY_TABLES = ['block_completions', 'lesson_completions', 'test_attempts'];

    /**
     * Every employee this actor may address, with hotel and department
     * loaded for the table and the renderer.
     *
     * @return Builder<User>
     */
    public static function forActor(User $actor): Builder
    {
        $query = User::query()
            ->employees()
            ->with(['hotel', 'department'])
            ->orderBy('name')
            ->orderBy('id');

        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            // A manager with no hotel sees nobody rather than everybody.
            $query->where('users.hotel_id', $actor->hotel_id ?? 0);
        }

        return $query;
    }

    /**
     * Narrow the query by the five screen filters. Unknown values read as
     * "all", so a stale link never errors and never widens the scope.
     *
     * @param  Builder<User>  $query
     * @param  array{hotel?: string, department?: string, consent?: string, activity?: string, search?: string}  $filters
     * @return Builder<User>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $hotel = (int) ($filters['hotel'] ?? 0);
        $department = (int) ($filters['department'] ?? 0);

        if ($hotel > 0) {
            $query->where('users.hotel_id', $hotel);
        }

        if ($department > 0) {
            $query->where('users.department_id', $department);
        }

        match ($filters['consent'] ?? self::ALL_CONSENT) {
            self::CONSENT_GRANTED => $query->whereNotNull('users.email_consent_at'),
            self::CONSENT_MISSING => $query->whereNull('users.email_consent_at'),
            default => null,
        };

        match ($filters['activity'] ?? self::ALL_ACTIVITY) {
            self::ACTIVITY_INACTIVE => self::idleSince($query, self::inactivityCutoff()),
            self::ACTIVITY_NOT_STARTED => self::notStarted($query),
            self::ACTIVITY_PRETEST_ONLY => self::preTestOnly($query),
            default => null,
        };

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            self::search($query, $search);
        }

        return $query;
    }

    /**
     * Idle since before the cutoff, or never active and created before it: a
     * brand-new account is not "inactive" yet (REM-03, `inactive_days`).
     *
     * @param  Builder<User>  $query
     */
    public static function idleSince(Builder $query, CarbonInterface $cutoff): void
    {
        $query->where(function (Builder $idle) use ($cutoff): void {
            $idle
                ->where('users.last_activity_at', '<', $cutoff)
                ->orWhere(function (Builder $never) use ($cutoff): void {
                    $never
                        ->whereNull('users.last_activity_at')
                        ->where('users.created_at', '<', $cutoff);
                });
        });
    }

    /**
     * No step, no lesson and no test attempt on record (REM-03, `not_started`).
     *
     * @param  Builder<User>  $query
     */
    public static function notStarted(Builder $query): void
    {
        foreach (self::ACTIVITY_TABLES as $table) {
            $query->whereNotExists(function (QueryBuilder $activity) use ($table): void {
                $activity
                    ->selectRaw('1')
                    ->from($table)
                    ->whereColumn($table.'.user_id', 'users.id');
            });
        }
    }

    /**
     * Submitted a Pre-test and then nothing: no step and no lesson completed.
     *
     * @param  Builder<User>  $query
     */
    public static function preTestOnly(Builder $query): void
    {
        $query->whereExists(function (QueryBuilder $attempt): void {
            $attempt
                ->selectRaw('1')
                ->from('test_attempts')
                ->join('tests', 'tests.id', '=', 'test_attempts.test_id')
                ->whereColumn('test_attempts.user_id', 'users.id')
                ->where('test_attempts.status', TestAttemptStatus::Submitted->value)
                ->where('tests.type', TestType::Pre->value);
        });

        foreach (['block_completions', 'lesson_completions'] as $table) {
            $query->whereNotExists(function (QueryBuilder $progress) use ($table): void {
                $progress
                    ->selectRaw('1')
                    ->from($table)
                    ->whereColumn($table.'.user_id', 'users.id');
            });
        }
    }

    /**
     * Name, username, hotel or department, case-insensitively.
     *
     * @param  Builder<User>  $query
     */
    public static function search(Builder $query, string $term): void
    {
        $like = '%'.mb_strtolower($term).'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner
                ->whereRaw('LOWER(users.name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(users.username) LIKE ?', [$like])
                ->orWhereHas('hotel', fn (Builder $hotel) => $hotel->withoutGlobalScopes()->whereRaw('LOWER(hotels.name) LIKE ?', [$like]))
                ->orWhereHas('department', fn (Builder $department) => $department->whereRaw('LOWER(departments.name) LIKE ?', [$like]));
        });
    }

    /**
     * The moment before which an employee counts as inactive, from the one
     * configured window (spec 0003 Part C).
     */
    public static function inactivityCutoff(): CarbonInterface
    {
        return Date::now()->subDays(self::inactiveDays());
    }

    public static function inactiveDays(): int
    {
        return max(1, (int) config('guesvia.reminders.inactive_days', 5));
    }
}
