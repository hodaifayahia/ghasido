<?php

namespace App\Models;

use App\Enums\AiFeature;
use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One metered call to an AI, TTS or STT provider (AIL-04, API-03,
 * spec 0003 B.6).
 *
 * Written by App\Services\Ai\UsageMeter on every real call. Limits are
 * counted live from these rows, never cached on the user (AIL-01..03).
 * Write once: `created_at` only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $hotel_id
 * @property AiFeature $feature
 * @property string $provider
 * @property string|null $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $points_charged
 * @property string $cost_estimate
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'hotel_id',
    'feature',
    'provider',
    'model',
    'prompt_tokens',
    'completion_tokens',
    'points_charged',
    'cost_estimate',
    'occurred_at',
])]
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'points_charged' => 'integer',
            'cost_estimate' => 'decimal:6',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Calls made since the start of today, in the app timezone: the window
     * the daily limits are counted over (AIL-01, AIL-02).
     *
     * @param  Builder<AiUsage>  $query
     */
    public function scopeToday(Builder $query): void
    {
        $query->where('occurred_at', '>=', now()->startOfDay());
    }

    /**
     * @param  Builder<AiUsage>  $query
     */
    public function scopeForFeature(Builder $query, AiFeature $feature): void
    {
        $query->where('feature', $feature->value);
    }

    public function totalTokens(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }
}
