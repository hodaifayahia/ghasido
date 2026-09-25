<?php

namespace App\Services\Dashboard;

use App\Enums\ContentStatus;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The read side of the Admin Dashboard (ADM-01, REP-01; spec 0003 Part D).
 *
 * Every figure is computed from rows on the way out: active employee
 * accounts, lesson completions against each learner's own curriculum,
 * submitted test sittings and real role-play attempts. Nothing is cached on
 * a user, so the page agrees with the Employees, Reports and learner screens
 * that read the same rows.
 *
 * Five queries feed the whole page: the employee population (departments
 * eager loaded), the published lesson count per department and hotel, the
 * in-curriculum completions per employee, the employees with a submitted
 * Pre-test, and the recent activity sources (five rows each). Nothing runs
 * per employee.
 *
 * Passing a hotel scopes every number to that hotel, which is what a manager
 * will see on the hotel manager dashboard (ROLE-02).
 */
final class DashboardStats
{
    /** Rows shown per Needs Attention tab. */
    public const ATTENTION_ROWS = 5;

    /** Rows shown in Recent Activity. */
    public const ACTIVITY_ROWS = 5;

    /** Rows shown in At-risk Learners (spec 0005 §4.1). */
    public const AT_RISK_ROWS = 5;

    /** A learner is listed as at risk from this score up. */
    public const AT_RISK_MIN_SCORE = 2;

    /** Each recent activity source contributes at most this many candidates. */
    private const ACTIVITY_CANDIDATES = 5;

    private const STATE_COMPLETED = 'completed';

    private const STATE_IN_PROGRESS = 'inProgress';

    private const STATE_NOT_STARTED = 'notStarted';

    /**
     * Per employee id: where they stand and how far they are.
     *
     * @var array<int, array{state: string, percent: int, completed: int, total: int}>
     */
    private array $progress = [];

    /**
     * Employee ids with a submitted Pre-test.
     *
     * @var array<int, true>
     */
    private array $preTested = [];

    /**
     * The props of pages/Dashboard.vue (resources/js/types/dashboard.ts).
     *
     * @return array{stats: list<array<string, mixed>>, trainingOverview: array{all: array{completed: int, inProgress: int, notStarted: int}, departments: list<array{id: int, name: string, breakdown: array{completed: int, inProgress: int, notStarted: int}}>}, departmentProgress: list<array{id: int, name: string, percent: int}>, needsAttention: list<array{key: string, label: string, total: int, employees: list<array{id: int, name: string, department: string, lastLogin: string}>}>, recentActivity: list<array{id: int, date: string, time: string, employee: string, type: string, activity: string, details: string}>, atRisk: array{total: int, high: int, medium: int, reasons: array<string, int>, rows: list<array{id: int, name: string, department: string, level: string, score: int, reasons: list<string>}>}}
     */
    public function build(?Hotel $hotel = null): array
    {
        $employees = $this->employees($hotel);
        $this->preTested = $this->preTestSubmitters($employees);
        $this->progress = $this->progressOf($employees, $hotel);

        $all = $this->breakdownOf($employees);
        $departments = $this->departments($hotel);

        $byDepartment = $employees->groupBy(fn (User $user): int => (int) $user->department_id);

        $overview = [];
        $departmentProgress = [];

        foreach ($departments as $department) {
            /** @var Collection<int, User> $members */
            $members = $byDepartment->get($department->id, new Collection);

            $overview[] = [
                'id' => $department->id,
                'name' => $department->name,
                'breakdown' => $this->breakdownOf($members),
            ];

            $departmentProgress[] = [
                'id' => $department->id,
                'name' => $department->name,
                'percent' => $this->averagePercent($members),
            ];
        }

        return [
            'stats' => $this->stats($hotel, $departments->count(), $employees, $all),
            'trainingOverview' => [
                'all' => $all,
                'departments' => $overview,
            ],
            'departmentProgress' => $departmentProgress,
            'needsAttention' => $this->needsAttention($employees),
            'recentActivity' => $this->recentActivity($hotel),
            'atRisk' => $this->atRisk($employees),
        ];
    }

