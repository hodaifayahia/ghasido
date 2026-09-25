<?php

namespace App\Services\Departments;

use App\Enums\ContentStatus;
use App\Enums\DepartmentStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The read side of the Departments screen: filters in, page props out
 * (spec 0003 Part D; spec 0002, AC-20 shape).
 *
 * Composes the model's scopes (visibility, search, scope, status, the fixed
 * directory order, the eager loaded counts) with the paginator, then hands
 * each row to the presenter. The controller only calls this; it writes no
 * query.
 */
class DepartmentDirectory
{
    public const ALL_SCOPES = 'all-scopes';

    public const ALL_STATUSES = 'all-statuses';

    public const STATUS_ARCHIVED = 'archived';

    public function __construct(private readonly DepartmentPresenter $presenter) {}

    /**
     * @return array{stats: list<array<string, mixed>>, filters: array<string, mixed>, departments: list<array<string, mixed>>, pagination: array<string, mixed>, overview: array<string, mixed>|null, hotelOptions: list<array{value: string, label: string}>}
     */
    public function build(Request $request, User $user): array
    {
        $search = trim((string) $request->query('search', ''));
        $scope = (string) $request->query('scope', self::ALL_SCOPES);
        $status = (string) $request->query('status', self::ALL_STATUSES);
        $selected = (int) $request->query('department', 0);

        $page = $this->query($user, $search, $scope, $status)
            ->paginate(self::perPage())
            ->withQueryString();

        /** @var list<Department> $rows */
        $rows = $page->items();
        $from = (int) ($page->firstItem() ?? 0);

        $overview = $this->select($user, $rows, $selected);

        return [
            'stats' => $this->stats($user),
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'status' => $status,
                'scopes' => $this->scopeOptions(),
                'statuses' => $this->statusOptions(),
            ],
            'departments' => array_map(
                fn (Department $department, int $index): array => $this->presenter->row($department, $from + $index),
                $rows,
                array_keys($rows),
            ),
            'pagination' => $this->pagination($page),
            'overview' => $overview === null ? null : $this->presenter->overview($overview),
            'hotelOptions' => $this->hotelOptions($user),
        ];
    }

    /**
     * @return Builder<Department>
     */
    private function query(User $user, string $search, string $scope, string $status): Builder
    {
        $query = Department::query()
            ->visibleTo($user)
            ->withDirectoryCounts()
            ->directoryOrder()
            ->search($search)
            ->withScope($scope);

        // Archived departments appear in no default list and can be filtered
        // back in, like archived hotels (spec 0002, AC-3).
        if ($status === self::STATUS_ARCHIVED) {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);

            $editorial = DepartmentStatus::tryFrom($status);

            if ($editorial !== null) {
                $query->where('status', $editorial->value);
            }
        }

        return $query;
    }

    /**
     * The sidebar opens on the first row of the current page and swaps when
     * View is clicked. A `department` parameter that is not on this page
     * (right after a create, say) is still honoured when the row exists and
     * the user may see it, so the person sees what they just did.
     *
     * @param  list<Department>  $rows
     */
    private function select(User $user, array $rows, int $selected): ?Department
    {
        if ($selected > 0) {
            foreach ($rows as $row) {
                if ($row->id === $selected) {
                    return $row;
                }
            }

            $department = Department::query()
                ->visibleTo($user)
                ->withDirectoryCounts()
                ->find($selected);

            if ($department !== null) {
                return $department;
            }
        }

        return $rows[0] ?? null;
    }

    /**
     * The five stat cards, all read through the user's visibility so a
     * manager's figures describe their own catalogue (spec 0003 Part D).
     *
     * @return list<array{key: string, value: int, label: string, detail?: string}>
     */
    private function stats(User $user): array
    {
        $live = fn (): Builder => Department::query()->visibleTo($user)->where('is_active', true);

        $published = static fn (Builder $query): Builder => $query->where('status', ContentStatus::Published->value);

        $employees = User::query()->active()->employees()->whereNotNull('department_id');

        if (! $user->hasRole(Role::SuperAdmin->value)) {
            // A manager's employee figure is their own hotel's (ROLE-02).
            $employees->where('hotel_id', $user->hotel_id ?? 0);
        }

        return [
            [
                'key' => 'totalDepartments',
                'value' => $live()->count(),
                'label' => __('Total Departments'),
            ],
            [
                'key' => 'activeDepartments',
                'value' => $live()->where('status', DepartmentStatus::Active->value)->count(),
                'label' => __('Active Departments'),
            ],
            [
                'key' => 'sharedTemplates',
                'value' => $live()->whereNull('hotel_id')->count(),
                'label' => __('Shared Templates'),
                'detail' => __('Across multiple hotels'),
            ],
            [
                'key' => 'assignedEmployees',
                'value' => $employees->count(),
                'label' => __('Assigned Employees'),
            ],
            [
                'key' => 'contentReady',
                'value' => $live()
                    ->whereHas('courses', $published)
                    ->whereHas('tests', $published)
                    ->whereHas('aiScenarios', $published)
                    ->count(),
                'label' => __('Content Ready'),
                'detail' => __('Lessons, tests and AI aligned'),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function scopeOptions(): array
    {
        return [
            ['value' => self::ALL_SCOPES, 'label' => __('All Scopes')],
            ['value' => 'shared', 'label' => __('Shared Across Hotels')],
            ['value' => 'hotel', 'label' => __('Hotel Specific')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => self::ALL_STATUSES, 'label' => __('All Statuses')],
            ['value' => DepartmentStatus::Active->value, 'label' => __('Active')],
            ['value' => DepartmentStatus::Review->value, 'label' => __('In Review')],
            ['value' => DepartmentStatus::Draft->value, 'label' => __('Draft')],
            ['value' => self::STATUS_ARCHIVED, 'label' => __('Archived')],
        ];
    }

    /**
     * The hotels the Add Department form may scope a department to: every
     * non archived hotel for the Super Admin, the manager's own otherwise
     * (the Hotel tenant scope already narrows the query for them).
     *
     * @return list<array{value: string, label: string}>
     */
    private function hotelOptions(User $user): array
    {
        if (! $user->can('create', Department::class)) {
            return [];
        }

        $options = [];

        foreach (Hotel::query()->notArchived()->orderBy('name')->get(['id', 'name']) as $hotel) {
            $options[] = ['value' => (string) $hotel->id, 'label' => $hotel->name];
        }

        return $options;
    }

    /**
     * @param  LengthAwarePaginator<int, Department>  $page
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
            'pages' => $this->pageWindow($page->currentPage(), $page->lastPage()),
        ];
    }

    /**
     * 1 … current-1 current current+1 … last, with the ends always present.
     *
     * @return list<int|string>
     */
    private function pageWindow(int $current, int $last): array
    {
        if ($last <= 7) {
            return range(1, max(1, $last));
        }

        $pages = [1];

        if ($current > 3) {
            $pages[] = 'ellipsis';
        }

        foreach (range(max(2, $current - 1), min($last - 1, $current + 1)) as $page) {
            $pages[] = $page;
        }

        if ($current < $last - 2) {
            $pages[] = 'ellipsis';
        }

        $pages[] = $last;

        return $pages;
    }

    public static function perPage(): int
    {
        return max(1, (int) config('guesvia.departments.per_page', 7));
    }
}
