<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Models\RoleplayAttempt;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Throwable;

/**
 * Score one finished role-play conversation (RP-08, AIE-01, AIE-04;
 * spec 0003 Part C, G.6).
 *
 * Writes the structured criterion scores, the overall score and the
 * feedback blocks onto the attempt and moves it from `evaluating` to
 * `completed`. The feedback page polls `status` / `ai_status` (PERF-04).
 * On failure the attempt stays `evaluating` with `ai_status = failed` and
 * the reason, so the page can offer a retry rather than lose the transcript.
 */
class EvaluateRoleplayAttempt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $roleplayAttemptId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->roleplayAttemptId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $attempt = RoleplayAttempt::query()->find($this->roleplayAttemptId);

        if ($attempt === null || $attempt->status === RoleplayStatus::Completed) {
            return;
        }

        $scenario = $attempt->scenario;

        if ($scenario === null) {
            $this->fail(new RuntimeException(sprintf('Role-play attempt [%d] has no scenario.', $attempt->id)));

            return;
        }

        $attempt->forceFill(['ai_status' => GenerationStatus::Running])->save();

        // Judged at the learner's measured level; the bar used is stored
        // beside the score (spec 0005 §2.1, §5.2).
        $level = $attempt->user?->english_level;
        $evaluation = $ai->evaluateRoleplay($scenario, $attempt->transcript, $level);

        $meter->record($attempt->user, AiFeature::RoleplayEval, $evaluation->usage);

        $endedAt = $attempt->ended_at ?? Date::now();

        // An admin's override stands through a re-grade (AIE-05; spec 0005
        // §2.5): the AI's fresh verdict becomes the recorded original.
        $overall = $attempt->isScoreOverridden()
            ? ['original_overall_score' => $evaluation->overall]
            : ['overall_score' => $evaluation->overall];

        $attempt->forceFill([
            'criteria_scores' => $evaluation->criteriaScores(),
            ...$overall,
            'graded_level' => $level,
            'feedback' => $evaluation->toFeedbackArray(),
            'status' => RoleplayStatus::Completed,
            'ai_status' => GenerationStatus::Done,
            'failed_reason' => null,
            'ended_at' => $endedAt,
            'duration_ms' => $attempt->duration_ms ?? (int) abs($attempt->started_at->diffInMilliseconds($endedAt)),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        RoleplayAttempt::query()
            ->whereKey($this->roleplayAttemptId)
            ->update([
                'ai_status' => GenerationStatus::Failed->value,
                'failed_reason' => $exception?->getMessage(),
            ]);
    }
}