    // ------------------------------------------------------------ population

    /**
     * Active employee accounts of hotels still in the portfolio, or of the
     * one hotel when scoped, with their department for the tables.
     *
     * @return Collection<int, User>
     */
    private function employees(?Hotel $hotel): Collection
    {
        return User::query()
            ->select([
                'users.id', 'users.name', 'users.hotel_id', 'users.department_id', 'users.status',
                'users.last_login_at', 'users.last_activity_at',
                'users.training_started_at', 'users.training_completed_at', 'users.created_at',
            ])
            ->employees()
            ->active()
            ->when(
                $hotel !== null,
                fn (Builder $query) => $query->where('users.hotel_id', $hotel?->id),
                fn (Builder $query) => $query->whereHas('hotel', fn (Builder $hotels) => $hotels->notArchived()),
            )
            ->with('department:id,name')
            ->orderBy('users.id')
            ->get();
    }

    /**
     * The departments the donut filter and the progress bars list: the
     * shared catalogue, or what the scoped hotel may draw on (ORG-02).
     *
     * @return Collection<int, Department>
     */
    private function departments(?Hotel $hotel): Collection
    {
        return Department::query()
            ->active()
            ->when(
                $hotel !== null,
                fn (Builder $query) => $query->availableTo($hotel),
                fn (Builder $query) => $query->globalCatalogue(),
            )
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Which of these employees have submitted a Pre-test (JOURNEY-01), in
     * one query.
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, true>
     */
    private function preTestSubmitters(Collection $employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = TestAttempt::query()
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereIn('user_id', $employees->modelKeys())
            ->whereHas('test', fn (Builder $test) => $test->where('type', TestType::Pre->value))
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    // -------------------------------------------------------------- progress

    /**
     * Where every employee stands, without a query per employee.
     *
     * The curriculum of a learner is the published lessons of the published
     * courses of their department that are shared or their hotel's, the same
     * rule as Lesson::scopeForLearner() and JourneyService::progressPercent().
     * Here it is evaluated for everyone at once: lesson totals grouped by
     * (department, hotel) and completions joined back to the learner's own
     * department and hotel.
     *
     * Completed: `training_completed_at` is stamped (DATA-07), or every
     * lesson of the curriculum is done. In progress: training has started, a
     * lesson is done or the Pre-test is submitted. Otherwise not started.
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, array{state: string, percent: int, completed: int, total: int}>
     */
    private function progressOf(Collection $employees, ?Hotel $hotel): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $totals = $this->lessonTotals();
        $completions = $this->completionsByEmployee($employees, $hotel);

        $progress = [];

        foreach ($employees as $user) {
            $departmentId = (int) $user->department_id;
            $total = ($totals[$departmentId][0] ?? 0) + ($totals[$departmentId][(int) $user->hotel_id] ?? 0);
            $completed = min($completions[$user->id] ?? 0, $total);

            $finished = $user->training_completed_at !== null || ($total > 0 && $completed >= $total);

            if ($finished) {
                $state = self::STATE_COMPLETED;
                $percent = 100;
            } elseif ($user->training_started_at !== null || $completed > 0 || isset($this->preTested[$user->id])) {
                $state = self::STATE_IN_PROGRESS;
                $percent = $total > 0 ? (int) round($completed / $total * 100) : 0;
            } else {
                $state = self::STATE_NOT_STARTED;
                $percent = 0;
            }

            $progress[$user->id] = [
                'state' => $state,
                'percent' => $percent,
                'completed' => $completed,
                'total' => $total,
            ];
        }

        return $progress;
    }

    /**
     * Published lessons of published courses, counted per department and
     * hotel. Key 0 stands for the shared catalogue (`hotel_id` null).
     *
     * @return array<int, array<int, int>>
     */
    private function lessonTotals(): array
    {
        $rows = Lesson::query()
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->where('lessons.status', ContentStatus::Published->value)
            ->where('courses.status', ContentStatus::Published->value)
            ->groupBy('courses.department_id', 'courses.hotel_id')
            ->toBase()
            ->selectRaw('courses.department_id as department_id, courses.hotel_id as hotel_id, count(*) as aggregate')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->department_id][(int) $row->hotel_id] = (int) $row->aggregate;
        }

