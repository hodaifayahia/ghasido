<?php

namespace App\Enums;

/**
 * Where one reminder is in its life (REM-04, REM-06, spec 0003 B.7).
 *
 * `blocked` is a real outcome, not an error: a reminder addressed to somebody
 * who has not consented, or who has no email, is written down with the reason
 * and never sent (REM-05). That row is what proves the rule was honoured.
 */
enum ReminderStatus: string
{
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Sent = 'sent';
    case Blocked = 'blocked';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Queued => __('Queued'),
            self::Sent => __('Sent'),
            self::Blocked => __('Blocked'),
            self::Failed => __('Failed'),
        };
    }
}
