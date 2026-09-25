<?php

namespace App\Services\Reminders;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Jobs\SendReminderEmail;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The one place a reminder is written and sent (REM-01..REM-06).
 *
 * Manual sends from the Messages screen and the automation runner both come
 * through here, so the consent rule is enforced once: an email reminder to
 * somebody without recorded consent, or without an address, becomes a
 * `blocked` row with its reason and never reaches the queue (REM-05). In-app
 * reminders have no such gate and are `sent` the moment they exist.
 *
 * A send may carry a moment in the future. The rows are then written as
 * `scheduled` with `scheduled_for`, the mail job is queued with that delay,
 * and the daily automation pass releases anything a worker missed
 * (App\Jobs\RunAutomationRules -> releaseDue()). Consent is checked when the
 * row is written and again when it is released (PRIV-02).
 *
 * Every send writes one audit row against the template with the recipient
 * count (SEC-06, spec 0003 Part A rule 2).
 */
final class ReminderService
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * Send one template to many employees on one channel, now or at a moment
     * in the future.
     *
     * @param  Collection<int, User>  $recipients
     * @param  User|null  $sender  the admin or manager pressing Send; null for the runner
     * @param  AutomationRule|null  $rule  the rule behind an automatic send
     * @param  CarbonInterface|null  $scheduledFor  leave null to send at once
     * @return Collection<int, Reminder> one row per recipient, in the order given
     */
    public function send(
        Collection $recipients,
        ReminderTemplate $template,
        ReminderChannel $channel,
        ?User $sender,
        ?AutomationRule $rule = null,
        ?CarbonInterface $scheduledFor = null,
    ): Collection {
        if ($recipients->isEmpty()) {
            return new Collection;
        }

        if ($recipients instanceof EloquentCollection) {
            $recipients->loadMissing(['hotel', 'department']);
        }

        $reminders = [];

        foreach ($recipients as $user) {
            $reminders[] = $this->sendToOne($user, $template, $channel, $sender, $rule, $scheduledFor);
        }

        $reminders = new Collection($reminders);

        AuditLog::record($template, $scheduledFor === null ? 'reminders.sent' : 'reminders.scheduled', [
            'channel' => $channel->value,
            'recipients' => $reminders->count(),
            'queued' => $this->countWithStatus($reminders, ReminderStatus::Queued),
            'sent' => $this->countWithStatus($reminders, ReminderStatus::Sent),
            'scheduled' => $this->countWithStatus($reminders, ReminderStatus::Scheduled),
            'blocked' => $this->countWithStatus($reminders, ReminderStatus::Blocked),
            'scheduled_for' => $scheduledFor?->toIso8601String(),
            'sent_by' => $sender?->id,
            'automation_rule_id' => $rule?->id,
        ]);

        return $reminders;
    }

    /**
     * How a batch went, for the toast: "Sent to N, blocked M (no consent)".
     *
     * @param  Collection<int, Reminder>  $reminders
     * @return array{total: int, delivered: int, scheduled: int, blocked: int}
     */
    public function summarise(Collection $reminders): array
    {
        return [
            'total' => $reminders->count(),
            'delivered' => $this->countWithStatus($reminders, ReminderStatus::Queued)
                + $this->countWithStatus($reminders, ReminderStatus::Sent),
            'scheduled' => $this->countWithStatus($reminders, ReminderStatus::Scheduled),
            'blocked' => $this->countWithStatus($reminders, ReminderStatus::Blocked),
        ];
    }

    /**
     * Release every scheduled reminder whose time has come: in-app rows are
     * sent on the spot, email rows are queued for the mail job. Returns how
     * many were released.
     */
    public function releaseDue(): int
    {
        $released = 0;

        Reminder::query()
            ->where('status', ReminderStatus::Scheduled->value)
            ->where('scheduled_for', '<=', Date::now())
            ->orderBy('id')
            ->with('user')
            ->chunkById(200, function (EloquentCollection $reminders) use (&$released): void {
                foreach ($reminders as $reminder) {
                    if ($this->release($reminder)) {
                        $released++;
                    }
                }
            });

        return $released;
    }

    /**
     * Move one scheduled reminder on. False when it is not due yet, so a
     * delayed job that ran early (a sync queue, say) leaves it alone.
     */
    public function release(Reminder $reminder): bool
    {
        if ($reminder->status !== ReminderStatus::Scheduled) {
            return false;
        }

        if ($reminder->scheduled_for === null || $reminder->scheduled_for->isFuture()) {
            return false;
        }

        if ($reminder->channel === ReminderChannel::InApp) {
            $reminder->markSent();

            return true;
        }

        $user = $reminder->user;

        if ($user === null) {
            $reminder->markFailed('recipient_missing');

            return true;
        }

        $blockedReason = $this->emailBlockReason($user);

        if ($blockedReason !== null) {
            $reminder->markBlocked($blockedReason);

            return true;
        }

        $reminder->forceFill(['status' => ReminderStatus::Queued])->save();

        SendReminderEmail::dispatch($reminder);

        return true;
    }

    /**
     * Why an email to this employee must not go out, or null when it may.
     *
     * Checked when the reminder is written and again when the job runs, so a
     * consent revoked while the mail was queued still wins (PRIV-02).
     */
    public function emailBlockReason(User $user): ?string
    {
        if (blank($user->email)) {
            return Reminder::BLOCKED_NO_EMAIL;
        }

        if ($user->email_consent_at === null) {
            return Reminder::BLOCKED_NO_CONSENT;
        }

        return null;
    }

    private function sendToOne(
        User $user,
        ReminderTemplate $template,
        ReminderChannel $channel,
        ?User $sender,
        ?AutomationRule $rule,
        ?CarbonInterface $scheduledFor,
    ): Reminder {
        $reminder = new Reminder([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'automation_rule_id' => $rule?->id,
            'channel' => $channel,
            'subject' => $this->renderer->render($template->subject, $user),
            'body' => $this->renderer->render($template->body, $user),
            'sent_by' => $sender?->id,
        ]);

        if ($channel === ReminderChannel::Email) {
            $blockedReason = $this->emailBlockReason($user);

            if ($blockedReason !== null) {
                $reminder->status = ReminderStatus::Blocked;
                $reminder->blocked_reason = $blockedReason;
                $reminder->save();

                return $reminder;
            }
        }

        if ($scheduledFor !== null) {
            $reminder->forceFill([
                'status' => ReminderStatus::Scheduled,
                'scheduled_for' => $scheduledFor,
            ])->save();

            // The worker releases it on time; the daily pass is the fallback.
            SendReminderEmail::dispatch($reminder)->delay($scheduledFor);

            return $reminder;
        }

        if ($channel === ReminderChannel::InApp) {
            $reminder->forceFill(['status' => ReminderStatus::Sent, 'sent_at' => now()])->save();

            return $reminder;
        }

        $reminder->status = ReminderStatus::Queued;
        $reminder->save();

        SendReminderEmail::dispatch($reminder);

        return $reminder;
    }

    /**
     * @param  Collection<int, Reminder>  $reminders
     */
    private function countWithStatus(Collection $reminders, ReminderStatus $status): int
    {
        return $reminders->filter(fn (Reminder $reminder): bool => $reminder->status === $status)->count();
    }
}
