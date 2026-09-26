<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable hotel subscription tier (SUB-01..03, AIL-01), priced in DZD
 * for Algeria and in USD for international customers (client decision
 * 2026-09-26).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $employee_limit
 * @property int $price_dzd
 * @property float $price_usd
 * @property int $points_per_employee
 * @property int $bonus_points_per_employee
 * @property int $voice_points_per_10_minutes
 * @property int $ai_action_points
 * @property bool $is_active
 */
#[Fillable([
    'name',
    'slug',
    'employee_limit',
    'price_dzd',
    'price_usd',
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
            'employee_limit' => 'integer',
            'price_dzd' => 'integer',
            'price_usd' => 'float',
            'points_per_employee' => 'integer',
            'bonus_points_per_employee' => 'integer',
            'voice_points_per_10_minutes' => 'integer',
            'ai_action_points' => 'integer',
            'is_active' => 'boolean',
        ];
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

    public function pointsPool(): int
    {
        return $this->employee_limit * ($this->points_per_employee + $this->bonus_points_per_employee);
    }
}
