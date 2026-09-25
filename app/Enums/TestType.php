<?php

namespace App\Enums;

/**
 * Which side of the paired assessment a Test is (TEST-02, spec 0003 B.6).
 *
 * The Pre-test is gate 1 of the employee journey: nothing else opens until it
 * is submitted (JOURNEY-01). The Post-test is gate 2 (JOURNEY-04).
 */
enum TestType: string
{
    case Pre = 'pre';
    case Post = 'post';

    public function label(): string
    {
        return match ($this) {
            self::Pre => __('Pre-test'),
            self::Post => __('Post-test'),
        };
    }

    /**
     * The Pre-test may be sat exactly once: showing it again would bias the
     * research baseline (TEST-05, spec 0003 TestPolicy::start).
     */
    public function isSingleAttempt(): bool
    {
        return $this === self::Pre;
    }
}
