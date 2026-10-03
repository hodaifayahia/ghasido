<?php

namespace App\Services\Employees;

use App\Enums\AccountStatus;
use App\Enums\ContentStatus;
use App\Enums\EmployeeBulkAction;
use App\Enums\Role;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Enums\TrainingStatus;
use App\Http\Resources\Employees\EmployeeRowResource;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\ReminderTemplate;
use App\Models\SeatQuota;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Hotels\SqlFragment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The read side of Manage Employees: filters in, page props out (spec 0003
 * Part D; ROLE-02, PROG-02, DATA-07, REP-08).
 *
 * One query lists the page. Every figure a row needs that is not a column
 * (lessons in the learner's curriculum, lessons completed, latest test
 * scores) rides along as a correlated subquery, so a page of ten is one
 * statement rather than forty. The subqueries restate the learner scopes
 * (Lesson::forLearner through Course::forLearner) in SQL; a test pins them
 * to User::progressPercent() so the two can never drift.
 *
 * An Admin or Manager only ever reads their own hotel: the query is scoped to it
 * whatever the filter says, and naming another hotel is refused with a 403
 * by the caller before this runs (SEC-01).
 */
class EmployeeDirectory
{
    public const ALL_HOTELS = 'all-hotels';

    public const ALL_DEPARTMENTS = 'all-departments';

    public const ALL_STATUSES = 'all-statuses';

