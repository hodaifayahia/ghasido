<?php

namespace App\Jobs;

use App\Enums\ReminderStatus;
use App\Mail\ReminderMail;
use App\Models\Reminder;
use App\Services\Mail\MailDelivery;
use App\Services\Reminders\ReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one queued email reminder (REM-01, REM-06).
 *
 * Idempotent: only a `queued` row is sent, so a retried or duplicated job
 * never mails twice. Consent and the address are checked again here, so a
 * consent revoked while the mail waited in the queue still wins (PRIV-02).
 *
 * A `scheduled` row is released first (ReminderService::release), which is
 * how a send with a future moment reaches the employee: the job is queued
 * with that delay. Run early, it leaves the row scheduled for the daily pass.
 */
class SendReminderEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * A reminder deleted from the log while its mail waited in the queue is
     * cancelled, not a failure: the job is dropped quietly (REM-06).
     */
    public bool $deleteWhenMissingModels = true;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Sent at once, on the sync connection, because Settings → Email says
     * "Send emails immediately" (client request 2026-09-29): the host may
     * have no queue worker. A delayed reminder still waits: handle() leaves
     * a row that is not due yet for the daily pass.
     */
    public bool $immediate = false;

    public function __construct(public Reminder $reminder)
    {
        if (app(MailDelivery::class)->immediate()) {
            $this->immediate = true;
            $this->onConnection('sync');
        }
    }

    public function handle(ReminderService $reminders): void
    {
        $reminder = $this->reminder->fresh(['user']);

        if ($reminder === null) {
            return;
        }

        if ($reminder->status === ReminderStatus::Scheduled) {
            // Not due yet, or released into a fresh queued job: either way
            // this one is done.
            $reminders->release($reminder);

            return;
        }

        if ($reminder->status !== ReminderStatus::Queued) {
            return;
        }

        $user = $reminder->user;

        if ($user === null) {
            $reminder->markFailed('recipient_missing');

            return;
        }

        $blockedReason = $reminders->emailBlockReason($user);

        if ($blockedReason !== null) {
            $reminder->markBlocked($blockedReason);

            return;
        }

        $mail = Mail::to($user->email, $user->name)->locale($user->locale ?? 'en');

        if (! $this->immediate) {
            $sent = $mail->send(new ReminderMail($reminder));
            $reminder->markSent($sent?->getMessageId());

            return;
        }

        // Sent inside the request that asked for it: a mail server failure
        // marks this reminder failed (the Messages log shows it) instead of
        // breaking a send to the whole team.
        try {
            $sent = $mail->send(new ReminderMail($reminder));
        } catch (Throwable $exception) {
            Log::warning('A reminder email could not be sent.', [
                'reminder' => $reminder->id,
                'error' => MailDelivery::redact($exception->getMessage()),
            ]);
            $reminder->markFailed(MailDelivery::explain($exception));

            return;
        }

        $reminder->markSent($sent?->getMessageId());
    }

    /**
     * Every retry is spent: leave the row in a state the Messages log shows.
     */
    public function failed(?Throwable $exception): void
    {
        $this->reminder->markFailed($exception?->getMessage() ?? 'unknown');
    }
}
