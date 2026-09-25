<?php

namespace App\Models;

use Database\Factories\ActivityPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Where an activity appears: a practice block or a test (PRAC-05, WRITE-05,
 * spec 0003 B.5).
 *
 * `overrides` lets one placement change the instruction line without forking
 * the activity, e.g. {"prompt": "..."}; `effectivePrompt()` applies it.
 *
 * @property int $id
 * @property int $activity_id
 * @property string $placeable_type
 * @property int $placeable_id
 * @property int $position
 * @property array<string, mixed>|null $overrides
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['activity_id', 'placeable_type', 'placeable_id', 'position', 'overrides'])]
class ActivityPlacement extends Model
{
    /** @use HasFactory<ActivityPlacementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'overrides' => 'array',
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return MorphTo<Model, $this> */
    public function placeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The instruction line for this placement: the override when set, the
     * activity's own otherwise.
     */
    public function effectivePrompt(): string
    {
        $override = $this->overrides['prompt'] ?? null;

        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        return $this->activity()->firstOrFail()->prompt;
    }

    /**
     * One override by key, or null.
     */
    public function override(string $key): mixed
    {
        return $this->overrides[$key] ?? null;
    }
}
