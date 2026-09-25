<?php

namespace App\Enums;

/**
 * The status the page displays (spec 0002).
 *
 * Never stored. This mixes the stored access state with the contract dates:
 * an `active` hotel reads as `expiring` or `ended` purely from how many days
 * are left. That is why the stored column is named `access_state` and this is
 * named status, so nothing ever filters the wrong one.
 *
 * Matches HotelContractStatus in resources/js/types/hotels.ts.
 */
enum HotelStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expiring = 'expiring';
    case Paused = 'paused';
    case Ended = 'ended';
    case Archived = 'archived';

    /**
     * The buckets the stat cards count, which are mutually exclusive so they
     * sum to Total Hotels (spec 0002, AC-3). Archived is excluded on purpose.
     *
     * @return list<self>
     */
    public static function countedBuckets(): array
    {
        return [self::Pending, self::Active, self::Expiring, self::Paused, self::Ended];
    }
}
