<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PronunciationAttempt;
use App\Models\User;

/**
 * Who may read a pronunciation check (spec 0006 §7, PRIV-04, ROLE-04).
 *
 * The learner who recorded it, and whoever may read learners' recordings
 * and transcripts (the Super Admin). A coworker or a manager is refused:
 * the attempt carries the learner's words verbatim, like a transcript.
 */
class PronunciationAttemptPolicy
{
    public function view(User $user, PronunciationAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id
            || $user->can(Permission::TranscriptsView->value);
    }
}
