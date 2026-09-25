<?php

namespace App\Services\Reports;

use App\Enums\AccountStatus;
use App\Enums\RoleplayStatus;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\Department;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The six stat cards and the four charts of Reports & Export (REP-01,
 * REP-02, TEST-10, AIE-04; spec 0003 Part D).
 *
 * Reads the population once (id, status, activity and the six computed
 * figures) and folds the state-based numbers in PHP; the three event-based
 * stats and the two bar charts are grouped by the database within the date
 * range. Nothing here is cached: every number is what the rows say now.
 */
final class ReportAggregates
{
    /** How many departments the two bar charts draw (the mockup's five columns). */
    public const CHART_DEPARTMENTS = 5;

    /** @var Collection<int, User>|null */
    private ?Collection $members = null;

    public function __construct(
        private readonly ReportFilters $filters,
        private readonly ReportPopulation $population,
    ) {}

    /**
     * @return list<array{key: string, value: int, label: string, detail: string}>
     */
    public function stats(): array
    {
        $members = $this->members();
        $total = $members->count();

        $active = $members->filter(fn (User $user): bool => $user->status === AccountStatus::Active)->count();
        $completedLessons = $members->filter(fn (User $user): bool => ReportPopulation::hasCompletedAllLessons($user))->count();
        $departments = $members->pluck('department_id')->filter()->unique()->count();

        $preTest = $this->countWithSubmittedTest(TestType::Pre);
        $postTest = $this->countWithSubmittedTest(TestType::Post);
        $scenarios = $this->countWithCompletedRoleplay();

        return [
            [
                'key' => 'totalEmployees',
                'value' => $total,
                'label' => __('Total Employees'),
                'detail' => trans_choice(':count Department|:count Departments', $departments, ['count' => $departments]),
            ],
            [
                'key' => 'activeAccounts',
                'value' => $active,
                'label' => __('Active Accounts'),
                'detail' => self::percentOf($active, $total),
            ],
            [
                'key' => 'completedPretest',
                'value' => $preTest,
                'label' => __('Completed Pre-test'),
                'detail' => self::percentOf($preTest, $total),
            ],
            [
                'key' => 'completedPosttest',
                'value' => $postTest,
                'label' => __('Completed Post-test'),
                'detail' => self::percentOf($postTest, $total),
            ],
            [
                'key' => 'completedAiScenarios',
                'value' => $scenarios,
                'label' => __('Completed AI Scenarios'),
                'detail' => self::percentOf($scenarios, $total),
            ],
            [
                'key' => 'completedLessons',
                'value' => $completedLessons,
                'label' => __('Completed All Lessons'),
                'detail' => self::percentOf($completedLessons, $total),
            ],
        ];
    }

    /**
     * Average Pre-test and Post-test percentage per department, over the
     * sittings submitted in the range (TEST-10).
     *
     * @return list<array{label: string, first: int, second: int}>
     */
    public function prePost(): array
    {
        $departments = $this->chartDepartments();

        if ($departments === []) {
            return [];
        }

        $query = TestAttempt::query()
            ->join('tests', 'tests.id', '=', 'test_attempts.test_id')
            ->where('test_attempts.status', TestAttemptStatus::Submitted->value)
            ->where('test_attempts.max_score', '>', 0)
            ->whereIn('test_attempts.user_id', $this->population->ids())
            ->whereIn('tests.department_id', array_keys($departments))
            ->groupBy('tests.department_id', 'tests.type')
            ->selectRaw('tests.department_id as department_id, tests.type as type, AVG(test_attempts.score * 100.0 / test_attempts.max_score) as avg_percent');

        $this->filters->withinRange($query, 'test_attempts.submitted_at');

        $averages = [];

        foreach ($query->get() as $row) {
            $averages[(int) $row->getAttribute('department_id')][(string) $row->getAttribute('type')] = (int) round((float) $row->getAttribute('avg_percent'));
        }

        $points = [];

        foreach ($departments as $id => $name) {
            $points[] = [
                'label' => $name,
                'first' => $averages[$id][TestType::Pre->value] ?? 0,
                'second' => $averages[$id][TestType::Post->value] ?? 0,
            ];
        }

        return $points;
    }

