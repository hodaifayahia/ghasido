<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\RoleplayAttempt;
use App\Services\Ai\UsageMeter;
use App\Services\Audio\AudioLibrary;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Produce the guest's next line in a role-play and append it to the stored
 * transcript (RP-03, RP-04, RP-11; spec 0003 Part C).
 *
 * `pending_reply` is what the chat page polls (PERF-04): true while this
 * runs, false once the line is stored or the job has given up. The limit
 * check happened in the controller before dispatch (AIL-03); this job only
 * meters what was actually spent (AIL-04).
 *
 * Unique per attempt, so a retry or a double submit can never produce two
 * guest lines for one employee turn.
 */
class GenerateRoleplayReply implements ShouldBeUnique, ShouldQueue
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

    public function handle(AiProvider $ai, UsageMeter $meter, AudioLibrary $audio): void
    {
        $attempt = RoleplayAttempt::query()->find($this->roleplayAttemptId);

        if ($attempt === null) {
            return;
        }

        if (! $attempt->status->acceptsTurns()) {
            // Ended or abandoned while queued: nothing left to say.
            $attempt->forceFill(['pending_reply' => false])->save();

            return;
        }

        $scenario = $attempt->scenario;

        if ($scenario === null) {
            $this->fail(new RuntimeException(sprintf('Role-play attempt [%d] has no scenario.', $attempt->id)));

            return;
        }

        $attempt->forceFill([
            'pending_reply' => true,
            'ai_status' => GenerationStatus::Running,
        ])->save();

        $reply = $ai->roleplayReply($scenario, $attempt->transcript);

        $meter->record($attempt->user, AiFeature::RoleplayTurn, $reply->usage);

        $attempt->appendTurn(RoleplayAttempt::ROLE_GUEST, $reply->text);

        // The guest's line is part of the same reusable stored-audio pipeline
        // as lesson copy. A TTS outage must not lose the AI reply (TTS-01,
        // TTS-02, CTRL-05, RP-03).
        try {
            $audio->ensureBoth($reply->text);
        } catch (Throwable) {
            // The transcript is the source of truth; playback can be
            // regenerated from the admin audio controls later.
        }

        $attempt->forceFill([
            'pending_reply' => false,
            'ai_status' => GenerationStatus::Done,
            'failed_reason' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        RoleplayAttempt::query()
            ->whereKey($this->roleplayAttemptId)
            ->update([
                'pending_reply' => false,
                'ai_status' => GenerationStatus::Failed->value,
                'failed_reason' => $exception?->getMessage(),
            ]);
    }
}
