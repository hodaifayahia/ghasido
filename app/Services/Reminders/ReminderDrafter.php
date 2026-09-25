<?php

namespace App\Services\Reminders;

use App\Enums\GenerationStatus;
use App\Jobs\GenerateReminderDraft;
use App\Models\AiInsight;
use App\Models\User;

/**
 * "Draft with AI" in the reminder template dialog (spec 0005 §4.2).
 *
 * One pending draft per admin, kept in `ai_insights` (kind
 * `reminder_draft`, the admin as subject): the dialog asks for one, a queued
 * job writes it (no AI call in the request; PERF-04) and the dialog polls
 * until it can drop the subject and body into the fields. Nothing is saved
 * as a template until the admin presses Save (GEN-03).
 */
final class ReminderDrafter
{
    public const KIND = 'reminder_draft';

    /** @var list<string> */
    public const TONES = ['friendly', 'encouraging', 'formal'];

    public function request(User $actor, string $purpose, string $tone): AiInsight
    {
        $draft = AiInsight::for($actor, self::KIND) ?? new AiInsight([
            'subject_type' => $actor->getMorphClass(),
            'subject_id' => $actor->id,
            'kind' => self::KIND,
            'hotel_id' => $actor->hotel_id,
        ]);

        $draft->forceFill([
            'status' => GenerationStatus::Pending,
            'payload' => ['request' => ['purpose' => trim($purpose), 'tone' => $tone]],
            'failed_reason' => null,
            'generated_at' => null,
        ])->save();

        GenerateReminderDraft::dispatch($draft->id);

        return $draft->refresh();
    }

    /**
     * @return array{status: string, subject: string|null, body: string|null, failedReason: string|null}
     */
    public function state(User $actor): array
    {
        $draft = AiInsight::for($actor, self::KIND);
        $result = is_array($draft?->payload['draft'] ?? null) ? $draft->payload['draft'] : [];
        $failed = __('The draft could not be written. Try again in a moment.');

        return [
            'status' => $draft === null ? 'empty' : $draft->status->value,
            'subject' => is_string($result['subject'] ?? null) ? $result['subject'] : null,
            'body' => is_string($result['body'] ?? null) ? $result['body'] : null,
            'failedReason' => $draft?->status === GenerationStatus::Failed && is_string($failed) ? $failed : null,
        ];
    }
}
