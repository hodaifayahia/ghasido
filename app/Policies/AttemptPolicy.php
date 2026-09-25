<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Attempt;
use App\Models\User;

/**
 * An answer belongs to the learner who gave it (ROLE-02, SEC-01,
 * spec 0003 Part E).
 *
 * Owner only. Managers see aggregates through the reports, never a learner's
 * individual row; the Super Admin passes through Gate::before (ROLE-03).
 */
class AttemptPolicy
{
    public function view(User $user, Attempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    public function update(User $user, Attempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    /**
     * Replacing or re-grading a score (AIE-05; spec 0005 §2.5): a holder of
     * `scores.override`, and never the learner's own score, and only inside
     * the actor's hotel when they have one. The permission belongs to the
     * Super Admin (who also passes Gate::before); the hotel bound keeps a
     * custom role that is granted it from reaching another hotel (ROLE-02).
     */
    public function overrideScore(User $user, Attempt $attempt): bool
    {
        if (! $user->can(Permission::ScoresOverride->value) || $attempt->user_id === $user->id) {
            return false;
        }

        return $user->hotel_id === null
            || $attempt->user()->value('hotel_id') === $user->hotel_id;
    }

    private function owns(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
