<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
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
        // Security audit 2026-11-08: transcripts.view on a hotel-bound role
        // reaches its own hotel's attempts only (ROLE-02, ROLE-04).
        return $attempt->user_id === $user->id
            || $user->hasRole(Role::SuperAdmin->value)
            || ($user->can(Permission::TranscriptsView->value)
                && $attempt->hotel_id !== null
                && $attempt->hotel_id === $user->hotel_id);
    }
}
