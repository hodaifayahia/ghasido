<?php

namespace App\Enums;

/**
 * The editorial state of a department's curriculum (spec 0003, B.2).
 *
 * This is the pill on the Departments screen. It is separate from
 * `is_active`, which is the on/off toggle: a department under review is still
 * switched on, it just is not finished.
 */
enum DepartmentStatus: string
{
    case Active = 'active';
    case Review = 'review';
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Review => __('In review'),
            self::Draft => __('Draft'),
        };
    }
}
