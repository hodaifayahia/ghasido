<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\ContentStatus;
use App\Enums\DepartmentStatus;
use App\Enums\Role;
use App\Enums\TestType;
use App\Policies\DepartmentPolicy;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * A job function content is scoped to (ORG-02, spec 0002).
 *
 * `hotel_id` null means the shared catalogue; set means a department only one
 * hotel has. There is no ScopedToHotel here on purpose: the global rows belong
 * to nobody, so a tenant scope would hide the whole catalogue. What a manager
 * may see is written once, in scopeVisibleTo(), and the policy answers with a
 * 403 for a row outside it (ROLE-02, SEC-01).
 *
 * `focus` and `status` are the Departments mockup's description line and
 * editorial pill (spec 0003, B.2). `status` is separate from `is_active`, the
 * on/off toggle: a department under review is still switched on, and an
 * archived department (`is_active` false) keeps every row that points at it
 * (DATA-10).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $focus
 * @property DepartmentStatus $status
 * @property int|null $hotel_id
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $courses_count
 * @property-read int|null $lessons_count
 * @property-read int|null $tests_count
 * @property-read int|null $ai_scenarios_count
 * @property-read int|null $hotels_count
 * @property-read int|null $active_employees_count
 * @property-read int|null $published_lessons_count
 * @property-read int|null $published_tests_count
 * @property-read int|null $published_scenarios_count
 */
