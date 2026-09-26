<?php

namespace App\Models;

use App\Casts\JsonInOrder;
use App\Enums\EnglishLevel;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Policies\RoleplayAttemptPolicy;
use Database\Factories\RoleplayAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * One complete AI role-play conversation (RP-05, RP-06, RP-11, DATA-05,
 * spec 0003 B.6).
 *
 * The transcript is stored turn by turn, on every attempt, with the attempt
 * number, criterion scores and feedback blocks, so it exports to long format
 * (AIE-04). A preview run by an admin is flagged and never counts against an
 * employee or reaches an export (RP-13).
 *
 * @property int $id
 * @property int $user_id
 * @property int $ai_scenario_id
 * @property int|null $lesson_id
 * @property int|null $block_id
 * @property int $attempt_no
 * @property RoleplayStatus $status
 * @property list<array{role: string, text: string, audio_media_id?: int|null, seq?: int, at: string}> $transcript
 * @property bool $pending_reply
 * @property array<string, int>|null $criteria_scores
 * @property int|null $overall_score
 * @property int|null $original_overall_score
 * @property int|null $score_overridden_by
 * @property string|null $score_override_reason
 * @property Carbon|null $score_overridden_at
 * @property EnglishLevel|null $graded_level
 * @property array<string, mixed>|null $feedback
 * @property GenerationStatus|null $ai_status
 * @property string|null $failed_reason
 * @property int|null $duration_ms
 * @property int $ai_points_charged
 * @property bool $is_preview
 * @property string $channel
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'ai_scenario_id',
    'lesson_id',
    'block_id',
    'attempt_no',
    'status',
    'transcript',
    'pending_reply',
    'criteria_scores',
    'overall_score',
    'original_overall_score',
    'score_overridden_by',
    'score_override_reason',
    'score_overridden_at',
    'graded_level',
    'feedback',
    'ai_status',
    'failed_reason',
    'duration_ms',
    'ai_points_charged',
    'is_preview',
    'channel',
    'started_at',
    'ended_at',
])]
#[UsePolicy(RoleplayAttemptPolicy::class)]
class RoleplayAttempt extends Model
{
    /** @use HasFactory<RoleplayAttemptFactory> */
    use HasFactory;

    public const ROLE_GUEST = 'guest';

    public const ROLE_EMPLOYEE = 'employee';

    /** A chat conversation (typed turns, optional voice notes). */
    public const CHANNEL_TEXT = 'text';

    /** A live Deepgram Voice Agent call (spec 0004). */
    public const CHANNEL_VOICE_CALL = 'voice_call';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'status' => RoleplayStatus::class,
            'transcript' => 'array',
            'pending_reply' => 'boolean',
            // The evaluator's criteria order, kept on MySQL too.
            'criteria_scores' => JsonInOrder::class.':-,pronunciation,grammar,vocabulary,fluency,politeness',
            'overall_score' => 'integer',
            'original_overall_score' => 'integer',
            'score_overridden_at' => 'datetime',
            'graded_level' => EnglishLevel::class,
            'feedback' => 'array',
            'ai_status' => GenerationStatus::class,
            'duration_ms' => 'integer',
            'ai_points_charged' => 'integer',
            'is_preview' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AiScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(AiScenario::class, 'ai_scenario_id');
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

    // ----------------------------------------------------------------- scopes

    /**
     * Real attempts only: what counts against `attempts_allowed` and what the
     * exports read (RP-05, RP-13).
     *
     * @param  Builder<RoleplayAttempt>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where('is_preview', false);
    }

    // ------------------------------------------------------------- transcript

    /**
     * Append one turn and persist it, so a message is never held only in
     * memory between the request and the reply job (PROG-01, RP-11).
     *
     * @param  string  $role  RoleplayAttempt::ROLE_GUEST or ROLE_EMPLOYEE
     */
    public function appendTurn(string $role, string $text, ?int $audioMediaId = null): self
    {
        $turn = [
            'role' => $role,
            'text' => $text,
            'at' => Date::now()->toIso8601String(),
        ];

        if ($audioMediaId !== null) {
            $turn['audio_media_id'] = $audioMediaId;
        }

        $turns = $this->transcript ?? [];
        $turns[] = $turn;

        $this->transcript = $turns;
        $this->save();

        return $this;
    }

    /**
     * Has an admin replaced the AI's overall score (AIE-05)?
     */
    public function isScoreOverridden(): bool
    {
        return $this->score_overridden_by !== null;
    }

    public function turnsCount(): int
    {
        return count($this->transcript ?? []);
    }

    /**
     * How many times the employee has spoken. The scenario's turn bounds
     * (`min_turns`, `max_turns`) count these, not the guest's lines.
     */
    public function employeeTurnsCount(): int
    {
        return count(array_filter(
            $this->transcript ?? [],
            static fn (array $turn): bool => $turn['role'] === self::ROLE_EMPLOYEE,
        ));
    }

    /**
     * Over from the learner's side: ended, evaluating, completed or abandoned.
     * No further turn is accepted.
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function isEvaluated(): bool
    {
        return $this->status === RoleplayStatus::Completed;
    }
}
