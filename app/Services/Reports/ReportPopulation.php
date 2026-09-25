<?php

namespace App\Services\Reports;

use App\Enums\AccountStatus;
use App\Enums\ContentStatus;
use App\Enums\RoleplayStatus;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\AiScenario;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The employees a report is about, with the six figures every row and
 * every stat needs computed by the database (REP-01, REP-02, DATA-06,
 * DATA-07; spec 0003 Part D).
 *
 * One derived table: the employee rows of the filtered hotel, department
 * and person, each carrying correlated sub-selects for lessons completed
 * and total, scenarios completed and total, and the latest Pre-test and
 * Post-test percentage. Everything downstream (the stat cards, the donuts,
 * the Employee Results table, the completion-status filter, the exports)
 * reads those columns, so no figure is ever counted twice in two ways and
 * no row triggers a query of its own.
 *
 * "Completed" here means every published lesson of the employee's own
 * curriculum (their department, shared or their hotel) has a completion
 * row: the same rule JourneyService applies, written in SQL.
 */
final class ReportPopulation
{
    public const COL_LESSONS_COMPLETED = 'lessons_completed_count';

    public const COL_LESSONS_TOTAL = 'lessons_total_count';

    public const COL_SCENARIOS_COMPLETED = 'scenarios_completed_count';

    public const COL_SCENARIOS_TOTAL = 'scenarios_total_count';

    public const COL_PRE_SCORE = 'pre_score';

    public const COL_POST_SCORE = 'post_score';

    public function __construct(private readonly ReportFilters $filters) {}

    /**
     * The population with the completion-status filter applied: the rows of
     * the Employee Results table and the base of the stat cards.
     *
     * @return Builder<User>
     */
    public function query(): Builder
    {
        $query = $this->unfiltered();

        $this->applyCompletionStatus($query, $this->filters->completionStatus);

        return $query;
    }

    /**
     * The population before the completion-status filter, as a derived table
     * whose computed columns can be filtered and ordered like any other.
     *
     * @return Builder<User>
     */
    public function unfiltered(): Builder
    {
        return User::query()->fromSub($this->inner(), 'users');
    }

    /**
     * The ids of the population, for whereIn() on the event tables.
     *
     * @return Builder<User>
     */
    public function ids(): Builder
    {
        return $this->query()->select('users.id');
    }

