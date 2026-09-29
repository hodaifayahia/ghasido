<?php

namespace App\Models;

use Database\Factories\VoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded line of the AI guest in the fast voice engine (spec 0009).
 *
 * The guest's lines are voiced once and kept; on the next call the AI sees
 * the reusable lines of the scenario and may pick one instead of writing and
 * voicing a new one, which makes the reply instant and free (TTS-02,
 * AIL-04). `uses` counts every time the line was spoken, `reuses` the times
 * it was picked from the bank rather than newly generated.
 *
 * @property int $id
 * @property int|null $ai_scenario_id
 * @property string $voice
 * @property string $text
 * @property string $text_hash
 * @property int|null $audio_clip_id
 * @property bool $reusable
 * @property int $uses
 * @property int $reuses
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ai_scenario_id',
    'voice',
    'text',
    'text_hash',
    'audio_clip_id',
    'reusable',
    'uses',
    'reuses',
    'last_used_at',
])]
class VoiceLine extends Model
{
    /** @use HasFactory<VoiceLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reusable' => 'boolean',
            'uses' => 'integer',
            'reuses' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Same canonical key as the audio clip, so the two always agree.
        static::saving(function (VoiceLine $line): void {
            $line->text = AudioClip::normalise($line->text);
            $line->text_hash = AudioClip::hashFor($line->text);
        });
    }

    /** @return BelongsTo<AiScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(AiScenario::class, 'ai_scenario_id');
    }

    /** @return BelongsTo<AudioClip, $this> */
    public function audioClip(): BelongsTo
    {
        return $this->belongsTo(AudioClip::class);
    }

    /**
     * Lines with a playable recording.
     *
     * @param  Builder<VoiceLine>  $query
     */
    public function scopePlayable(Builder $query): void
    {
        $query->whereHas('audioClip', fn (Builder $clip) => $clip->done());
    }

    public function url(): ?string
    {
        return $this->audioClip?->url();
    }
}
