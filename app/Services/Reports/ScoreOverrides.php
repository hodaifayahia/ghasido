<?php

namespace App\Services\Reports;

use App\Enums\ActivityType;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\EvaluateWrittenAnswer;
use App\Jobs\TranscribeAndEvaluateSpokenAnswer;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An admin replaces or re-grades a score (AIE-05; spec 0005 §2.5).
 *
 * The machine's value is never lost: the first override moves it into the
 * `original_*` column, and restoring puts it back. Who, when and why are
 * stored on the row and written to the audit log (SEC-06). A test answer
 * that counts toward its sitting's total moves the total with it, so the
 * Pre/Post comparison stays true (TEST-06). Re-grading re-queues the same
 * evaluation job the learner's submission used; the job keeps an override
 * in place (spec 0005 §2.5).
 */
final class ScoreOverrides
{
    public const AUDIT_OVERRIDE = 'score.overridden';

    public const AUDIT_RESTORE = 'score.restored';

    public const AUDIT_REEVALUATE = 'score.reevaluation_requested';

    /** A role-play overall score and every AI answer score is out of 100. */
    public const DEFAULT_MAX = 100.0;

    // ------------------------------------------------------------- answers

    public function overrideAnswer(Attempt $attempt, float $score, string $reason, User $actor): void
    {
        $this->assertGraded($attempt);

        DB::transaction(function () use ($attempt, $score, $reason, $actor): void {
            $before = $attempt->score === null ? 0.0 : (float) $attempt->score;

            $attempt->forceFill([
                // The first override keeps the machine's value; a second one
                // replaces only the admin's.
                'original_score' => $attempt->isScoreOverridden() ? $attempt->original_score : $attempt->score,
                'score' => round($score, 2),
                'score_overridden_by' => $actor->id,
                'score_override_reason' => $reason,
                'score_overridden_at' => Date::now(),
            ]);

            AuditLog::record($attempt, self::AUDIT_OVERRIDE, ['reason' => $reason]);
            $attempt->save();

            $this->moveSittingTotal($attempt, round($score, 2) - $before);
        });
    }

    public function restoreAnswer(Attempt $attempt): void
    {
        if (! $attempt->isScoreOverridden()) {
            return;
        }

        DB::transaction(function () use ($attempt): void {
            $before = $attempt->score === null ? 0.0 : (float) $attempt->score;
            $original = $attempt->original_score;

            $attempt->forceFill([
                'score' => $original,
                'original_score' => null,
                'score_overridden_by' => null,
                'score_override_reason' => null,
                'score_overridden_at' => null,
            ]);

            AuditLog::record($attempt, self::AUDIT_RESTORE);
            $attempt->save();

            $this->moveSittingTotal($attempt, ($original === null ? 0.0 : (float) $original) - $before);
        });
    }

    /**
     * Ask the AI to judge a written or spoken answer again.
     *
     * @throws InvalidArgumentException when the answer is not AI-graded or a
     *                                  judgement is already running
     */
    public function reevaluateAnswer(Attempt $attempt): void
    {
        $type = $attempt->activity?->type;

        if (! self::isAiGraded($type)) {
            throw new InvalidArgumentException(__('Only written and spoken answers are graded by the AI.'));
        }

        if ($type === ActivityType::Speaking && $attempt->response_media_id === null) {
            throw new InvalidArgumentException(__('This spoken answer has no recording to judge.'));
        }

        if (self::isRunning($attempt->ai_status)) {
            throw new InvalidArgumentException(__('The AI is already grading this answer.'));
        }

        $attempt->forceFill(['ai_status' => GenerationStatus::Pending]);
        AuditLog::record($attempt, self::AUDIT_REEVALUATE);
        $attempt->save();

        if ($type === ActivityType::Speaking) {
            TranscribeAndEvaluateSpokenAnswer::dispatch($attempt->id);
        } else {
            EvaluateWrittenAnswer::dispatch($attempt->id);
        }
    }

    // ------------------------------------------------------------ role-play