#[Fillable(['name', 'slug', 'focus', 'status', 'hotel_id', 'position', 'is_active'])]
#[UsePolicy(DepartmentPolicy::class)]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
            'status' => DepartmentStatus::class,
        ];
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return HasMany<SeatQuota, $this> */
    public function seatQuotas(): HasMany
    {
        return $this->hasMany(SeatQuota::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ---------------------------------------------------------------- content
    //
    // Course, Lesson, Test and AiScenario are written by the content and
    // learner lanes (spec 0003, B.5, B.6). They are referenced by name here so
    // the Departments screen can withCount() them; nothing in this model calls
    // them until those models exist.

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return HasManyThrough<Lesson, Course, $this> */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Course::class);
    }

    /** @return HasMany<Test, $this> */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    /** @return HasMany<AiScenario, $this> */
    public function aiScenarios(): HasMany
    {
        return $this->hasMany(AiScenario::class);
    }

    /**
     * Eager load the four content counts the Departments rows show, as
     * constrained counts rather than a query per row (spec 0002, AC-20 shape).
     *
     * @param  Builder<Department>  $query
     */
    public function scopeWithContentCounts(Builder $query): void
    {
        $query->withCount(['courses', 'lessons', 'tests', 'aiScenarios']);
    }

    public function coursesCount(): int
    {
        return $this->courses_count ?? $this->courses()->count();
    }

    public function lessonsCount(): int
    {
        return $this->lessons_count ?? $this->lessons()->count();
    }

    public function testsCount(): int
    {
        return $this->tests_count ?? $this->tests()->count();
    }

    public function scenariosCount(): int
    {
        return $this->ai_scenarios_count ?? $this->aiScenarios()->count();
    }

    // ------------------------------------------------------------- directory

    /**
     * Every figure a directory row shows, in one query for the whole page
     * (spec 0003 Part D; spec 0002, AC-20 shape): hotels holding a seat quota,
     * active learner accounts, the allowed seats across those quotas, and the
     * PUBLISHED lessons, tests and AI scenarios. Draft content is not
     * "ready", so it is not counted (CMS-05).
     *
     * @param  Builder<Department>  $query
     */
    public function scopeWithDirectoryCounts(Builder $query): void
    {
        $query
            ->withCount([
                'seatQuotas as hotels_count',
                'users as active_employees_count' => function (Builder $users): void {
                    $users
                        ->where('status', AccountStatus::Active->value)
                        ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value));
                },
                'lessons as published_lessons_count' => function (Builder $lessons): void {
                    $lessons->where('lessons.status', ContentStatus::Published->value);
                },
                'tests as published_tests_count' => function (Builder $tests): void {
                    $tests->where('status', ContentStatus::Published->value);
                },
                'aiScenarios as published_scenarios_count' => function (Builder $scenarios): void {
                    $scenarios->where('status', ContentStatus::Published->value);
                },
            ])
            ->withSum('seatQuotas as allowed_seats_sum', 'allowed_seats');
    }

    /**
     * Hotels holding a seat quota in this department.
     */
    public function hotelCount(): int
    {
        return $this->hotels_count ?? $this->seatQuotas()->count();
    }

    /**
     * Active learner accounts in this department, across every hotel. By
     * definition this is also the number of seats in use (spec 0002,
     * invariant 9): a seat is an active employee account.
     */
    public function usedSeats(): int
    {
        if ($this->active_employees_count !== null) {
            return $this->active_employees_count;
        }

        return $this->users()
            ->where('status', AccountStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value))
            ->count();
    }

    /**
     * Allowed seats summed over every hotel's quota for this department.
     */
    public function allowedSeats(): int
    {
        $sum = $this->getAttribute('allowed_seats_sum');

        if ($sum !== null) {
            return (int) $sum;
        }

        return (int) $this->seatQuotas()->sum('allowed_seats');
    }

    public function publishedLessonsCount(): int
    {
        return $this->published_lessons_count
            ?? $this->lessons()->where('lessons.status', ContentStatus::Published->value)->count();
    }

    public function publishedTestsCount(): int
    {
        return $this->published_tests_count
            ?? $this->tests()->where('status', ContentStatus::Published->value)->count();
    }

    public function publishedScenariosCount(): int
    {
        return $this->published_scenarios_count
            ?? $this->aiScenarios()->where('status', ContentStatus::Published->value)->count();
    }

    /**
     * Does this department hold a published Pre-test AND a published
     * Post-test (TSTM-03)? Drives the "already paired" sidebar note.
     */
    public function hasPairedTests(): bool
    {
        $types = $this->tests()
            ->where('status', ContentStatus::Published->value)
            ->distinct()
            ->pluck('type')
            ->map(fn ($type): string => $type instanceof TestType ? $type->value : (string) $type)
            ->all();

        return in_array(TestType::Pre->value, $types, true)
            && in_array(TestType::Post->value, $types, true);
    }

    /**
     * Live used counts per hotel for this department, in ONE query, keyed by
     * hotel id. Active employee accounts only (spec 0002, AC-6).
     *
     * @return array<int, int>
     */
    public function usedSeatsByHotel(): array
    {
        /** @var array<int, int> $counts */
        $counts = User::query()
            ->active()
            ->employees()
            ->where('department_id', $this->id)
            ->whereNotNull('hotel_id')
            ->groupBy('hotel_id')
            ->selectRaw('hotel_id, count(*) as aggregate')
            ->pluck('aggregate', 'hotel_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * This department's seat quotas with their hotel and live used count
     * loaded, in hotel name order: two queries for the whole list rather than
     * one per hotel. This is what the sidebar's Hotel Coverage lists.
     *
     * Goes through the SeatQuota tenant scope, so a manager only ever sees
     * their own hotel's row here (ROLE-02).
     *
     * @return Collection<int, SeatQuota>
     */
    public function seatQuotasWithUsage(): Collection
    {
        $used = $this->usedSeatsByHotel();

        $quotas = $this->seatQuotas()
            ->with('hotel')
            ->join('hotels', 'hotels.id', '=', 'seat_quotas.hotel_id')
            ->orderBy('hotels.name')
            ->select('seat_quotas.*')
            ->get();

        foreach ($quotas as $quota) {
            $quota->setAttribute('used_seats_count', $used[$quota->hotel_id] ?? 0);
        }

        return $quotas;
    }

    public function isShared(): bool
    {
        return $this->hotel_id === null;
    }

    /**
     * Whether this row is inside what the user may see (the one-row form of
     * scopeVisibleTo, for the policy).
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        return $this->hotel_id === null
            || ($user->hotel_id !== null && $this->hotel_id === $user->hotel_id);
    }

    // ----------------------------------------------------------------- scopes

    /**
     * Departments that are switched on and editorially active: the ones a
     * learner may be assigned to and content may be published under.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->where('status', DepartmentStatus::Active->value);
    }

    /**
     * The shared catalogue: departments every hotel may draw on.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeGlobalCatalogue(Builder $query): void
    {
        $query->whereNull('hotel_id');
    }

    /**
     * The catalogue this hotel may draw on: the shared rows plus its own.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeAvailableTo(Builder $query, Hotel $hotel): void
    {
        $query->where(function (Builder $inner) use ($hotel): void {
            $inner->whereNull('hotel_id')->orWhere('hotel_id', $hotel->id);
        });
    }

    /**
     * What this user may see on the Departments screen (ROLE-02, spec 0003
     * Part D): everything for the Super Admin; the shared catalogue plus
     * their own hotel's departments for anyone else. A manager with no hotel
     * yet sees the shared catalogue only.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $query->where(function (Builder $inner) use ($user): void {
            $inner->whereNull('hotel_id');

            if ($user->hotel_id !== null) {
                $inner->orWhere('hotel_id', $user->hotel_id);
            }
        });
    }

    /**
     * Search across the name and the focus line, which is what the input's
     * placeholder promises.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner
                ->where('name', 'like', $like)
                ->orWhere('focus', 'like', $like);
        });
    }

    /**
     * Shared rows only, or hotel owned rows only.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeWithScope(Builder $query, string $scope): void
    {
        if ($scope === 'shared') {
            $query->whereNull('hotel_id');
        } elseif ($scope === 'hotel') {
            $query->whereNotNull('hotel_id');
        }
    }

    /**
     * The fixed directory order: catalogue position, then name, then id so
     * paging is stable when two rows share a position.
     *
     * @param  Builder<Department>  $query
     */
    public function scopeDirectoryOrder(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name')->orderBy('id');
    }
}
