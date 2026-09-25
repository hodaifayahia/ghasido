<?php

namespace App\Enums;

/**
 * How full a hotel or a department is on seats (spec 0002).
 *
 * Never stored: derived by comparing a live count of active employee accounts
 * against the allowed seats. Matches HotelCapacityState in
 * resources/js/types/hotels.ts.
 */
enum CapacityState: string
{
    case Available = 'available';
    case Full = 'full';
    case Over = 'over';

    /**
     * Compare used against allowed.
     *
     * A hotel with no quotas at all (0 allowed, 0 used) reads as Available,
     * not Full. Without this guard a hotel that simply has no departments yet
     * would display as At Capacity, which is wrong and misleading
     * (spec 0002, AC-1 boundary case).
     */
    public static function compare(int $used, int $allowed): self
    {
        if ($allowed === 0) {
            return $used > 0 ? self::Over : self::Available;
        }

        return match (true) {
            $used > $allowed => self::Over,
            $used === $allowed => self::Full,
            default => self::Available,
        };
    }
}
