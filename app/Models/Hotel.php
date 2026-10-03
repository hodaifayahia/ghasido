<?php

namespace App\Models;

use App\Concerns\ScopedToHotel;
use App\Enums\AccountStatus;
use App\Enums\CapacityState;
use App\Enums\HotelAccessState;
use App\Enums\HotelStatus;
use App\Enums\Role;
use App\Policies\HotelPolicy;
use App\Services\Hotels\SqlFragment;
use Carbon\CarbonInterface;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

/**
 * A hotel: the tenant everything else hangs off (spec 0002).
 *
 * Read the model methods here rather than writing a query in a controller
 * (spec 0002, AC-20). Nothing time based or count based is stored, so every
 * figure the page shows is computed on the way out.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $city
 * @property string $manager_name
 * @property string $manager_email
 * @property int $subscription_plan_id
 * @property HotelAccessState $access_state
 * @property CarbonInterface|null $contract_starts_on
 * @property CarbonInterface|null $contract_ends_on
 * @property CarbonInterface|null $paused_at
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $removed_at when the hotel was deleted (it leaves every list)
 * @property CarbonInterface|null $request_read_at when the Super Admin saw the new request in the bell
 * @property int|null $approved_by
 * @property CarbonInterface|null $archived_at
 * @property string|null $archive_reason
 * @property array<string, mixed>|null $settings
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read int|null $active_employees_count
 * @property-read int|null $seat_quotas_count
 */
