<?php

namespace App\Models;

use App\Enums\Accent;
use App\Enums\GenerationStatus;
use App\Policies\PronunciationAttemptPolicy;
use Database\Factories\PronunciationAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One pronunciation check of one recording (spec 0006 §5, §6).
 *
 * Research data: never deleted, never soft-deleted (DATA-10). `stt_raw`
 * keeps both listens verbatim ({free, hinted}), `words` the verdict per
 * reference word (PronunciationOutcome), `feedback` the coach's reply.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $hotel_id
 * @property int|null $department_id
 * @property int|null $lesson_id
 * @property int|null $block_id
 * @property int|null $lexicon_item_id
 * @property int|null $item_index
 * @property int|null $word_index
 * @property int|null $voice_recording_id
 * @property string $reference_text
 * @property string|null $sentence_text
 * @property Accent $accent
 * @property int $attempt_no
 * @property GenerationStatus $status
 * @property string|null $failed_reason
 * @property string|null $stt_provider
 * @property string|null $stt_model
 * @property array<string, mixed>|null $stt_raw
 * @property list<array<string, mixed>>|null $words
 * @property list<array<string, mixed>>|null $extras
 * @property int $fillers
 * @property int $long_pauses
 * @property string|null $speed_ratio
 * @property int|null $duration_ms
 * @property string|null $score
 * @property string|null $words_score
 * @property string|null $clarity_score
 * @property string|null $flow_score
 * @property string|null $level
 * @property string|null $scoring_version
 * @property array<string, mixed>|null $feedback
 * @property string|null $feedback_status
 * @property Carbon|null $checked_at
 * @property Carbon|null $coached_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'hotel_id',
    'department_id',
    'lesson_id',
    'block_id',
    'lexicon_item_id',
    'item_index',
    'word_index',
    'voice_recording_id',
    'reference_text',
    'sentence_text',
    'accent',
    'attempt_no',
    'status',
    'duration_ms',
])]
#[UsePolicy(PronunciationAttemptPolicy::class)]
class PronunciationAttempt extends Model
{
    /** @use HasFactory<PronunciationAttemptFactory> */
    use HasFactory;

    /** The coach is waiting to run, or ran. */
    public const FEEDBACK_PENDING = 'pending';

    public const FEEDBACK_DONE = 'done';

    public const FEEDBACK_FAILED = 'failed';

    /** Every word was correct, or nothing was heard: a fixed message, no AI call. */
    public const FEEDBACK_SKIPPED = 'skipped';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accent' => Accent::class,
            'status' => GenerationStatus::class,
            'stt_raw' => 'array',
            'words' => 'array',
            'extras' => 'array',
            'feedback' => 'array',
            'fillers' => 'integer',
            'long_pauses' => 'integer',
            'speed_ratio' => 'decimal:2',
            'attempt_no' => 'integer',
            'item_index' => 'integer',
            'word_index' => 'integer',
            'duration_ms' => 'integer',
            'score' => 'decimal:2',
            'words_score' => 'decimal:2',
            'clarity_score' => 'decimal:2',
            'flow_score' => 'decimal:2',
            'checked_at' => 'datetime',
            'coached_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<Block, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    /** @return BelongsTo<LexiconItem, $this> */
    public function lexiconItem(): BelongsTo
    {
        return $this->belongsTo(LexiconItem::class);
    }

    /** @return BelongsTo<VoiceRecording, $this> */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(VoiceRecording::class, 'voice_recording_id');
    }

    public function isChecked(): bool
    {
        return $this->status->isTerminal();
    }

    /** One word of a sentence, said on its own. */
    public function isWordDrill(): bool
    {
        return $this->sentence_text !== null && $this->word_index !== null;
    }

    /**
     * The text whose guide applies: the sentence, also for a drill of one
     * of its words (the guide is per word inside the sentence's row).
     */
    public function guideText(): string
    {
        return $this->sentence_text ?? $this->reference_text;
    }

    /**
     * Is the page still waiting on anything (the check or the coach)?
     */
    public function isSettled(): bool
    {
        if ($this->status === GenerationStatus::Failed) {
            return true;
        }

        return $this->status === GenerationStatus::Done && $this->feedback_status !== self::FEEDBACK_PENDING;
    }
}