    /**
     * Average overall role-play score per department over the completed
     * attempts in the range (AIE-04, RP-11).
     *
     * @return list<array{label: string, value: int}>
     */
    public function aiPerformance(): array
    {
        $departments = $this->chartDepartments();

        if ($departments === []) {
            return [];
        }

        $query = RoleplayAttempt::query()
            ->join('ai_scenarios', 'ai_scenarios.id', '=', 'roleplay_attempts.ai_scenario_id')
            ->where('roleplay_attempts.status', RoleplayStatus::Completed->value)
            ->where('roleplay_attempts.is_preview', false)
            ->whereNotNull('roleplay_attempts.overall_score')
            ->whereIn('roleplay_attempts.user_id', $this->population->ids())
            ->whereIn('ai_scenarios.department_id', array_keys($departments))
            ->groupBy('ai_scenarios.department_id')
            ->selectRaw('ai_scenarios.department_id as department_id, AVG(roleplay_attempts.overall_score) as avg_score');

        $this->filters->withinRange($query, 'roleplay_attempts.started_at');

        $averages = [];

        foreach ($query->get() as $row) {
            $averages[(int) $row->getAttribute('department_id')] = (int) round((float) $row->getAttribute('avg_score'));
        }

        $points = [];

        foreach ($departments as $id => $name) {
            $points[] = ['label' => $name, 'value' => $averages[$id] ?? 0];
        }

        return $points;
    }

    /**
     * Employees who finished every lesson, started some, or none, as
     * percentages of the population (PROG-02).
     *
     * @return array{overall: int, completed: int, inProgress: int, notStarted: int}
     */
    public function completion(): array
    {
        $members = $this->members();
        $total = $members->count();

        $completed = 0;
        $inProgress = 0;

        foreach ($members as $user) {
            if (ReportPopulation::hasCompletedAllLessons($user)) {
                $completed++;
            } elseif (ReportPopulation::figures($user)['lessonsCompleted'] > 0) {
                $inProgress++;
            }
        }

        $completedPercent = self::percentValue($completed, $total);
        $inProgressPercent = self::percentValue($inProgress, $total);

        return [
            'overall' => $completedPercent,
            'completed' => $completedPercent,
            'inProgress' => $inProgressPercent,
            'notStarted' => $total === 0 ? 0 : max(0, 100 - $completedPercent - $inProgressPercent),
        ];
    }

    /**
     * Active this week, active this month, inactive (DATA-06).
     *
     * @return array{total: int, activeThisWeek: int, activeThisMonth: int, inactive: int}
     */
    public function activity(): array
    {
        $bands = ['week' => 0, 'month' => 0, 'inactive' => 0];

        foreach ($this->members() as $user) {
            $bands[ReportPopulation::activityBand($user)]++;
        }

        return [
            'total' => array_sum($bands),
            'activeThisWeek' => $bands['week'],
            'activeThisMonth' => $bands['month'],
            'inactive' => $bands['inactive'],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The population, once: only the columns the folds need.
     *
     * @return Collection<int, User>
     */
    private function members(): Collection
    {
        return $this->members ??= $this->population->query()
            ->select([
                'users.id',
                'users.status',
                'users.department_id',
                'users.last_activity_at',
                'users.'.ReportPopulation::COL_LESSONS_COMPLETED,
                'users.'.ReportPopulation::COL_LESSONS_TOTAL,
            ])
            ->get();
    }

    private function countWithSubmittedTest(TestType $type): int
    {
        return $this->population->query()
            ->whereHas('testAttempts', function (Builder $attempts) use ($type): void {
                /** @var Builder<TestAttempt> $attempts */
                $attempts
                    ->where('status', TestAttemptStatus::Submitted->value)
                    ->whereHas('test', fn (Builder $test) => $test->where('type', $type->value));

                $this->filters->withinRange($attempts, 'test_attempts.submitted_at');
            })
            ->count();
    }

    private function countWithCompletedRoleplay(): int
    {
        return $this->population->query()
            ->whereHas('roleplayAttempts', function (Builder $attempts): void {
                /** @var Builder<RoleplayAttempt> $attempts */
                $attempts
                    ->where('status', RoleplayStatus::Completed->value)
                    ->where('is_preview', false);

                $this->filters->withinRange($attempts, 'roleplay_attempts.started_at');
            })
            ->count();
    }

    /**
     * The departments the bar charts draw: the ones with the most employees
     * in the population, in catalogue order, capped at the mockup's five.
     *
     * @return array<int, string> id => name
     */
    private function chartDepartments(): array
    {
        $counts = $this->population->query()
            ->whereNotNull('users.department_id')
            ->groupBy('users.department_id')
            ->selectRaw('users.department_id as department_id, count(*) as members')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('users.department_id')
            ->limit(self::CHART_DEPARTMENTS)
            ->get()
            ->pluck('members', 'department_id');

        if ($counts->isEmpty()) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Department::query()
            ->whereIn('id', $counts->keys()->all())
            ->orderBy('position')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $names;
    }

    private static function percentOf(int $part, int $total): string
    {
        return self::percentValue($part, $total).'%';
    }

    private static function percentValue(int $part, int $total): int
    {
        return $total === 0 ? 0 : (int) round($part / $total * 100);
    }
}
