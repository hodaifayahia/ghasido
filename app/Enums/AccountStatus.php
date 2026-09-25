<?php

namespace App\Enums;

/**
 * Whether a user account is live (spec 0002, AUTH-08).
 *
 * Seat usage counts active accounts only, so this column is what lets a hotel
 * free a seat without deleting anybody, which DATA-10 forbids.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
