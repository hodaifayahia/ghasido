<?php

namespace App\Enums;

/**
 * How demanding an AI role-play scenario is (RP-01, spec 0003 B.5).
 *
 * The client's three levels, Beginner, Intermediate and Advanced (client
 * decision 2026-09-30), the same as EnglishLevel.
 */
enum ScenarioDifficulty: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => __('Beginner'),
            self::Intermediate => __('Intermediate'),
            self::Advanced => __('Advanced'),
        };
    }
}
