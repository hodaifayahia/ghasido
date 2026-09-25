<?php

namespace App\Enums;

/**
 * What an automation rule watches for (REM-03, spec 0003 B.7).
 *
 * The runner (App\Services\Reminders\AutomationRunner) turns each case into a
 * recipient query. `days` on the rule means "how long" for `inactive_days`,
 * and doubles as the repeat window for every trigger, so the same employee is
 * not nudged by the same rule every morning.
 */
enum AutomationTrigger: string
{
    case InactiveDays = 'inactive_days';
    case NotStarted = 'not_started';
    case ConsentMissing = 'consent_missing';
    case PosttestAvailable = 'posttest_available';

    public function label(?int $days = null): string
    {
        return match ($this) {
            self::InactiveDays => __('No progress for :days days', ['days' => $days ?? (int) config('guesvia.reminders.inactive_days', 5)]),
            self::NotStarted => __('Training not started'),
            self::ConsentMissing => __('Email consent not completed'),
            self::PosttestAvailable => __('Post-test available'),
        };
    }

    /**
     * The channel this trigger sends on.
     *
     * A consent reminder cannot go by email: the people it targets are, by
     * definition, the ones who have not consented to email (REM-05). It goes
     * in-app instead. Every other trigger is an email nudge.
     */
    public function channel(): ReminderChannel
    {
        return match ($this) {
            self::ConsentMissing => ReminderChannel::InApp,
            default => ReminderChannel::Email,
        };
    }
}
