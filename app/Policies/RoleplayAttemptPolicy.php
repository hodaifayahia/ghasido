<?php

namespace App\Policies;

use App\Enums\Permission;
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

    /**
     * Replacing or re-grading a score (AIE-05; spec 0005 §2.5): a holder of
     * `scores.override`, and never the learner's own score, and only inside
     * the actor's hotel when they have one. The permission belongs to the
     * Super Admin (who also passes Gate::before); the hotel bound keeps a
     * custom role that is granted it from reaching another hotel (ROLE-02).
     */
    public function overrideScore(User $user, RoleplayAttempt $attempt): bool
    {
        if (! $user->can(Permission::ScoresOverride->value) || $attempt->user_id === $user->id) {
            return false;
        }

        return $user->hotel_id === null
            || $attempt->user()->value('hotel_id') === $user->hotel_id;
    }

    private function owns(User $user, RoleplayAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
