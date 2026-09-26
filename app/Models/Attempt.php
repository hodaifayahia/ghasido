<?php

namespace App\Models;

use App\Casts\JsonInOrder;
use App\Enums\EnglishLevel;
use App\Enums\GenerationStatus;
use App\Policies\AttemptPolicy;
use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One answer to one activity, in a lesson or in a test (TEST-06, DATA-01,
 * PRAC-04, spec 0003 B.6).
 *
 * The research row. `raw_answer` is always written; `activity_version_id`
 * pins the exact question text answered (TEST-09, DATA-11); `time_taken_ms`
 * is recorded whether or not a timer ran (TIME-04, DATA-08). Written answers
 * are kept verbatim with the AI evaluation on the same row (WRITE-04,
 * TEST-08); spoken answers point at the stored recording (TEST-07).
 *
 * An admin may override the AI score: `original_score` keeps the machine's
 * value and `score_overridden_by` who changed it (AIE-05).
 *
 * @property int $id
 * @property int $user_id
 * @property int $activity_id
 * @property int $activity_version_id
 * @property int|null $placement_id
 * @property int|null $test_attempt_id
 * @property int|null $lesson_id
 * @property int|null $block_id
 * @property int $attempt_no
 * @property array<string, mixed>|null $raw_answer
 * @property array<int, mixed>|null $answer_history
 * @property int|null $response_media_id
 * @property string|null $transcript
 * @property bool|null $is_correct
 * @property string|null $score
 * @property string|null $max_score
 * @property array<string, mixed>|null $ai_feedback
 * @property GenerationStatus|null $ai_status
 * @property string|null $ai_failed_reason
 * @property int|null $score_overridden_by
 * @property string|null $original_score
 * @property string|null $score_override_reason
 * @property Carbon|null $score_overridden_at
 * @property EnglishLevel|null $graded_level
 * @property Carbon $started_at
 * @property Carbon|null $submitted_at
 * @property int|null $time_taken_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'activity_id',
    'activity_version_id',
    'placement_id',
    'test_attempt_id',
    'lesson_id',
    'block_id',
    'attempt_no',
    'raw_answer',
    'answer_history',
    'response_media_id',
    'transcript',
    'is_correct',
    'score',
    'max_score',
    'ai_feedback',
    'ai_status',
    'ai_failed_reason',
    'score_overridden_by',
    'original_score',
    'score_override_reason',
    'score_overridden_at',
    'graded_level',
    'started_at',
    'submitted_at',
    'time_taken_ms',
])]
#[UsePolicy(AttemptPolicy::class)]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'raw_answer' => 'array',
            'answer_history' => 'array',
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            // Criteria in their defined order on MySQL too (writing and
            // speaking share one order: the union of both lists).
            'ai_feedback' => JsonInOrder::class.':criteria,task_completion,accuracy,vocabulary,politeness,clarity',
            'ai_status' => GenerationStatus::class,
            'original_score' => 'decimal:2',
            'score_overridden_at' => 'datetime',
            'graded_level' => EnglishLevel::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'time_taken_ms' => 'integer',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<ActivityVersion, $this> */
    public function activityVersion(): BelongsTo
    {
        return $this->belongsTo(ActivityVersion::class);
    }

    /** @return BelongsTo<ActivityPlacement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(ActivityPlacement::class, 'placement_id');
    }

    /** @return BelongsTo<TestAttempt, $this> */
    public function testAttempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class);
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

    /** @return BelongsTo<MediaAsset, $this> */
    public function responseMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'response_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function scoreOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'score_overridden_by');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * Answers given inside a test sitting, as opposed to lesson practice.
     *
     * @param  Builder<Attempt>  $query
     */
    public function scopeInTests(Builder $query): void
    {
        $query->whereNotNull('test_attempt_id');
    }

    /**
     * @param  Builder<Attempt>  $query
     */
    public function scopePractice(Builder $query): void
    {
        $query->whereNull('test_attempt_id');
    }

    // ---------------------------------------------------------------- reading

    public function isTestAnswer(): bool
    {
        return $this->test_attempt_id !== null;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Was the AI's score replaced by a person (AIE-05)?
     */
    public function isScoreOverridden(): bool
    {
        return $this->score_overridden_by !== null;
    }

    /**
     * The bar an AI evaluator judges this answer against (spec 0005 §5.2).
     *
     * Lesson practice is pitched at the learner's measured level, so the
     * feedback is realistic for them. A Pre- or Post-test answer is judged on
     * the one fixed scale for everyone: the level moves after every sitting
     * and the study compares the two sittings (TEST-02, TSTM-03). The bar
     * used is written to `graded_level` beside the score.
     */
    public function gradingLevel(): ?EnglishLevel
    {
        return $this->isTestAnswer() ? null : $this->user?->english_level;
    }

    /**
     * Whole seconds the learner spent, from the millisecond figure that is
     * always recorded (TIME-04).
     */
    public function timeTakenSeconds(): ?int
    {
        return $this->time_taken_ms === null ? null : intdiv($this->time_taken_ms, 1000);
    }
}