#[Fillable([
    'name',
    'slug',
    'city',
    'manager_name',
    'manager_email',
    'subscription_plan_id',
    'access_state',
    'contract_starts_on',
    'contract_ends_on',
])]
#[UsePolicy(HotelPolicy::class)]
class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use HasFactory, ScopedToHotel;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_plan_id' => 'integer',
            'access_state' => HotelAccessState::class,
            'contract_starts_on' => 'date',
            'contract_ends_on' => 'date',
            'request_read_at' => 'datetime',
            'removed_at' => 'datetime',
            'paused_at' => 'datetime',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * Resolve a route parameter WITHOUT the tenant scope.
     *
     * With the scope in place a manager requesting another hotel's record
     * would get a 404 before any policy ran. Spec 0002 (AC-10) requires a
     * 403, so the boundary is visible as a boundary: the row is found here
     * and the policy, which every controller action calls, refuses it.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQueryWithoutScope('hotel')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    // ---------------------------------------------------------------- relations

    /** @return HasMany<SeatQuota, $this> */
    public function seatQuotas(): HasMany
    {
        return $this->hasMany(SeatQuota::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The manual payments sent for this hotel from the checkout (client
     * request 2026-09-27).
     *
     * @return HasMany<PaymentSubmission, $this>
     */
    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    /** @return HasMany<HotelAiPointTopUp, $this> */
    public function aiPointTopUps(): HasMany
    {
        return $this->hasMany(HotelAiPointTopUp::class);
    }

    /** @return HasMany<HotelAiPointTopUpRequest, $this> */
    public function aiPointTopUpRequests(): HasMany
    {
        return $this->hasMany(HotelAiPointTopUpRequest::class);
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * Archived hotels drop out of every stat card and the default directory
     * view, and can be filtered back in (spec 0002, AC-3).
     *
     * @param  Builder<Hotel>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->where('access_state', '!=', HotelAccessState::Archived);
    }

    /**
     * Eager load the two counts every row needs, as constrained counts rather
     * than a query per row. The seeded portfolio is far too small to reveal an
     * N plus 1, so this is the shape from the start (spec 0002, foundation
     * child, Watch out for).
     *
     * @param  Builder<Hotel>  $query
     */
    public function scopeWithSeatCounts(Builder $query): void
    {
        $query
            ->withCount([
                'users as active_employees_count' => function (Builder $users): void {
                    $users
                        ->where('status', AccountStatus::Active->value)
                        ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value));
                },
                'seatQuotas',
            ])
            ->withSum('seatQuotas as allowed_seats_sum', 'allowed_seats');
    }

    /**
     * Search across hotel name, manager name and city, which is what the
     * input's placeholder promises (spec 0002, AC-11).
     *
     * @param  Builder<Hotel>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner
                ->where('name', 'like', $like)
                ->orWhere('manager_name', 'like', $like)
                ->orWhere('city', 'like', $like);
        });
    }

    /**
     * Filter on the DISPLAYED status, which for an active hotel is derived
     * from the contract dates against the configured window. This is the one
     * place expiring and ended become SQL, so the stat card, the row pill and
     * the filter can never disagree (spec 0002, AC-2, AC-4, AC-11).
     *
     * @param  Builder<Hotel>  $query
     */
    public function scopeWithStatus(Builder $query, HotelStatus $status): void
    {
        $today = Date::today()->toDateString();
        $horizon = Date::today()->addDays(self::expiringWithinDays())->toDateString();

        match ($status) {
            HotelStatus::Pending => $query->where('access_state', HotelAccessState::Pending),
            HotelStatus::Paused => $query->where('access_state', HotelAccessState::Paused),
            HotelStatus::Archived => $query->where('access_state', HotelAccessState::Archived),
            HotelStatus::Ended => $query
                ->where('access_state', HotelAccessState::Active)
                ->whereDate('contract_ends_on', '<', $today),
            HotelStatus::Expiring => $query
                ->where('access_state', HotelAccessState::Active)
                ->whereDate('contract_ends_on', '>=', $today)
                ->whereDate('contract_ends_on', '<=', $horizon),
            HotelStatus::Active => $query
                ->where('access_state', HotelAccessState::Active)
                ->where(function (Builder $inner) use ($horizon): void {
                    $inner
                        ->whereNull('contract_ends_on')
                        ->orWhereDate('contract_ends_on', '>', $horizon);
                }),
        };
    }

    /**
     * Filter on capacity, which compares a live count of active employee
     * accounts against the summed quota. Both sides are correlated
     * subqueries, so this composes with search, status and paging without a
     * GROUP BY (spec 0002, AC-11, Consequences).
     *
     * The rules are CapacityState::compare() in SQL: a hotel with no quotas
     * and no staff reads as Available, never Full.
     *
     * @param  Builder<Hotel>  $query
     */
    public function scopeWithCapacity(Builder $query, CapacityState $state): void
    {
        $used = User::query()
            ->selectRaw('count(*)')
            ->whereColumn('users.hotel_id', 'hotels.id')
            ->where('users.status', AccountStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value));

        $usedSql = '('.$used->toSql().')';
        $bindings = $used->getBindings();
        $allowedSql = '(select coalesce(sum(seat_quotas.allowed_seats), 0) from seat_quotas where seat_quotas.hotel_id = hotels.id)';

        match ($state) {
            CapacityState::Over => $query->whereRaw(new SqlFragment("{$usedSql} > {$allowedSql}"), $bindings),
            CapacityState::Full => $query->whereRaw(new SqlFragment("{$usedSql} = {$allowedSql} and {$allowedSql} > 0"), $bindings),
            CapacityState::Available => $query->whereRaw(
                new SqlFragment("({$usedSql} < {$allowedSql}) or ({$usedSql} = 0 and {$allowedSql} = 0)"),
                [...$bindings, ...$bindings],
            ),
        };
    }

    /**
     * The one directory order: name ascending with id as a tiebreaker, so
     * paging is deterministic and the `#` column and the auto selected first
     * row stay stable across identical requests (spec 0002, AC-11, AC-18).
     *
     * @param  Builder<Hotel>  $query
     */
    public function scopeDirectoryOrder(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }

    // ------------------------------------------------------------- aggregates

    /**
     * Total Hotels: every hotel that is not archived (spec 0002, AC-3).
     *
     * A model method rather than a query in the controller (AC-20). Subject to
     * ScopedToHotel, so a manager would only ever count their own.
     */
    public static function totalInPortfolio(): int
    {
        return static::query()->notArchived()->count();
    }

    /**
     * The five stat cards and the two counts the AC-3 identity needs, all
     * from the same derivation the rows use (spec 0002, Value sourcing).
     *
     * Pending + Active + Expiring + Paused + Ended = Total, always, because the
     * status scopes partition the non archived rows.
     *
     * @return array{total: int, pending: int, active: int, expiring: int, paused: int, ended: int, usedSeats: int, allowedSeats: int}
     */
    public static function portfolioStats(): array
    {
        $count = fn (HotelStatus $status): int => static::query()->withStatus($status)->count();

        return [
            'total' => static::totalInPortfolio(),
            'pending' => $count(HotelStatus::Pending),
            'active' => $count(HotelStatus::Active),
            'expiring' => $count(HotelStatus::Expiring),
            'paused' => $count(HotelStatus::Paused),
            'ended' => $count(HotelStatus::Ended),
            'usedSeats' => User::query()
                ->active()
                ->employees()
                ->whereHas('hotel', fn (Builder $hotels) => $hotels->notArchived())
                ->count(),
            'allowedSeats' => (int) SeatQuota::query()
                ->whereHas('hotel', fn (Builder $hotels) => $hotels->notArchived())
                ->sum('allowed_seats'),
        ];
    }

    // ------------------------------------------------------------ seat counts

    /**
     * Active employee accounts in this hotel (spec 0002, AC-6).
     *
     * Counted live, never stored. Uses the eager loaded count when the query
     * asked for it, so a directory page is one query rather than fifteen.
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
     * The seats this hotel is allowed across all of its departments.
     */
    public function allowedSeats(): int
    {
        $sum = $this->getAttribute('allowed_seats_sum');

        if ($sum !== null) {
            return (int) $sum;
        }

        return (int) $this->seatQuotas()->sum('allowed_seats');
    }

    /**
     * How many departments this hotel has, which is how many seat quota rows
     * it holds. This is why one hotel shows 7 and another 4 (spec 0002, AC-8).
     */
    public function departmentCount(): int
    {
        return $this->seat_quotas_count ?? $this->seatQuotas()->count();
    }

    public function capacityState(): CapacityState
    {
        return CapacityState::compare($this->usedSeats(), $this->allowedSeats());
    }

    /**
     * Live used counts per department for this hotel, in ONE query, keyed by
     * department id. Active employee accounts only (spec 0002, AC-6).
     *
     * @return array<int, int>
     */
    public function usedSeatsByDepartment(): array
    {
        /** @var array<int, int> $counts */
        $counts = User::query()
            ->active()
            ->employees()
            ->where('hotel_id', $this->id)
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->selectRaw('department_id, count(*) as aggregate')
            ->pluck('aggregate', 'department_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * This hotel's seat quotas with their department and live used count
     * loaded, in catalogue order: two queries for the whole list rather than
     * one per department (spec 0002, directory child, step 6).
     *
     * @return Collection<int, SeatQuota>
     */
    public function seatQuotasWithUsage(): Collection
    {
        $used = $this->usedSeatsByDepartment();

        $quotas = $this->seatQuotas()
            ->with('department')
            ->join('departments', 'departments.id', '=', 'seat_quotas.department_id')
            ->orderBy('departments.position')
            ->orderBy('departments.name')
            ->select('seat_quotas.*')
            ->get();

        foreach ($quotas as $quota) {
            $quota->setAttribute('used_seats_count', $used[$quota->department_id] ?? 0);
        }

        return $quotas;
    }

    /**
     * Every department this hotel may hold seats in (the shared catalogue
     * plus its own), each with its current quota row, if any, and its live
     * used count. This is what the Manage seats dialog lists (spec 0002,
     * contracts and seats child, step 9).
     *
     * @return Collection<int, Department>
     */
    public function seatCatalogueWithUsage(): Collection
    {
        $used = $this->usedSeatsByDepartment();

        /** @var array<int, int> $allowed */
        $allowed = $this->seatQuotas()
            ->pluck('allowed_seats', 'department_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $departments = Department::query()
            ->availableTo($this)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        foreach ($departments as $department) {
            $department->setAttribute('used_seats_count', $used[$department->id] ?? 0);
            $department->setAttribute('allowed_seats', $allowed[$department->id] ?? null);
        }

        return $departments;
    }

    // --------------------------------------------------------------- contract

    /**
     * Whole calendar days between today and the contract end, signed.
     *
     * Both sides go through startOfDay() in the app timezone, which is the one
     * rounding rule this spec uses everywhere (spec 0002, invariant 8). A
     * contract that has ended gives a negative number; the page renders that
     * as Ended rather than as a count.
     *
     * Null when the hotel is paused (the clock is frozen) or when no contract
     * has been set yet.
     */
    public function daysRemaining(): ?int
    {
        if ($this->access_state === HotelAccessState::Paused) {
            return null;
        }

        if ($this->contract_ends_on === null) {
            return null;
        }

        return (int) Date::now()->startOfDay()->diffInDays(
            $this->contract_ends_on->startOfDay(),
            false,
        );
    }

    /**
     * Days remaining expressed in weeks, rounded up, for the renewal alert
     * sentence (spec 0002, Value sourcing, alerts[0]).
     */
    public function weeksRemaining(): ?int
    {
        $days = $this->daysRemaining();

        return $days === null ? null : (int) ceil(max(0, $days) / 7);
    }

    /**
     * The status the page displays (spec 0002, State transitions).
     *
     * Pending, paused and archived come straight from the stored state.
     * Everything else is the contract dates against the configured window, so
     * expiring and ended are never written down anywhere.
     */
    public function derivedStatus(): HotelStatus
    {
        $state = $this->access_state;

        if ($state !== HotelAccessState::Active) {
            return HotelStatus::from($state->value);
        }

        $daysRemaining = $this->daysRemaining();

        if ($daysRemaining === null) {
            return HotelStatus::Active;
        }

        if ($daysRemaining < 0) {
            return HotelStatus::Ended;
        }

        return $daysRemaining <= self::expiringWithinDays()
            ? HotelStatus::Expiring
            : HotelStatus::Active;
    }

    /**
     * Whether anyone attached to this hotel may sign in (spec 0002, AC-15).
     */
    public function allowsAccess(): bool
    {
        return $this->access_state->allowsAccess()
            && $this->derivedStatus() !== HotelStatus::Ended;
    }

    /**
     * The message a blocked user sees at login, naming which situation
     * applies: pending, paused, archived, or ended (spec 0002, AC-15).
     *
     * The stored state answers for the first three; an active hotel only
     * reaches here when its contract has ended, which is the enum's Active
     * message.
     */
    public function blockedMessage(): string
    {
        return $this->access_state->blockedMessage();
    }

    /**
     * How many days before a contract ends counts as expiring soon.
     *
     * Read from configuration in exactly one place, so the stat card, the row
     * pill and the status filter can never disagree (spec 0002, AC-4).
     */
    public static function expiringWithinDays(): int
    {
        return (int) config('guesvia.hotels.expiring_within_days', 30);
    }
}