        return $totals;
    }

    /**
     * Completed lessons that are in each employee's own curriculum, keyed by
     * employee id. A completion on a lesson that was unpublished or moved to
     * another department never inflates a count.
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, int>
     */
    private function completionsByEmployee(Collection $employees, ?Hotel $hotel): array
    {
        /** @var array<int, int> $counts */
        $counts = LessonCompletion::query()
            ->join('users', 'users.id', '=', 'lesson_completions.user_id')
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->where('lessons.status', ContentStatus::Published->value)
            ->where('courses.status', ContentStatus::Published->value)
            ->whereColumn('courses.department_id', 'users.department_id')
            ->where(function (Builder $scope): void {
                $scope
                    ->whereNull('courses.hotel_id')
                    ->orWhereColumn('courses.hotel_id', 'users.hotel_id');
            })
            ->when($hotel !== null, fn (Builder $query) => $query->where('users.hotel_id', $hotel?->id))
            ->whereIn('lesson_completions.user_id', $employees->modelKeys())
            ->groupBy('lesson_completions.user_id')
            ->toBase()
            ->selectRaw('lesson_completions.user_id as user_id, count(*) as aggregate')
            ->pluck('aggregate', 'user_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return array{completed: int, inProgress: int, notStarted: int}
     */
    private function breakdownOf(Collection $employees): array
    {
        $completed = 0;
        $inProgress = 0;
        $notStarted = 0;

        foreach ($employees as $user) {
            match ($this->stateOf($user)) {
                self::STATE_COMPLETED => $completed++,
                self::STATE_IN_PROGRESS => $inProgress++,
                default => $notStarted++,
            };
        }

        return [
            self::STATE_COMPLETED => $completed,
            self::STATE_IN_PROGRESS => $inProgress,
            self::STATE_NOT_STARTED => $notStarted,
        ];
    }

    /**
     * Mean progress across these employees, whole percent; 0 with nobody.
     *
     * @param  Collection<int, User>  $employees
     */
    private function averagePercent(Collection $employees): int
    {
        if ($employees->isEmpty()) {
            return 0;
        }

        $sum = $employees->sum(fn (User $user): int => $this->percentOfUser($user));

        return (int) round($sum / $employees->count());
    }

    private function stateOf(User $user): string
    {
        return $this->progress[$user->id]['state'] ?? self::STATE_NOT_STARTED;
    }

    private function percentOfUser(User $user): int
    {
        return $this->progress[$user->id]['percent'] ?? 0;
    }

    private function completedLessonsOf(User $user): int
    {
        return $this->progress[$user->id]['completed'] ?? 0;
    }

    // ----------------------------------------------------------------- stats

    /**
     * The six stat cards.
     *
     * @param  Collection<int, User>  $employees
     * @param  array{completed: int, inProgress: int, notStarted: int}  $all
     * @return list<array<string, mixed>>
     */
    private function stats(?Hotel $hotel, int $departments, Collection $employees, array $all): array
    {
        $total = $employees->count();
        $started = $all[self::STATE_COMPLETED] + $all[self::STATE_IN_PROGRESS];
        $completed = $all[self::STATE_COMPLETED];

        return [
            $this->hotelStat($hotel),
            ['key' => 'departments', 'value' => $departments, 'label' => $this->text('Departments'), 'detail' => $this->text('Active')],
            ['key' => 'employees', 'value' => $total, 'label' => $this->text('Employees'), 'detail' => $this->text('Total accounts')],
            ['key' => 'trainingStarted', 'value' => $started, 'label' => $this->text('Started Training'), 'detail' => $this->percentOf($started, $total)],
            ['key' => 'trainingCompleted', 'value' => $completed, 'label' => $this->text('Completed Training'), 'detail' => $this->percentOf($completed, $total)],
            ['key' => 'averageProgress', 'value' => $this->averagePercent($employees), 'unit' => '%', 'label' => $this->text('Average Progress')],
        ];
    }

    /**
     * Hotels in the portfolio (archived ones drop out, as on the Hotels
     * screen). One hotel is named; several read "Active portfolio".
     *
     * @return array<string, mixed>
     */
    private function hotelStat(?Hotel $hotel): array
    {
        if ($hotel !== null) {
            return ['key' => 'hotels', 'value' => 1, 'label' => $this->text('Hotel'), 'detail' => $hotel->name];
        }

        $count = Hotel::totalInPortfolio();
        $only = $count === 1 ? Hotel::query()->notArchived()->first() : null;

        return [
            'key' => 'hotels',
            'value' => $count,
            'label' => $count === 1 ? $this->text('Hotel') : $this->text('Hotels'),
            'detail' => $only->name ?? $this->text('Active portfolio'),
        ];
    }

    /**
     * A translated string, always a string (I18N-02).
     *
     * @param  array<string, int|float|string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        $value = __($key, $replace);

        return is_string($value) ? $value : $key;
    }

    private function percentOf(int $part, int $whole): string
    {
        return $whole === 0 ? '0%' : round($part / $whole * 100, 1).'%';
    }

    // ------------------------------------------------------- needs attention

    /**
     * The three tabs: idle learners, learners who never started, and
     * learners who sat the Pre-test but have not finished a lesson yet. Each
     * carries its full count and its first rows.
     *
     * Idle means no activity for `guesvia.reminders.inactive_days`, the same
     * window the inactivity reminder rule reads, and training not finished:
     * a learner who completed everything is not chased.
     *
     * @param  Collection<int, User>  $employees
     * @return list<array{key: string, label: string, total: int, employees: list<array{id: int, name: string, department: string, lastLogin: string}>}>
     */
    private function needsAttention(Collection $employees): array
    {
        $cutoff = Date::now()->subDays(self::inactiveDays());

        $inactive = $employees
            ->filter(fn (User $user): bool => $user->last_activity_at !== null
                && $user->last_activity_at->lessThan($cutoff)
                && $this->stateOf($user) !== self::STATE_COMPLETED)
            ->sortBy(fn (User $user): int => $user->last_activity_at?->getTimestamp() ?? 0);

        $notStarted = $employees
            ->filter(fn (User $user): bool => $this->stateOf($user) === self::STATE_NOT_STARTED)
            ->sortByDesc(fn (User $user): int => $user->last_login_at?->getTimestamp() ?? 0);

        $preTestFinished = $employees
            ->filter(fn (User $user): bool => isset($this->preTested[$user->id]) && $this->completedLessonsOf($user) === 0)
            ->sortByDesc(fn (User $user): int => $user->last_activity_at?->getTimestamp() ?? 0);

        return [
            $this->attentionGroup('inactive', $this->text('Inactive Employees'), $inactive),
            $this->attentionGroup('notStarted', $this->text('Not Started'), $notStarted),
            $this->attentionGroup('pretestFinished', $this->text('Pre-test Finished'), $preTestFinished),
        ];
    }

    /**
     * @param  Collection<int, User>  $members
     * @return array{key: string, label: string, total: int, employees: list<array{id: int, name: string, department: string, lastLogin: string}>}
     */
    private function attentionGroup(string $key, string $label, Collection $members): array
    {
        $rows = [];

        foreach ($members->take(self::ATTENTION_ROWS) as $user) {
            $rows[] = [
                'id' => $user->id,
                'name' => $user->name,
                'department' => $user->department->name ?? '',
                'lastLogin' => $this->daysAgo($user->last_login_at ?? $user->last_activity_at),
            ];
        }

        return [
            'key' => $key,
            'label' => $label,
            'total' => $members->count(),
            'employees' => $rows,
        ];
    }

    /**
     * "Today", "1 day ago", "N days ago" or "Never": whole calendar days in
     * the app timezone, never weeks, as the mockup writes it.
     */
    private function daysAgo(?CarbonInterface $at): string
    {
        if ($at === null) {
            return $this->text('Never');
        }

        $days = (int) $at->copy()->startOfDay()->diffInDays(Date::now()->startOfDay());

        return match (true) {
            $days <= 0 => $this->text('Today'),
            $days === 1 => $this->text('1 day ago'),
            default => $this->text(':count days ago', ['count' => $days]),
        };
    }

    // --------------------------------------------------------------- at risk

    /**
     * Learners likely to drop out or fall behind, with the reasons, from
     * rules anyone can check (spec 0005 §4.1): no AI decides who is at risk.
     * Each signal adds to a score; two or more is listed, three or more is
     * high. Completed learners are never listed. Two extra queries: the
     * latest Pre-test percentage and the role-play average per learner.
     *
     * @param  Collection<int, User>  $employees
     * @return array{total: int, high: int, medium: int, reasons: array<string, int>, rows: list<array{id: int, name: string, department: string, level: string, score: int, reasons: list<string>}>}
     */
    private function atRisk(Collection $employees): array
    {
        $candidates = $employees->filter(fn (User $user): bool => $this->stateOf($user) !== self::STATE_COMPLETED);
        $preTest = $this->preTestPercents($candidates);
        $roleplay = $this->roleplayAverages($candidates);
        $now = Date::now();
        $inactiveDays = self::inactiveDays();

        $rows = [];
        $tally = [];

        foreach ($candidates as $user) {
            $score = 0;
            $reasons = [];
            $flag = function (string $key, int $weight, string $reason) use (&$score, &$reasons, &$tally): void {
                $score += $weight;
                $reasons[] = $reason;
                $tally[$key] = ($tally[$key] ?? 0) + 1;
            };

            $idleDays = $user->last_activity_at === null ? null : (int) $user->last_activity_at->diffInDays($now);
            $accountDays = $user->created_at === null ? 0 : (int) $user->created_at->diffInDays($now);
            $startedDays = $user->training_started_at === null ? null : (int) $user->training_started_at->diffInDays($now);

            // Never started and gone quiet are one fact, not two: a learner
            // who never started is flagged once, as not started.
            if ($this->stateOf($user) === self::STATE_NOT_STARTED) {
                if ($accountDays >= 7) {
                    $flag('notStarted', 2, $this->text('Not started after :count days', ['count' => $accountDays]));
                }
            } elseif ($idleDays !== null && $idleDays >= $inactiveDays) {
                $flag('inactive', 2, $this->text('No activity for :count days', ['count' => $idleDays]));
            }

            if ($startedDays !== null && $startedDays >= 14 && $this->percentOfUser($user) < 25) {
                $flag('slowProgress', 1, $this->text('Slow progress: :percent% after :count days', ['percent' => $this->percentOfUser($user), 'count' => $startedDays]));
            }

            if (isset($preTest[$user->id]) && $preTest[$user->id] < 40) {
                $flag('lowPreTest', 1, $this->text('Low Pre-test score (:percent%)', ['percent' => $preTest[$user->id]]));
            }

            if (isset($roleplay[$user->id]) && $roleplay[$user->id] < 50) {
                $flag('lowRoleplay', 1, $this->text('Role-play average :score/100', ['score' => $roleplay[$user->id]]));
            }

            if ($score >= self::AT_RISK_MIN_SCORE) {
                $rows[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department->name ?? '',
                    'level' => $score >= 3 ? 'high' : 'medium',
                    'score' => $score,
                    'reasons' => $reasons,
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        return [
            'total' => count($rows),
            'high' => count(array_filter($rows, fn (array $row): bool => $row['level'] === 'high')),
            'medium' => count(array_filter($rows, fn (array $row): bool => $row['level'] === 'medium')),
            'reasons' => $tally,
            'rows' => array_slice($rows, 0, self::AT_RISK_ROWS),
        ];
    }

    /**
     * The latest submitted Pre-test percentage per learner.
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, int>
     */
    private function preTestPercents(Collection $employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $percents = [];

        TestAttempt::query()
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereIn('user_id', $employees->modelKeys())
            ->whereHas('test', fn (Builder $test) => $test->where('type', TestType::Pre->value))
            ->whereNotNull('max_score')
            ->where('max_score', '>', 0)
            ->orderBy('submitted_at')
            ->get(['user_id', 'score', 'max_score'])
            ->each(function (TestAttempt $attempt) use (&$percents): void {
                $percents[$attempt->user_id] = (int) round((float) $attempt->score / (float) $attempt->max_score * 100);
            });

        return $percents;
    }

    /**
     * The average overall role-play score per learner (real, scored
     * conversations only; RP-13).
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, int>
     */
    private function roleplayAverages(Collection $employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        return RoleplayAttempt::query()
            ->whereIn('user_id', $employees->modelKeys())
            ->where('is_preview', false)
            ->whereNotNull('overall_score')
            ->groupBy('user_id')
            ->toBase()
            ->selectRaw('user_id, avg(overall_score) as average')
            ->pluck('average', 'user_id')
            ->map(fn ($value): int => (int) round((float) $value))
            ->all();
    }

    // ------------------------------------------------------- recent activity

    /**
     * The latest five events across lesson completions, submitted test
     * sittings, real role-play attempts, training completed and training
     * started. Each source gives its own latest five, the union is sorted and
     * cut, and only the surviving rows resolve their detail line.
     *
     * @return list<array{id: int, date: string, time: string, employee: string, type: string, activity: string, details: string}>
     */
    private function recentActivity(?Hotel $hotel): array
    {
        $candidates = [
            ...$this->lessonCompletionEvents($hotel),
            ...$this->testEvents($hotel),
            ...$this->roleplayEvents($hotel),
            ...$this->trainingCompletedEvents($hotel),
            ...$this->trainingStartedEvents($hotel),
        ];

        usort($candidates, fn (ActivityEvent $a, ActivityEvent $b): int => $b->at <=> $a->at);

        $rows = [];

        foreach (array_slice($candidates, 0, self::ACTIVITY_ROWS) as $index => $event) {
            $rows[] = [
                'id' => $index + 1,
                'date' => $event->at->format('d M Y'),
                'time' => $event->at->format('H:i'),
                'employee' => $event->employee,
                'type' => $event->type,
                'activity' => $event->activity,
                'details' => $event->details(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<ActivityEvent>
     */
    private function lessonCompletionEvents(?Hotel $hotel): array
    {
        $events = [];

        $completions = LessonCompletion::query()
            ->with(['user:id,name', 'lesson:id,title,course_id'])
            ->when($hotel !== null, fn (Builder $query) => $query->whereHas('user', fn (Builder $users) => $users->where('hotel_id', $hotel?->id)))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_CANDIDATES)
            ->get();

        foreach ($completions as $completion) {
            $events[] = new ActivityEvent(
                at: $completion->completed_at,
                employee: $completion->user->name ?? '',
                type: 'lessonCompleted',
                activity: $this->text('Completed a lesson'),
                details: function () use ($completion): string {
                    $lesson = $completion->lesson;

                    if ($lesson === null) {
                        return $this->text('Lesson');
                    }

                    $number = $lesson->stepNumberIn();

                    return $number > 0
                        ? $this->text('Lesson :number – :title', ['number' => $number, 'title' => $lesson->title])
                        : $lesson->title;
                },
            );
        }

        return $events;
    }

    /**
     * @return list<ActivityEvent>
     */
    private function testEvents(?Hotel $hotel): array
    {
        $events = [];

        $attempts = TestAttempt::query()
            ->with(['user:id,name', 'test:id,type,settings'])
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereNotNull('submitted_at')
            ->when($hotel !== null, fn (Builder $query) => $query->whereHas('user', fn (Builder $users) => $users->where('hotel_id', $hotel?->id)))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_CANDIDATES)
            ->get();

        foreach ($attempts as $attempt) {
            if ($attempt->submitted_at === null) {
                continue;
            }

            $summary = $attempt->scoreSummary();

            $details = $summary['percent'] === null
                ? $this->text('Submitted')
                : $this->text('Score: :percent% (:score/:max)', [
                    'percent' => $summary['percent'],
                    'score' => (int) round($summary['score'] ?? 0),
                    'max' => (int) round($summary['max_score'] ?? 0),
                ]);

            $events[] = new ActivityEvent(
                at: $attempt->submitted_at,
                employee: $attempt->user->name ?? '',
                type: 'pretestFinished',
                activity: $attempt->test?->type === TestType::Post ? $this->text('Finished Post-test') : $this->text('Finished Pre-test'),
                details: $details,
            );
        }

        return $events;
    }

    /**
     * @return list<ActivityEvent>
     */
    private function roleplayEvents(?Hotel $hotel): array
    {
        $events = [];

        $attempts = RoleplayAttempt::query()
            ->with(['user:id,name', 'scenario:id,title'])
            ->counted()
            ->when($hotel !== null, fn (Builder $query) => $query->whereHas('user', fn (Builder $users) => $users->where('hotel_id', $hotel?->id)))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_CANDIDATES)
            ->get();

        foreach ($attempts as $attempt) {
            $events[] = new ActivityEvent(
                at: $attempt->started_at,
                employee: $attempt->user->name ?? '',
                type: 'roleplayUsed',
                activity: $this->text('Used AI Role-play'),
                details: $this->text('Scenario: :title', ['title' => $attempt->scenario->title ?? '']),
            );
        }

        return $events;
    }

    /**
     * @return list<ActivityEvent>
     */
    private function trainingCompletedEvents(?Hotel $hotel): array
    {
        $events = [];

        foreach ($this->learnersStamped('training_completed_at', $hotel) as $user) {
            if ($user->training_completed_at === null) {
                continue;
            }

            $events[] = new ActivityEvent(
                at: $user->training_completed_at,
                employee: $user->name,
                type: 'trainingCompleted',
                activity: $this->text('Completed Training'),
                details: $this->text('100% – All lessons'),
            );
        }

        return $events;
    }

    /**
     * @return list<ActivityEvent>
     */
    private function trainingStartedEvents(?Hotel $hotel): array
    {
        $events = [];

        foreach ($this->learnersStamped('training_started_at', $hotel) as $user) {
            if ($user->training_started_at === null) {
                continue;
            }

            $events[] = new ActivityEvent(
                at: $user->training_started_at,
                employee: $user->name,
                type: 'trainingStarted',
                activity: $this->text('Started Training'),
                details: function () use ($user): string {
                    $first = Course::query()
                        ->forLearner($user)
                        ->orderBy('position')
                        ->orderBy('id')
                        ->first()
                        ?->orderedLessons()
                        ->first();

                    return $first === null
                        ? $this->text('First lesson')
                        : $this->text('Lesson 1 – :title', ['title' => $first->title]);
                },
            );
        }

        return $events;
    }

    /**
     * The latest employees whose `$column` timestamp is set (DATA-07).
     *
     * @return Collection<int, User>
     */
    private function learnersStamped(string $column, ?Hotel $hotel): Collection
    {
        return User::query()
            ->select(['id', 'name', 'hotel_id', 'department_id', 'training_started_at', 'training_completed_at'])
            ->employees()
            ->whereNotNull($column)
            ->when($hotel !== null, fn (Builder $query) => $query->where('hotel_id', $hotel?->id))
            ->orderByDesc($column)
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_CANDIDATES)
            ->get();
    }

    // ---------------------------------------------------------------- config

    /**
     * Days without activity before a learner counts as inactive, read from
     * the same key the reminder automation uses (REM-03).
     */
    public static function inactiveDays(): int
    {
        return max(1, (int) config('guesvia.reminders.inactive_days', 5));
    }
}
