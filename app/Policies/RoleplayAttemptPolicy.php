<?php

namespace App\Policies;

use App\Models\RoleplayAttempt;
use App\Models\User;

/**
 * A role-play conversation belongs to the learner who had it (ROLE-02,
 * ROLE-04, PRIV-04, spec 0003 Part E).
 *
 * Owner only. The full transcript is the most sensitive learner artefact on
 * the platform: a manager never reads one through this policy, and the
 * Super Admin passes through Gate::before (ROLE-03).
 */
class RoleplayAttemptPolicy
{
    public function view(User $user, RoleplayAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    public function update(User $user, RoleplayAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    /**
     * Adding a turn or ending the conversation: owner, and still in progress.
     */
    public function message(User $user, RoleplayAttempt $attempt): bool
    {
        return $this->owns($user, $attempt)
            && $attempt->status->acceptsTurns();
    }

    private function owns(User $user, RoleplayAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
