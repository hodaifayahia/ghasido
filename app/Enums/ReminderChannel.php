<?php

namespace App\Enums;

/**
 * How a reminder reaches the employee (REM-01, spec 0003 B.7).
 *
 * Email needs an address and recorded consent (REM-05). In-app needs neither:
 * it is a row the Messages page lists, so it is "sent" the moment it exists.
 */
enum ReminderChannel: string
{
    case Email = 'email';
    case InApp = 'in_app';

    public function label(): string
    {
        return match ($this) {
            self::Email => __('Email'),
            self::InApp => __('In-app'),
        };
    }

    /**
     * Whether this channel is gated on the employee's reminder consent.
     */
    public function requiresConsent(): bool
    {
        return $this === self::Email;
    }
}
