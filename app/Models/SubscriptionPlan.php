<?php

namespace App\Models;

use App\Enums\PlanAudience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable subscription tier (SUB-01..03, AIL-01), priced in DZD for
 * Algeria and in USD for international customers (client decision
 * 2026-09-26). Hotel plans are sized by employee seats; individual plans
 * give one learner a monthly AI point allowance (`points_per_employee`) and
 * have no seats (client request 2026-09-27).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property PlanAudience $audience
 * @property int $employee_limit
 * @property int $price_dzd
 * @property float $price_usd
 * @property int $extra_points_price_dzd price of 1,000 extra AI points
 * @property float $extra_points_price_usd
 * @property int $extra_seat_price_dzd price of one extra employee seat per month
 * @property float $extra_seat_price_usd
 * @property int $points_per_employee
 * @property int $bonus_points_per_employee
 * @property int $voice_points_per_10_minutes
 * @property int $ai_action_points
 * @property bool $is_active
 * @property-read int|null $individual_subscriptions_count
 */
#[Fillable([
    'name',
    'slug',
    'audience',
    'employee_limit',
    'price_dzd',
    'price_usd',
    'extra_points_price_dzd',
    'extra_points_price_usd',
    'extra_seat_price_dzd',
    'extra_seat_price_usd',
    'points_per_employee',
    'bonus_points_per_employee',
    'voice_points_per_10_minutes',
    'ai_action_points',
    'is_active',
])]
class SubscriptionPlan extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'audience' => PlanAudience::class,
            'employee_limit' => 'integer',
            'price_dzd' => 'integer',
            'price_usd' => 'float',
            'extra_points_price_dzd' => 'integer',
            'extra_points_price_usd' => 'float',
            'extra_seat_price_dzd' => 'integer',
            'extra_seat_price_usd' => 'float',
            'points_per_employee' => 'integer',
            'bonus_points_per_employee' => 'integer',
            'voice_points_per_10_minutes' => 'integer',
            'ai_action_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<IndividualSubscription, $this> */
    public function individualSubscriptions(): HasMany
    {
        return $this->hasMany(IndividualSubscription::class);
    }

    /** @return HasMany<Hotel, $this> */
    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    /** @param Builder<SubscriptionPlan> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeForHotels(Builder $query): void
    {
        $query->where('audience', PlanAudience::Hotel->value);
    }

    /** @param Builder<self> $query */
    public function scopeForIndividuals(Builder $query): void
    {
        $query->where('audience', PlanAudience::Individual->value);
    }

    public function isIndividual(): bool
    {
        return $this->audience === PlanAudience::Individual;
    }

    public function pointsPool(): int
    {
        return $this->employee_limit * ($this->points_per_employee + $this->bonus_points_per_employee);
    }
}