    /** Rows per page (spec 0003 Part D: "10 per page with the 1 2 3 4 5 … 8 shape"). */
    public const PER_PAGE = 10;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, User $actor): array
    {
        $filters = $this->filtersFrom($request, $actor);

        $page = $this->query($actor, $filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        /** @var list<User> $rows */
        $rows = $page->items();
        $from = (int) ($page->firstItem() ?? 0);

        return [
            'stats' => $this->stats($actor),
            'filters' => [
                'search' => $filters['search'],
                'hotel' => $filters['hotel'] === null ? self::ALL_HOTELS : (string) $filters['hotel'],
                'department' => $filters['department'] === null ? self::ALL_DEPARTMENTS : (string) $filters['department'],
                'status' => $filters['status'] === null ? self::ALL_STATUSES : $filters['status']->value,
                'hotels' => [['value' => self::ALL_HOTELS, 'label' => __('All Hotels')], ...$this->hotelOptions($actor)],
                'departments' => [['value' => self::ALL_DEPARTMENTS, 'label' => __('All Departments')], ...$this->departmentOptions($actor)],
                'statuses' => $this->statusOptions(),
            ],
            'employees' => array_map(
                fn (User $user, int $index): array => (new EmployeeRowResource($user, $from + $index))->resolve($request),
                $rows,
                array_keys($rows),
            ),
            'pagination' => $this->pagination($page),
            'bulkActions' => [
                ['value' => 'select-action', 'label' => __('Select action')],
                ...array_map(
                    static fn (EmployeeBulkAction $action): array => ['value' => $action->value, 'label' => $action->label()],
                    EmployeeBulkAction::cases(),
                ),
            ],
            'createForm' => $this->createForm($actor),
            'reminderTemplates' => ReminderTemplate::query()->active()->orderBy('id')->get()
                ->map(static fn (ReminderTemplate $template): array => ['value' => (string) $template->id, 'label' => $template->name])
                ->values()
                ->all(),
        ];
    }

    /**
     * The filters as typed values. An Admin or Manager's hotel is forced; the actor
     * asking for another hotel is refused (ROLE-02).
     *
     * @return array{search: string, hotel: int|null, department: int|null, status: TrainingStatus|null}
     */
    public function filtersFrom(Request $request, User $actor): array
    {
        $hotel = $this->positiveInt($request->query('hotel'));

        if ($actor->hotel_id !== null && ! $this->seesEveryHotel($actor)) {
            if ($hotel !== null && $hotel !== $actor->hotel_id) {
                abort(403);
            }

            $hotel = $actor->hotel_id;
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'hotel' => $hotel,
            'department' => $this->positiveInt($request->query('department')),
            'status' => TrainingStatus::tryFrom((string) $request->query('status', '')),
        ];
    }

    /**
     * The directory query: employee accounts, scoped, filtered, ordered by
     * name, with every derived figure selected alongside.
     *
     * @param  array{search: string, hotel: int|null, department: int|null, status: TrainingStatus|null}  $filters
     * @return Builder<User>
     */
    public function query(User $actor, array $filters): Builder
    {
        $query = $this->base($actor)
            ->with([
                'hotel' => fn ($hotels) => $hotels->withoutGlobalScopes(),
                'department',
            ])
            ->select('users.*')
            ->selectSub($this->lessonsTotalSql(), 'lessons_total')
            ->selectSub($this->lessonsCompletedSql(), 'lessons_completed_count')
            ->selectSub($this->latestTestPercentSql(TestType::Pre), 'pre_test_percent')
            ->selectSub($this->latestTestPercentSql(TestType::Post), 'post_test_percent')
            ->orderBy('users.name')
            ->orderBy('users.id');

        if ($filters['hotel'] !== null) {
            $query->where('users.hotel_id', $filters['hotel']);
        }

        if ($filters['department'] !== null) {
            $query->where('users.department_id', $filters['department']);
        }

        if ($filters['status'] !== null) {
            $this->applyStatus($query, $filters['status']);
        }

        $this->applySearch($query, $filters['search']);

        return $query;
    }

    /**
     * The six stat cards, from the same derivations the rows use, over the
     * accounts the actor may see (never filtered: they describe the whole).
     *
     * @return list<array{key: string, value: int, label: string, detail?: string}>
     */
    public function stats(User $actor): array
    {
        $count = fn (?TrainingStatus $status): int => $status === null
            ? $this->base($actor)->count()
            : $this->applyStatus($this->base($actor), $status)->count();

        $total = $count(null);
        $completed = $count(TrainingStatus::Completed);
        $inProgress = $count(TrainingStatus::InProgress);
        $notStarted = $count(TrainingStatus::NotStarted);
        $inactive = $count(TrainingStatus::Inactive);
        $started = $completed + $inProgress;

        $percent = static fn (int $part): string => $total === 0
            ? '0.0%'
            : number_format($part / $total * 100, 1).'%';

        return [
            ['key' => 'totalEmployees', 'value' => $total, 'label' => __('Total Employees')],
            ['key' => 'activeAccounts', 'value' => $total - $inactive, 'label' => __('Active Accounts')],
            ['key' => 'inactiveAccounts', 'value' => $inactive, 'label' => __('Inactive Accounts')],
            ['key' => 'startedTraining', 'value' => $started, 'label' => __('Started Training'), 'detail' => $percent($started)],
            ['key' => 'completedTraining', 'value' => $completed, 'label' => __('Completed Training'), 'detail' => $percent($completed)],
            ['key' => 'notStarted', 'value' => $notStarted, 'label' => __('Not Started'), 'detail' => $percent($notStarted)],
        ];
    }

    /**
     * The status pill of one loaded row, from the columns and the selected
     * subqueries: the PHP twin of applyStatus().
     */
    public static function statusOf(User $user): TrainingStatus
    {
        if ($user->status === AccountStatus::Inactive) {
            return TrainingStatus::Inactive;
        }

        $total = (int) $user->getAttribute('lessons_total');
        $completed = (int) $user->getAttribute('lessons_completed_count');

        if ($user->training_completed_at !== null || ($total > 0 && $completed >= $total)) {
            return TrainingStatus::Completed;
        }

        if ($user->training_started_at !== null || $completed > 0) {
            return TrainingStatus::InProgress;
        }

        return TrainingStatus::NotStarted;
    }

    /**
     * Completed lessons over the curriculum, as a whole percentage, from the
     * selected subqueries: what User::progressPercent() answers, without the
     * two queries per row.
     */
    public static function progressOf(User $user): int
    {
        $total = (int) $user->getAttribute('lessons_total');

        if ($total === 0) {
            return 0;
        }

        return (int) round((int) $user->getAttribute('lessons_completed_count') / $total * 100);
    }

    // ---------------------------------------------------------------- queries

    /**
     * Employee accounts the actor may see: every one for the Super Admin,
     * their hotel's for a manager, none for a manager without a hotel.
     *
     * @return Builder<User>
     */
    private function base(User $actor): Builder
    {
        // A deleted (anonymised) account leaves the list (client request
        // 2026-10-02); its answers stay in the reports.
        $query = User::query()->employees()->whereNull('users.removed_at');

        if (! $this->seesEveryHotel($actor)) {
            $query->where('users.hotel_id', $actor->hotel_id ?? 0);
        }

        return $query;
    }

    private function seesEveryHotel(User $actor): bool
    {
        return $actor->hasRole(Role::SuperAdmin->value);
    }

    /**
     * Name, username or email, which is what the search box promises.
     *
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner
                ->where('users.name', 'like', $like)
                ->orWhere('users.username', 'like', $like)
                ->orWhere('users.email', 'like', $like);
        });
    }

    /**
     * The status pill as SQL, so the filter and the stat cards agree with
     * statusOf() to the row (spec 0003 Part D).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function applyStatus(Builder $query, TrainingStatus $status): Builder
    {
        $active = AccountStatus::Active->value;
        $inactive = AccountStatus::Inactive->value;

        $total = $this->lessonsTotalSql();
        $completed = $this->lessonsCompletedSql();
        $totalSql = '('.$total->toSql().')';
        $completedSql = '('.$completed->toSql().')';
        $bindings = [...$total->getBindings(), ...$completed->getBindings()];

        // completed: stamped, or every lesson in the curriculum done.
        $isCompleted = "(users.training_completed_at is not null or ({$totalSql} > 0 and {$completedSql} >= {$totalSql}))";
        $completedBindings = [...$bindings, ...$total->getBindings()];
        // started: stamped, or any lesson done.
        $isStarted = "(users.training_started_at is not null or {$completedSql} > 0)";

        return match ($status) {
            TrainingStatus::Inactive => $query->where('users.status', $inactive),
            TrainingStatus::Completed => $query
                ->where('users.status', $active)
                ->whereRaw(new SqlFragment($isCompleted), $completedBindings),
            TrainingStatus::InProgress => $query
                ->where('users.status', $active)
                ->whereRaw(new SqlFragment("not {$isCompleted}"), $completedBindings)
                ->whereRaw(new SqlFragment($isStarted), $completed->getBindings()),
            TrainingStatus::NotStarted => $query
                ->where('users.status', $active)
                ->whereRaw(new SqlFragment("not {$isStarted}"), $completed->getBindings()),
        };
    }

    /**
     * Published lessons on published courses of the user's department, shared
     * or their hotel's: Lesson::forLearner() as a correlated subquery.
     *
     * @return Builder<Lesson>
     */
    private function lessonsTotalSql(): Builder
    {
        return Lesson::query()
            ->selectRaw('count(*)')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->where('lessons.status', ContentStatus::Published->value)
            ->where('courses.status', ContentStatus::Published->value)
            ->whereColumn('courses.department_id', 'users.department_id')
            ->where(function (Builder $inner): void {
                $inner->whereNull('courses.hotel_id')->orWhereColumn('courses.hotel_id', 'users.hotel_id');
            })
            ->where(function (Builder $level): void {
                // Their level's courses, or courses for every level (client
                // decision 2026-09-30); no level yet = every level.
                $level->whereNull('courses.level')
                    ->orWhereNull('users.english_level')
                    ->orWhereColumn('courses.level', 'users.english_level');
            });
    }

    /**
     * The user's completions that are still in that curriculum.
     *
     * @return Builder<LessonCompletion>
     */
    private function lessonsCompletedSql(): Builder
    {
        return LessonCompletion::query()
            ->selectRaw('count(*)')
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->whereColumn('lesson_completions.user_id', 'users.id')
            ->where('lessons.status', ContentStatus::Published->value)
            ->where('courses.status', ContentStatus::Published->value)
            ->whereColumn('courses.department_id', 'users.department_id')
            ->where(function (Builder $inner): void {
                $inner->whereNull('courses.hotel_id')->orWhereColumn('courses.hotel_id', 'users.hotel_id');
            })
            ->where(function (Builder $level): void {
                // Their level's courses, or courses for every level (client
                // decision 2026-09-30); no level yet = every level.
                $level->whereNull('courses.level')
                    ->orWhereNull('users.english_level')
                    ->orWhereColumn('courses.level', 'users.english_level');
            });
    }

    /**
     * The percentage of the user's latest submitted sitting of this test
     * type, or null (TestAttempt::scoreSummary() for one row, in SQL).
     *
     * @return Builder<TestAttempt>
     */
    private function latestTestPercentSql(TestType $type): Builder
    {
        return TestAttempt::query()
            ->select(DB::raw('round(test_attempts.score * 100.0 / test_attempts.max_score)'))
            ->join('tests', 'tests.id', '=', 'test_attempts.test_id')
            ->whereColumn('test_attempts.user_id', 'users.id')
            ->where('test_attempts.status', TestAttemptStatus::Submitted->value)
            ->where('tests.type', $type->value)
            ->where('test_attempts.max_score', '>', 0)
            ->orderByDesc('test_attempts.submitted_at')
            ->orderByDesc('test_attempts.id')
            ->limit(1);
    }

    // ---------------------------------------------------------------- options

    /**
     * @return list<array{value: string, label: string}>
     */
    private function hotelOptions(User $actor): array
    {
        $hotels = Hotel::query()->withoutGlobalScopes()->notArchived()->orderBy('name');

        if (! $this->seesEveryHotel($actor)) {
            $hotels->whereKey($actor->hotel_id ?? 0);
        }

        return array_values($hotels->get()
            ->map(static fn (Hotel $hotel): array => ['value' => (string) $hotel->id, 'label' => $hotel->name])
            ->all());
    }

    /**
     * The departments the actor's hotels hold seats in, catalogue order.
     *
     * @return list<array{value: string, label: string}>
     */
    private function departmentOptions(User $actor): array
    {
        $departments = Department::query()->orderBy('position')->orderBy('name');

        if ($this->seesEveryHotel($actor)) {
            $departments->whereIn('id', SeatQuota::query()->withoutGlobalScopes()->select('department_id'));
        } else {
            $departments->whereIn('id', SeatQuota::query()->withoutGlobalScopes()->where('hotel_id', $actor->hotel_id ?? 0)->select('department_id'));
        }

        return array_values($departments->get()
            ->map(static fn (Department $department): array => ['value' => (string) $department->id, 'label' => $department->name])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => self::ALL_STATUSES, 'label' => __('All Statuses')],
            ...array_map(
                static fn (TrainingStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                TrainingStatus::cases(),
            ),
        ];
    }

    /**
     * Add New Employee: the hotels the actor may fill, each with the
     * departments it bought seats in (SUB-01), so the department list follows
     * the hotel (spec 0003 Part D).
     *
     * @return array<string, mixed>
     */
    private function createForm(User $actor): array
    {
        $hotels = $this->hotelOptions($actor);
        $hotelIds = array_map(static fn (array $option): int => (int) $option['value'], $hotels);

        /** @var EloquentCollection<int, SeatQuota> $quotas */
        $quotas = SeatQuota::query()
            ->withoutGlobalScopes()
            ->with('department')
            ->whereIn('hotel_id', $hotelIds)
            ->get();

        $byHotel = [];

        foreach ($hotels as $option) {
            $byHotel[$option['value']] = $quotas
                ->where('hotel_id', (int) $option['value'])
                ->sortBy(fn (SeatQuota $quota): string => sprintf('%05d %s', $quota->department->position, $quota->department->name))
                ->map(static fn (SeatQuota $quota): array => [
                    'value' => (string) $quota->department_id,
                    'label' => $quota->department->name,
                ])
                ->values()
                ->all();
        }

        $defaultHotel = $hotels[0]['value'] ?? '';

        return [
            'hotels' => $hotels,
            'departments' => $byHotel[$defaultHotel] ?? [],
            'departmentsByHotel' => (object) $byHotel,
            'statuses' => [
                ['value' => AccountStatus::Active->value, 'label' => __('Active')],
                ['value' => AccountStatus::Inactive->value, 'label' => __('Inactive')],
            ],
            'defaultHotel' => $defaultHotel,
            'defaultDepartment' => $byHotel[$defaultHotel][0]['value'] ?? '',
            'defaultStatus' => AccountStatus::Active->value,
            'allowReminderEmails' => true,
            'passwordLength' => EmployeeService::GENERATED_PASSWORD_LENGTH,
        ];
    }

    // ------------------------------------------------------------- pagination

    /**
     * @param  LengthAwarePaginator<int, User>  $page
     * @return array{from: int, to: int, total: int, currentPage: int, lastPage: int, pages: list<int|string>}
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'from' => (int) ($page->firstItem() ?? 0),
            'to' => (int) ($page->lastItem() ?? 0),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'pages' => self::pageWindow($page->currentPage(), $page->lastPage()),
        ];
    }

    /**
     * Five numbers around the current page, an ellipsis, and the ends: on
     * page 1 of 8 that is `1 2 3 4 5 … 8`, exactly as the mockup draws it.
     *
     * @return list<int|string>
     */
    public static function pageWindow(int $current, int $last): array
    {
        $last = max(1, $last);

        if ($last <= 7) {
            return range(1, $last);
        }

        $start = max(1, min($current - 2, $last - 4));
        $end = min($last, $start + 4);
        $pages = [];

        if ($start > 1) {
            $pages[] = 1;

            if ($start > 2) {
                $pages[] = 'ellipsis';
            }
        }

        foreach (range($start, $end) as $number) {
            $pages[] = $number;
        }

        if ($end < $last) {
            if ($end < $last - 1) {
                $pages[] = 'ellipsis';
            }

            $pages[] = $last;
        }

        return $pages;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
