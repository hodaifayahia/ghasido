<?php

namespace App\Services\Reports;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * The state of the Reports & Export screen, read once from the query string
 * (REP-01, REP-02, spec 0003 Part D).
 *
 * A value object: every tab, chart, stat and export reads the same instance,
 * so a filter can never apply to the table and not to the figures above it.
 * The tenant boundary is settled here too: anyone but the Super Admin is
 * pinned to their own hotel before a query is built (ROLE-02, SEC-01), and
 * `viewerMayUse()` is what the form requests call to turn a foreign `hotel`
 * or `employee` parameter into a 403 rather than an empty page.
 *
 * The date range applies to events (answers, sittings, role-play attempts,
 * lesson completions); the employee population and its per-row figures are
 * state as of now, so "12 / 15 lessons" never shrinks when the range does.
 */
final class ReportFilters
{
    public const ALL_HOTELS = 'all-hotels';

    public const ALL_DEPARTMENTS = 'all-departments';

    public const ALL_EMPLOYEES = 'all-employees';

    public const ALL_ACTIVITIES = 'all-activities';

    public const ALL_STATUSES = 'all';

    public const RANGE_THIS_MONTH = 'this-month';

    public const RANGE_LAST_MONTH = 'last-month';

    public const RANGE_LAST_90_DAYS = 'last-90-days';

    public const RANGE_CUSTOM = 'custom';

    public const ACTIVITY_LESSONS = 'lessons';

    public const ACTIVITY_TESTS = 'tests';

    public const ACTIVITY_ROLEPLAY = 'roleplay';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INACTIVE = 'inactive';

    public const TAB_EMPLOYEES = 'employeeResults';

    public const TAB_ANSWERS = 'detailedAnswers';

    public const TAB_ROLEPLAY = 'roleplayLogs';

    public const TAB_LESSONS = 'lessonProgress';

    public const TAB_COMPARISON = 'comparison';

    public const TAB_DOWNLOADS = 'downloadCenter';

    /** @var list<string> */
    public const TABS = [
        self::TAB_EMPLOYEES,
        self::TAB_ANSWERS,
        self::TAB_ROLEPLAY,
        self::TAB_LESSONS,
        self::TAB_COMPARISON,
        self::TAB_DOWNLOADS,
    ];

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [7, 10, 20];

    public const DATE_FORMAT = 'j M Y';

    /** Days without activity after which an employee counts as "this month" rather than "this week". */
    public const WEEK_DAYS = 7;

    /** Days without activity after which an employee counts as inactive. */
    public const MONTH_DAYS = 30;

    public function __construct(
        public readonly string $range,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $hotelId,
        public readonly ?int $departmentId,
        public readonly ?int $employeeId,
        public readonly string $activityType,
        public readonly string $completionStatus,
        public readonly string $tab,
        public readonly string $search,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?int $detailId,
    ) {}

