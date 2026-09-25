<?php

namespace App\Enums;

/**
 * Where one AI role-play conversation stands (RP-05, RP-07, spec 0003 B.6).
 *
 * Only `in_progress` accepts a new turn. `evaluating` means the learner ended
 * the conversation and the evaluation job is running (PERF-04); `completed`
 * carries the scores and feedback; `abandoned` is a conversation that was
 * never ended. The last three all count as one used attempt (RP-06).
 */
enum RoleplayStatus: string
{
    case InProgress = 'in_progress';
    case Evaluating = 'evaluating';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function acceptsTurns(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * The conversation is over from the learner's side, whether or not the
     * evaluation has landed yet.
     */
    public function isFinished(): bool
    {
        return $this !== self::InProgress;
    }
}
