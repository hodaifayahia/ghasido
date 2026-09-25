<?php

namespace App\Enums;

/**
 * Where one sitting of a test stands (spec 0003 B.6).
 *
 * `in_progress` is the only state that accepts answers. `submitted` is set by
 * the learner finishing (or by the timeout rule when `on_timeout` is
 * `submit`); `expired` when the deadline passed and the rule was
 * `unanswered`. Both are terminal: a sitting is never reopened.
 */
enum TestAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Expired = 'expired';

    public function acceptsAnswers(): bool
    {
        return $this === self::InProgress;
    }

    public function isTerminal(): bool
    {
        return $this !== self::InProgress;
    }
}
