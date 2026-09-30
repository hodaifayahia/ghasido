<?php

namespace App\Models;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use Carbon\CarbonInterface;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * One reminder to one employee (REM-04, REM-06, spec 0003 B.7).
 *
 * The row is the log. `subject` and `body` are the text as rendered at send
 * time; `status` walks queued -> sent | failed for email, is `sent` at once
 * for in-app, and is `blocked` (with `blocked_reason`) when consent or an
 * address was missing (REM-05). `read_at` tracks whether the recipient has
 * seen the delivered reminder in the app's notification center (REM-08).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $template_id
 * @property int|null $automation_rule_id
 * @property ReminderChannel $channel
 * @property string $subject
 * @property string $body
 * @property int|null $sent_by
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property ReminderStatus $status
 * @property string|null $blocked_reason why it was blocked, or the failure message
 * @property Carbon|null $read_at
 * @property string|null $provider_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read ReminderTemplate|null $template
 * @property-read AutomationRule|null $automationRule
 * @property-read User|null $sender
 */
#[Fillable([
    'user_id',
    'template_id',
    'automation_rule_id',
    'channel',
    'subject',
    'body',
    'sent_by',
    'scheduled_for',
    'sent_at',
    'status',
    'blocked_reason',
    'read_at',
    'provider_message_id',
])]
class Reminder extends Model
{
    /** In-app notices stop appearing after a day; their audit rows remain. */
    public const IN_APP_EXPIRY_HOURS = 24;

    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /** Blocked because the employee never granted email consent (REM-05). */
    public const BLOCKED_NO_CONSENT = 'no_consent';

    /** Blocked because the employee has no email address on file (AUTH-04). */
    public const BLOCKED_NO_EMAIL = 'no_email';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ReminderChannel::class,
            'status' => ReminderStatus::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ReminderTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReminderTemplate::class, 'template_id');
    }

    /** @return BelongsTo<AutomationRule, $this> */
    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * Reminders visible to their recipient for 24 hours. An email reminder is
     * also an in-app notice from the moment it is written: the employee sees
     * it in the bell and in Messages at once, whether the email is still
     * queued, was blocked (no address or no consent) or failed (REM-08,
     * client request 2026-09-30). Expiry hides the notice but DATA-10 keeps
     * the log row.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopeVisibleInNotificationCenter(Builder $query): void
    {
        $cutoff = Date::now()->subHours(self::IN_APP_EXPIRY_HOURS);

        $query
            ->whereIn('channel', [ReminderChannel::InApp->value, ReminderChannel::Email->value])
            ->where(fn (Builder $visible) => $visible
                ->where(fn (Builder $sent) => $sent
                    ->where('status', ReminderStatus::Sent->value)
                    ->whereNotNull('sent_at')
                    ->where('sent_at', '>', $cutoff))
                ->orWhere(fn (Builder $email) => $email
                    ->where('channel', ReminderChannel::Email->value)
                    ->whereIn('status', [
                        ReminderStatus::Queued->value,
                        ReminderStatus::Blocked->value,
                        ReminderStatus::Failed->value,
                    ])
                    ->whereNull('sent_at')
                    ->where(fn (Builder $recent) => $recent
                        ->where('scheduled_for', '>', $cutoff)
                        ->orWhere(fn (Builder $now) => $now
                            ->whereNull('scheduled_for')
                            ->where('created_at', '>', $cutoff)))));
    }

    /** When the notice reached the employee's account. */
    public function noticedAt(): ?CarbonInterface
    {
        return $this->sent_at ?? $this->scheduled_for ?? $this->created_at;
    }

    /**
     * @param  Builder<Reminder>  $query
     */
    public function scopeVisibleInApp(Builder $query): void
    {
        $query
            ->visibleInNotificationCenter()
            ->where('channel', ReminderChannel::InApp->value);
    }

    /**
     * In-app reminders the employee has not opened: the topbar bell count.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopeUnreadInApp(Builder $query): void
    {
        $query
            ->visibleInApp()
            ->whereNull('read_at');
    }

    /**
     * @param  Builder<Reminder>  $query
     */
    public function scopeUnreadNotifications(Builder $query): void
    {
        $query
            ->visibleInNotificationCenter()
            ->whereNull('read_at');
    }

    /**
     * @param  Builder<Reminder>  $query
     */
    public function scopeSentBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $query
            ->where('status', ReminderStatus::Sent->value)
            ->whereBetween('sent_at', [$from, $to]);
    }

    // ------------------------------------------------------------ transitions

    public function markSent(?string $providerMessageId = null): self
    {
        $this->forceFill([
            'status' => ReminderStatus::Sent,
            'sent_at' => Date::now(),
            'provider_message_id' => $providerMessageId,
        ])->save();

        return $this;
    }

    public function markBlocked(string $reason): self
    {
        $this->forceFill([
            'status' => ReminderStatus::Blocked,
            'blocked_reason' => $reason,
        ])->save();

        return $this;
    }

    public function markFailed(string $reason): self
    {
        $this->forceFill([
            'status' => ReminderStatus::Failed,
            'blocked_reason' => mb_substr($reason, 0, 255),
        ])->save();

        return $this;
    }

    public function markRead(): self
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => Date::now()])->save();
        }

        return $this;
    }
}
