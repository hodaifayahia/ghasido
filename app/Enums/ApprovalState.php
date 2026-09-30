<?php

namespace App\Enums;

/**
 * Whether an individual subscription bought online has been approved
 * (client request 2026-09-27). Subscriptions an admin creates are approved
 * from the start.
 */
enum ApprovalState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }
}
