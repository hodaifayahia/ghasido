<?php

namespace App\Enums;

/**
 * Who a subscription plan is for (client request 2026-09-27): a hotel,
 * sized by employee seats, or one individual learner, sized by AI points.
 */
enum PlanAudience: string
{
    case Hotel = 'hotel';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Hotel => __('Hotel'),
            self::Individual => __('Individual'),
        };
    }
}
