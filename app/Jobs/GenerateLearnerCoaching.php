<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\AiInsight;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Services\Learning\LearnerCoach;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Write one learner's coaching summary (spec 0005 §3.5).
 *
 * Reads the learner's own figures at run time (so a burst of activity is
 * summed up once), asks the provider for the words, and stores them on the
 * learner's `ai_insights` row, which the Home and Progress cards poll
 * (PERF-04). Metered as `learner_coach` for cost reports (API-03), without
 * spending the learner's AI points: the learner never asked for it.
 */
class GenerateLearnerCoaching implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $userId)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter, LearnerCoach $coach): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $insight = AiInsight::for($user, AiInsight::KIND_LEARNER_COACH);

        if ($insight === null) {
            return;
        }

        $insight->forceFill(['status' => GenerationStatus::Running])->save();

        $context = $coach->context($user);
        $summary = $ai->coachLearner($context, $user->english_level);

        $meter->record($user, AiFeature::LearnerCoach, $summary->usage, chargePoints: false);

        $insight->forceFill([
            'status' => GenerationStatus::Done,
            'payload' => $summary->toArray(),
            'fingerprint' => LearnerCoach::fingerprint($context),
            'failed_reason' => null,
            'generated_at' => Date::now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        AiInsight::for($user, AiInsight::KIND_LEARNER_COACH)?->forceFill([
            'status' => GenerationStatus::Failed,
            'failed_reason' => mb_substr((string) $exception?->getMessage(), 0, 500),
        ])->save();
    }
}
