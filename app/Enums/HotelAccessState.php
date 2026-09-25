<?php

namespace App\Enums;

/**
 * What an administrator has set a hotel to (spec 0002).
 *
 * These four are the only stored states. `expiring` and `ended` are NOT here
 * on purpose: both are derived from the contract dates at read time, so they
 * can never disagree with the calendar and no scheduled job is needed to keep
 * a column honest (spec 0002, invariant 1). See HotelStatus for the full set
 * the page displays.
 */
enum HotelAccessState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    /**
     * Whether anyone attached to this hotel may sign in (spec 0002, AC-15).
     *
     * Only `active` opens the door, and even then the contract dates get the
     * final say, which is why this is not the whole login check.
     */
    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * The message a blocked user sees, naming which situation applies.
     */
    public function blockedMessage(): string
    {
        return match ($this) {
            self::Pending => __('This hotel is waiting for approval. Your administrator will let you know when it opens.'),
            self::Paused => __('Access to this hotel is paused. Please contact your administrator.'),
            self::Archived => __('This hotel is no longer active. Please contact your administrator.'),
            self::Active => __('Your training period has ended. Please contact your administrator.'),
        };
    }
}
