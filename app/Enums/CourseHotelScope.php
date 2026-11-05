<?php

namespace App\Enums;

/**
 * Which hotels see a shared course (client request 2026-11-05): every hotel,
 * only a chosen group, or every hotel except a chosen group. The group is
 * `course_hotel`. A course owned by one hotel (`hotel_id` set) ignores it:
 * only that hotel sees it (ORG-04, CMS-04).
 */
enum CourseHotelScope: string
{
    case All = 'all';
    case Only = 'only';
    case Except = 'except';

    public function label(): string
    {
        return match ($this) {
            self::All => __('All hotels'),
            self::Only => __('Only these hotels'),
            self::Except => __('All hotels except these'),
        };
    }
}
