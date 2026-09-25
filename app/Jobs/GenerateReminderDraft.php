<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\AiInsight;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Write one AI reminder draft (spec 0005 §4.2): the subject and body land
 * on the admin's `reminder_draft` row, which the template dialog polls. A
 * draft only; the admin edits it and saves the template (GEN-03).
 */
class GenerateReminderDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10];

    public function __construct(public readonly int $insightId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $draft = AiInsight::query()->find($this->insightId);
        $request = is_array($draft?->payload['request'] ?? null) ? $draft->payload['request'] : null;

        if ($draft === null || $request === null) {
            return;
        }

        $draft->forceFill(['status' => GenerationStatus::Running])->save();

        $result = $ai->draftReminder(
            (string) ($request['purpose'] ?? ''),
            (string) ($request['tone'] ?? 'friendly'),
            ReminderTemplate::VARIABLES,
        );

        $meter->record(User::query()->find($draft->subject_id), AiFeature::ReminderDraft, $result->usage, chargePoints: false);

        $draft->forceFill([
            'status' => GenerationStatus::Done,
            'payload' => ['request' => $request, 'draft' => $result->toArray()],
            'failed_reason' => null,
            'generated_at' => Date::now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        AiInsight::query()->whereKey($this->insightId)->update([
            'status' => GenerationStatus::Failed->value,
            'failed_reason' => mb_substr((string) $exception?->getMessage(), 0, 500),
        ]);
    }
}