    /**
     * The population with the search box applied (name, username,
     * participant code or department name).
     *
     * @return Builder<User>
     */
    public function searched(): Builder
    {
        $query = $this->query();

        $search = $this->filters->search;

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $where) use ($like): void {
                $where
                    ->where('users.name', 'like', $like)
                    ->orWhere('users.username', 'like', $like)
                    ->orWhere('users.participant_code', 'like', $like)
                    ->orWhereIn('users.department_id', DB::table('departments')->select('id')->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * One member of the population by id, or null when they are not in it
     * (another hotel's employee for a manager, a non-employee, a filter
     * that excludes them).
     */
    public function find(int $id): ?User
    {
        return $this->unfiltered()->whereKey($id)->with(['hotel', 'department'])->first();
    }

    // ---------------------------------------------------------------- figures

    /**
     * The computed columns of a population row, typed.
     *
     * @return array{lessonsCompleted: int, lessonsTotal: int, scenariosCompleted: int, scenariosTotal: int, preScore: int|null, postScore: int|null}
     */
    public static function figures(User $user): array
    {
        return [
            'lessonsCompleted' => (int) $user->getAttribute(self::COL_LESSONS_COMPLETED),
            'lessonsTotal' => (int) $user->getAttribute(self::COL_LESSONS_TOTAL),
            'scenariosCompleted' => (int) $user->getAttribute(self::COL_SCENARIOS_COMPLETED),
            'scenariosTotal' => (int) $user->getAttribute(self::COL_SCENARIOS_TOTAL),
            'preScore' => self::percent($user->getAttribute(self::COL_PRE_SCORE)),
            'postScore' => self::percent($user->getAttribute(self::COL_POST_SCORE)),
        ];
    }

    /**
     * Has this employee finished every lesson of their curriculum?
     */
    public static function hasCompletedAllLessons(User $user): bool
    {
        $figures = self::figures($user);

        return $figures['lessonsTotal'] > 0 && $figures['lessonsCompleted'] >= $figures['lessonsTotal'];
    }

    /**
     * The row's status: completed beats everything; then inactive (account
     * off, or no activity within the month); then active (this week); then
     * in progress (this month). The same bands the activity donut draws.
     */
    public static function statusOf(User $user): string
    {
        if (self::hasCompletedAllLessons($user)) {
            return ReportFilters::STATUS_COMPLETED;
        }

        $days = self::daysSinceActivity($user);

        if ($user->status !== AccountStatus::Active || $days === null || $days > ReportFilters::MONTH_DAYS) {
            return ReportFilters::STATUS_INACTIVE;
        }

        return $days <= ReportFilters::WEEK_DAYS
            ? ReportFilters::STATUS_ACTIVE
            : ReportFilters::STATUS_IN_PROGRESS;
    }

    public static function statusLabel(User $user): string
    {
        $status = self::statusOf($user);

        if ($status === ReportFilters::STATUS_INACTIVE) {
            $days = self::daysSinceActivity($user);

            return $days === null
                ? __('Inactive')
                : __('Inactive (:days days)', ['days' => $days]);
        }

        return match ($status) {
            ReportFilters::STATUS_COMPLETED => __('Completed'),
            ReportFilters::STATUS_ACTIVE => __('Active'),
            default => __('In progress'),
        };
    }

    /**
     * Whole days since the last recorded activity, null when there is none.
     */
    public static function daysSinceActivity(User $user): ?int
    {
        if ($user->last_activity_at === null) {
            return null;
        }

        return (int) $user->last_activity_at->copy()->startOfDay()->diffInDays(Date::today(), true);
    }

    /**
     * Which activity band an employee falls in: `week`, `month` or `inactive`.
     */
    public static function activityBand(User $user): string
    {
        $days = self::daysSinceActivity($user);

        if ($user->status !== AccountStatus::Active || $days === null || $days > ReportFilters::MONTH_DAYS) {
            return 'inactive';
        }

        return $days <= ReportFilters::WEEK_DAYS ? 'week' : 'month';
    }

    // ------------------------------------------------------------------ query

    /**
     * @return Builder<User>
     */
    private function inner(): Builder
    {
        $query = User::query()
            ->employees()
            ->select('users.*')
            ->addSelect([
                self::COL_LESSONS_COMPLETED => $this->completedLessonsSubquery(),
                self::COL_LESSONS_TOTAL => $this->curriculumLessonsCountSubquery(),
                self::COL_SCENARIOS_COMPLETED => $this->completedScenariosSubquery(),
                self::COL_SCENARIOS_TOTAL => $this->scenariosCountSubquery(),
                self::COL_PRE_SCORE => $this->latestScoreSubquery(TestType::Pre),
                self::COL_POST_SCORE => $this->latestScoreSubquery(TestType::Post),
            ]);

        if ($this->filters->hotelId !== null) {
            $query->where('users.hotel_id', $this->filters->hotelId);
        }

        if ($this->filters->departmentId !== null) {
            $query->where('users.department_id', $this->filters->departmentId);
        }

        if ($this->filters->employeeId !== null) {
            $query->whereKey($this->filters->employeeId);
        }

        return $query;
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyCompletionStatus(Builder $query, string $status): void
    {
        if ($status === ReportFilters::ALL_STATUSES) {
            return;
        }

        $weekAgo = CarbonImmutable::instance(Date::today())->subDays(ReportFilters::WEEK_DAYS)->startOfDay()->toDateTimeString();
        $monthAgo = CarbonImmutable::instance(Date::today())->subDays(ReportFilters::MONTH_DAYS)->startOfDay()->toDateTimeString();

        if ($status === ReportFilters::STATUS_COMPLETED) {
            $query
                ->where(self::COL_LESSONS_TOTAL, '>', 0)
                ->whereColumn(self::COL_LESSONS_COMPLETED, '>=', self::COL_LESSONS_TOTAL);

            return;
        }

        $query->where(function (Builder $notCompleted): void {
            $notCompleted
                ->where(self::COL_LESSONS_TOTAL, '<=', 0)
                ->orWhereColumn(self::COL_LESSONS_COMPLETED, '<', self::COL_LESSONS_TOTAL);
        });

        match ($status) {
            ReportFilters::STATUS_INACTIVE => $query->where(function (Builder $inactive) use ($monthAgo): void {
                $inactive
                    ->where('users.status', AccountStatus::Inactive->value)
                    ->orWhereNull('users.last_activity_at')
                    ->orWhere('users.last_activity_at', '<', $monthAgo);
            }),
            ReportFilters::STATUS_ACTIVE => $query
                ->where('users.status', AccountStatus::Active->value)
                ->where('users.last_activity_at', '>=', $weekAgo),
            default => $query
                ->where('users.status', AccountStatus::Active->value)
                ->where('users.last_activity_at', '>=', $monthAgo)
                ->where('users.last_activity_at', '<', $weekAgo),
        };
    }

    /**
     * Published lessons of published courses for the employee's department,
     * shared across hotels or belonging to theirs (ORG-04, JOURNEY-03).
     * Correlated on the outer `users` row.
     *
     * @return Builder<Lesson>
     */
    private function curriculumLessons(): Builder
    {
        return Lesson::query()
            ->where('lessons.status', ContentStatus::Published->value)
            ->whereExists(function (QueryBuilder $courses): void {
                $courses
                    ->select(DB::raw('1'))
                    ->from('courses')
                    ->whereColumn('courses.id', 'lessons.course_id')
                    ->where('courses.status', ContentStatus::Published->value)
                    ->whereColumn('courses.department_id', 'users.department_id')
                    ->where(function (QueryBuilder $scope): void {
                        $scope
                            ->whereNull('courses.hotel_id')
                            ->orWhereColumn('courses.hotel_id', 'users.hotel_id');
                    });
            });
    }

    /**
     * @return Builder<LessonCompletion>
     */
    private function completedLessonsSubquery(): Builder
    {
        return LessonCompletion::query()
            ->selectRaw('count(*)')
            ->whereColumn('lesson_completions.user_id', 'users.id')
            ->whereIn('lesson_completions.lesson_id', $this->curriculumLessons()->select('lessons.id'));
    }

    /**
     * @return Builder<Lesson>
     */
    private function curriculumLessonsCountSubquery(): Builder
    {
        return $this->curriculumLessons()->selectRaw('count(*)');
    }

    /**
     * Distinct scenarios with at least one completed, counted attempt
     * (RP-06, RP-13).
     *
     * @return Builder<RoleplayAttempt>
     */
    private function completedScenariosSubquery(): Builder
    {
        return RoleplayAttempt::query()
            ->selectRaw('count(distinct roleplay_attempts.ai_scenario_id)')
            ->whereColumn('roleplay_attempts.user_id', 'users.id')
            ->where('roleplay_attempts.status', RoleplayStatus::Completed->value)
            ->where('roleplay_attempts.is_preview', false);
    }

    /**
     * @return Builder<AiScenario>
     */
    private function scenariosCountSubquery(): Builder
    {
        return AiScenario::query()
            ->selectRaw('count(*)')
            ->where('ai_scenarios.status', ContentStatus::Published->value)
            ->whereColumn('ai_scenarios.department_id', 'users.department_id')
            ->where(function (Builder $scope): void {
                $scope
                    ->whereNull('ai_scenarios.hotel_id')
                    ->orWhereColumn('ai_scenarios.hotel_id', 'users.hotel_id');
            });
    }

    /**
     * The percentage of the employee's most recent submitted sitting of a
     * test of this type; null with none, or when nothing was gradable.
     *
     * @return Builder<TestAttempt>
     */
    private function latestScoreSubquery(TestType $type): Builder
    {
        return TestAttempt::query()
            ->selectRaw('CASE WHEN test_attempts.max_score > 0 THEN ROUND(test_attempts.score * 100.0 / test_attempts.max_score) ELSE NULL END')
            ->whereColumn('test_attempts.user_id', 'users.id')
            ->where('test_attempts.status', TestAttemptStatus::Submitted->value)
            ->whereIn('test_attempts.test_id', Test::query()->select('tests.id')->where('tests.type', $type->value))
            ->orderByDesc('test_attempts.submitted_at')
            ->orderByDesc('test_attempts.id')
            ->limit(1);
    }

    private static function percent(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
