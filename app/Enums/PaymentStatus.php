<?php

namespace App\Enums;

/**
 * Where a customer's payment submission stands (client request 2026-09-27).
 * It follows the account it pays for: confirmed when the hotel or
 * individual is approved, rejected when they are rejected.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting approval'),
            self::Confirmed => __('Confirmed'),
            self::Rejected => __('Rejected'),
        };
    }
}
