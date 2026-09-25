<?php

namespace App\Enums;

/**
 * Whether a piece of content is visible to learners (CMS-05, spec 0003 B.5).
 *
 * Courses, units, lessons, activities and AI scenarios all carry one of these.
 * There is no `archived` case: CMS-01 archiving is a status the admin lane
 * may add later without touching the learner side, which only ever asks
 * "is it published".
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
        };
    }

    /**
     * The StatusPill tone the admin tree shows (desgin/11-components.md 11.3).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
        };
    }
}
