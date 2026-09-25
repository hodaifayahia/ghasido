<?php

namespace App\Enums;

/**
 * What an employee may see after submitting a test (TEST-04, spec 0003 B.6).
 *
 * An admin setting stored in `tests.settings.results_visibility`, never a
 * constant: showing Pre-test results could bias the research, so the safe
 * default when nothing is configured is `hidden`.
 */
enum ResultsVisibility: string
{
    case Hidden = 'hidden';
    case Score = 'score';
    case ScoreBreakdown = 'score_breakdown';

    public function showsScore(): bool
    {
        return $this !== self::Hidden;
    }

    public function showsBreakdown(): bool
    {
        return $this === self::ScoreBreakdown;
    }
}
