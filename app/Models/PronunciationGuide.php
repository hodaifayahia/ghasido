<?php

namespace App\Models;

use App\Enums\Accent;
use App\Enums\GenerationStatus;
use Database\Factories\PronunciationGuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What the platform knows about how one text sounds in one accent (spec
 * 0006 §4): the Qwen-drafted guide and the calibration measured on our own
 * reference audio.
 *
 * `words` entries: {word, ipa, syllables, sounds_like, tip, traps: [{heard_as,
 * sound, tip}], homophones: [..]}. `calibration`: {voice, words: [{key,
 * confidence, heard}], duration_ms, provider, model, measured_at}.
 *
 * @property int $id
 * @property string $text
 * @property string $text_hash
 * @property Accent $accent
 * @property GenerationStatus $status
 * @property string|null $failed_reason
 * @property string $source
 * @property string|null $ipa
 * @property list<array<string, mixed>>|null $words
 * @property list<string>|null $tips
 * @property array<string, mixed>|null $calibration
 * @property string|null $provider
 * @property string|null $model
 * @property Carbon|null $generated_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'text',
    'text_hash',
    'accent',
    'status',
    'failed_reason',
    'source',
    'ipa',
    'words',
    'tips',
    'calibration',
    'provider',
    'model',
    'generated_at',
    'reviewed_by',
    'reviewed_at',
])]
class PronunciationGuide extends Model
{
    /** @use HasFactory<PronunciationGuideFactory> */
    use HasFactory;

    public const SOURCE_AI = 'ai';

    public const SOURCE_EDITED = 'edited';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accent' => Accent::class,
            'status' => GenerationStatus::class,
            'words' => 'array',
            'tips' => 'array',
            'calibration' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Derived, never typed: the same canonical key the audio clips use.
        static::saving(function (PronunciationGuide $guide): void {
            $guide->text = AudioClip::normalise($guide->text);
            $guide->text_hash = AudioClip::hashFor($guide->text);
        });
    }

    public function isDone(): bool
    {
        return $this->status === GenerationStatus::Done;
    }

    /**
     * Was the calibration measured on this voice's reference audio?
     */
    public function isCalibratedFor(string $voice): bool
    {
        return is_array($this->calibration) && ($this->calibration['voice'] ?? null) === $voice;
    }
}