    public static function fromRequest(Request $request, User $viewer): self
    {
        $range = (string) $request->query('range', self::RANGE_LAST_90_DAYS);
        [$range, $from, $to] = self::resolveRange(
            $range,
            self::stringParam($request, 'from'),
            self::stringParam($request, 'to'),
        );

        $activityType = (string) $request->query('activityType', self::ALL_ACTIVITIES);
        $completionStatus = (string) $request->query('completionStatus', self::ALL_STATUSES);
        $tab = (string) $request->query('tab', self::TAB_EMPLOYEES);
        $perPage = (int) $request->query('per_page', self::PER_PAGE_OPTIONS[0]);

        return new self(
            range: $range,
            from: $from,
            to: $to,
            hotelId: self::scopedHotelId($viewer) ?? self::idParam($request, 'hotel'),
            departmentId: self::idParam($request, 'department'),
            employeeId: self::idParam($request, 'employee'),
            activityType: in_array($activityType, [self::ACTIVITY_LESSONS, self::ACTIVITY_TESTS, self::ACTIVITY_ROLEPLAY], true)
                ? $activityType
                : self::ALL_ACTIVITIES,
            completionStatus: in_array($completionStatus, self::statuses(), true)
                ? $completionStatus
                : self::ALL_STATUSES,
            tab: in_array($tab, self::TABS, true) ? $tab : self::TAB_EMPLOYEES,
            search: trim((string) $request->query('search', '')),
            page: max(1, (int) $request->query('page', 1)),
            perPage: in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0],
            detailId: self::idParam($request, 'detail'),
        );
    }

    /**
     * May this viewer use the parameters of this request?
     *
     * The Super Admin may ask for anything. Anyone else may only name their
     * own hotel and employees of their own hotel; naming another hotel's is
     * a boundary, answered with a 403 by the form request (ROLE-02).
     */
    public static function viewerMayUse(Request $request, User $viewer): bool
    {
        if (self::isSuperAdmin($viewer)) {
            return true;
        }

        $hotelId = self::idParam($request, 'hotel');

        if ($hotelId !== null && $hotelId !== $viewer->hotel_id) {
            return false;
        }

        foreach (['employee', 'detail'] as $key) {
            $userId = self::idParam($request, $key);

            if ($userId === null) {
                continue;
            }

            $inHotel = $viewer->hotel_id !== null && User::query()
                ->whereKey($userId)
                ->where('hotel_id', $viewer->hotel_id)
                ->exists();

            if (! $inHotel) {
                return false;
            }
        }

        return true;
    }

    /**
     * The hotel a viewer is pinned to: null for the Super Admin (everything),
     * the viewer's own hotel otherwise, and 0 for a manager with no hotel
     * yet, which matches nothing rather than everything.
     */
    public static function scopedHotelId(User $viewer): ?int
    {
        if (self::isSuperAdmin($viewer)) {
            return null;
        }

        return $viewer->hotel_id ?? 0;
    }

    public static function isSuperAdmin(User $viewer): bool
    {
        return $viewer->hasRole(Role::SuperAdmin->value);
    }

    // ------------------------------------------------------------------ range

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private static function resolveRange(string $range, ?string $from, ?string $to): array
    {
        $today = CarbonImmutable::instance(Date::today());

        if ($range === self::RANGE_CUSTOM) {
            $start = self::parseDate($from);
            $end = self::parseDate($to);

            if ($start !== null && $end !== null && ! $start->greaterThan($end)) {
                return [self::RANGE_CUSTOM, $start->startOfDay(), $end->endOfDay()];
            }

            $range = self::RANGE_LAST_90_DAYS;
        }

        return match ($range) {
            self::RANGE_THIS_MONTH => [self::RANGE_THIS_MONTH, $today->startOfMonth(), $today->endOfMonth()->endOfDay()],
            self::RANGE_LAST_MONTH => [
                self::RANGE_LAST_MONTH,
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            default => [self::RANGE_LAST_90_DAYS, $today->subDays(89)->startOfDay(), $today->endOfDay()],
        };
    }

    private static function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $date === null ? null : $date;
    }

    /**
     * The four presets with the dates they currently stand for, so the
     * select reads "1 Sep 2026 - 30 Sep 2026" rather than "This month".
     *
     * @return list<array{value: string, label: string}>
     */
    public function rangeOptions(): array
    {
        $options = [];

        foreach ([self::RANGE_THIS_MONTH, self::RANGE_LAST_MONTH, self::RANGE_LAST_90_DAYS] as $preset) {
            [, $from, $to] = self::resolveRange($preset, null, null);
            $options[] = ['value' => $preset, 'label' => self::formatSpan($from, $to)];
        }

        $options[] = [
            'value' => self::RANGE_CUSTOM,
            'label' => $this->range === self::RANGE_CUSTOM
                ? self::formatSpan($this->from, $this->to)
                : __('Custom range'),
        ];

        return $options;
    }

    public function rangeLabel(): string
    {
        return self::formatSpan($this->from, $this->to);
    }

    public static function formatSpan(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->format(self::DATE_FORMAT).' - '.$to->format(self::DATE_FORMAT);
    }

    /**
     * Constrain a query's timestamp column to the range.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function withinRange(Builder $query, string $column): void
    {
        $query->whereBetween($column, [$this->from->toDateTimeString(), $this->to->toDateTimeString()]);
    }

    // -------------------------------------------------------------- activity

    public function includesTests(): bool
    {
        return in_array($this->activityType, [self::ALL_ACTIVITIES, self::ACTIVITY_TESTS], true);
    }

    public function includesLessons(): bool
    {
        return in_array($this->activityType, [self::ALL_ACTIVITIES, self::ACTIVITY_LESSONS], true);
    }

    public function includesRoleplay(): bool
    {
        return in_array($this->activityType, [self::ALL_ACTIVITIES, self::ACTIVITY_ROLEPLAY], true);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::ALL_STATUSES, self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_INACTIVE];
    }

    /**
     * The filters as query parameters: what the page echoes back, what an
     * export audit row records, and what an export link carries.
     *
     * @return array<string, string|int>
     */
    public function toQuery(bool $withPaging = false): array
    {
        $query = [
            'range' => $this->range,
            'hotel' => $this->hotelId ?? self::ALL_HOTELS,
            'department' => $this->departmentId ?? self::ALL_DEPARTMENTS,
            'employee' => $this->employeeId ?? self::ALL_EMPLOYEES,
            'activityType' => $this->activityType,
            'completionStatus' => $this->completionStatus,
        ];

        if ($this->range === self::RANGE_CUSTOM) {
            $query['from'] = $this->from->toDateString();
            $query['to'] = $this->to->toDateString();
        }

        if ($withPaging) {
            $query['tab'] = $this->tab;
            $query['search'] = $this->search;
            $query['page'] = $this->page;
            $query['per_page'] = $this->perPage;
        }

        return $query;
    }

    // ---------------------------------------------------------------- helpers

    private static function idParam(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function stringParam(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