    public function overrideRoleplay(RoleplayAttempt $attempt, int $score, string $reason, User $actor): void
    {
        $this->assertEvaluated($attempt);

        $attempt->forceFill([
            'original_overall_score' => $attempt->isScoreOverridden() ? $attempt->original_overall_score : $attempt->overall_score,
            'overall_score' => $score,
            'score_overridden_by' => $actor->id,
            'score_override_reason' => $reason,
            'score_overridden_at' => Date::now(),
        ]);

        AuditLog::record($attempt, self::AUDIT_OVERRIDE, ['reason' => $reason]);
        $attempt->save();
    }

    public function restoreRoleplay(RoleplayAttempt $attempt): void
    {
        if (! $attempt->isScoreOverridden()) {
            return;
        }

        $attempt->forceFill([
            'overall_score' => $attempt->original_overall_score,
            'original_overall_score' => null,
            'score_overridden_by' => null,
            'score_override_reason' => null,
            'score_overridden_at' => null,
        ]);

        AuditLog::record($attempt, self::AUDIT_RESTORE);
        $attempt->save();
    }

    /**
     * Ask the AI to judge a finished conversation again.
     *
     * @throws InvalidArgumentException while the conversation is still open
     *                                  or already being judged
     */
    public function reevaluateRoleplay(RoleplayAttempt $attempt): void
    {
        if ($attempt->is_preview || $attempt->status->acceptsTurns() || $attempt->status === RoleplayStatus::Abandoned) {
            throw new InvalidArgumentException(__('Only a finished conversation can be graded again.'));
        }

        if (self::isRunning($attempt->ai_status)) {
            throw new InvalidArgumentException(__('The AI is already grading this conversation.'));
        }

        $attempt->forceFill([
            'status' => RoleplayStatus::Evaluating,
            'ai_status' => GenerationStatus::Pending,
        ]);

        AuditLog::record($attempt, self::AUDIT_REEVALUATE);
        $attempt->save();

        EvaluateRoleplayAttempt::dispatch($attempt->id);
    }

    // -------------------------------------------------------------- reading

    public static function isAiGraded(?ActivityType $type): bool
    {
        return $type === ActivityType::Speaking || $type === ActivityType::Writing;
    }

    /**
     * The most an answer may score: its stored maximum, or 100 for an AI
     * graded answer that has none yet.
     */
    public static function maxFor(Attempt $attempt): float
    {
        return $attempt->max_score === null ? self::DEFAULT_MAX : (float) $attempt->max_score;
    }

    // ------------------------------------------------------------ internals

    private static function isRunning(?GenerationStatus $status): bool
    {
        return $status === GenerationStatus::Pending || $status === GenerationStatus::Running;
    }

    private function assertGraded(Attempt $attempt): void
    {
        if ($attempt->submitted_at === null) {
            throw new InvalidArgumentException(__('Only a submitted answer can be scored.'));
        }
    }

    private function assertEvaluated(RoleplayAttempt $attempt): void
    {
        if ($attempt->is_preview || $attempt->status !== RoleplayStatus::Completed) {
            throw new InvalidArgumentException(__('Only a graded conversation can be scored.'));
        }
    }

    /**
     * An auto-graded test answer counts toward its sitting's score and skill
     * breakdown (TestScorer::finish); move both by the same amount. AI-graded
     * answers are outside that total, so they move nothing.
     */
    private function moveSittingTotal(Attempt $attempt, float $delta): void
    {
        if ($delta === 0.0 || $attempt->test_attempt_id === null || self::isAiGraded($attempt->activity?->type)) {
            return;
        }

        $sitting = TestAttempt::query()->lockForUpdate()->find($attempt->test_attempt_id);

        if ($sitting === null || $sitting->score === null) {
            return;
        }

        $breakdown = $sitting->breakdown ?? [];
        $skill = $attempt->activity->skill_label ?? 'General';

        if (is_array($breakdown[$skill] ?? null) && is_numeric($breakdown[$skill]['score'] ?? null)) {
            $breakdown[$skill]['score'] = round((float) $breakdown[$skill]['score'] + $delta, 2);
        }

        $sitting->forceFill([
            'score' => round((float) $sitting->score + $delta, 2),
            'breakdown' => $breakdown,
        ])->save();
    }
}
