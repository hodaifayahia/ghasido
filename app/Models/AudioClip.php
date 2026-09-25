<?php

namespace App\Models;

use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use Database\Factories\AudioClipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One generated audio file for one sentence at one speed (CTRL-05, TTS-01,
 * TTS-02, spec 0003 B.4).
 *
 * Playback reads `media_asset_id`; it never synthesises. The row exists
 * before the file does (`pending`), so a page can already show the speaker
 * button and the job fills the file in.
 *
 * @property int $id
 * @property string $text
 * @property string $text_hash
 * @property string $voice
 * @property AudioSpeed $speed
 * @property int|null $media_asset_id
 * @property GenerationStatus $status
 * @property string|null $failed_reason
 * @property string|null $provider
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'text',
    'text_hash',
    'voice',
    'speed',
    'media_asset_id',
    'status',
    'failed_reason',
    'provider',
    'generated_at',
])]
class AudioClip extends Model
{
    /** @use HasFactory<AudioClipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed' => AudioSpeed::class,
            'status' => GenerationStatus::class,
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The hash is derived, never typed: whoever creates a clip by hand
        // still gets the canonical key the unique index expects.
        static::saving(function (AudioClip $clip): void {
            $clip->text_hash = self::hashFor($clip->text);
        });
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * The canonical key for a sentence (spec 0003 B.4): sha256 of the trimmed,
     * whitespace-collapsed text, so "How can  I help you?" and
     * "How can I help you? " are one clip.
     */
    public static function hashFor(string $text): string
    {
        return hash('sha256', self::normalise($text));
    }

    /**
     * The text as the hash sees it.
     */
    public static function normalise(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($collapsed);
    }

    /**
     * @param  Builder<AudioClip>  $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->where('status', GenerationStatus::Done);
    }

    /**
     * @param  Builder<AudioClip>  $query
     */
    public function scopeForVoice(Builder $query, string $voice): void
    {
        $query->where('voice', $voice);
    }

    /**
     * Only generated audio from the active provider may be played. Uploaded
     * audio is provider-independent and remains available (TTS-03, API-04).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveProvider(Builder $query, string $provider): void
    {
        $query->where(function (Builder $scope) use ($provider): void {
            $scope->where('provider', $provider)
                ->orWhere('provider', 'upload');
        });
    }

    public function isDone(): bool
    {
        return $this->status === GenerationStatus::Done && $this->media_asset_id !== null;
    }

    /**
     * The playable URL, or null while the file is not there yet.
     */
    public function url(): ?string
    {
        if (! $this->isDone()) {
            return null;
        }

        return $this->mediaAsset?->url();
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => GenerationStatus::Running,
            'failed_reason' => null,
        ])->save();
    }

    public function markDone(MediaAsset $asset, string $provider): void
    {
        $this->forceFill([
            'media_asset_id' => $asset->id,
            'status' => GenerationStatus::Done,
            'failed_reason' => null,
            'provider' => $provider,
            'generated_at' => now(),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => GenerationStatus::Failed,
            'failed_reason' => mb_substr($reason, 0, 2000),
        ])->save();
    }
}
