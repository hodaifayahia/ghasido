<?php

namespace App\Models;

use Database\Factories\VoiceRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An employee's uploaded voice recording (TEST-07, DATA-02, RESP-05,
 * spec 0003 B.6).
 *
 * The audio is a private `media_assets` row, reachable only through the
 * authorizing media route (PRIV-04). This row says what it was recorded for
 * through the `recordable` morph. Write once: `created_at` only.
 *
 * @property int $id
 * @property int $user_id
 * @property string $recordable_type
 * @property int $recordable_id
 * @property int $media_asset_id
 * @property int|null $duration_ms
 * @property string|null $transcript
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'recordable_type',
    'recordable_id',
    'media_asset_id',
    'duration_ms',
    'transcript',
])]
class VoiceRecording extends Model
{
    /** @use HasFactory<VoiceRecordingFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * Whole seconds, from the millisecond figure that is stored.
     */
    public function durationSeconds(): ?int
    {
        return $this->duration_ms === null ? null : intdiv($this->duration_ms, 1000);
    }
}
