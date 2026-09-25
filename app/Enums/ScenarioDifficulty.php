<?php

namespace App\Enums;

/**
 * How demanding an AI role-play scenario is (RP-01, spec 0003 B.5).
 *
 * Difficulty tags, not CEFR levels: whether the platform adopts A1/A2/B1 is
 * an open client decision (system/08 8.1), so nothing here names a CEFR band.
 */
enum ScenarioDifficulty: string
{
    case Beginner = 'beginner';
    case Elementary = 'elementary';
    case Intermediate = 'intermediate';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => __('Beginner'),
            self::Elementary => __('Elementary'),
            self::Intermediate => __('Intermediate'),
        };
    }
}
