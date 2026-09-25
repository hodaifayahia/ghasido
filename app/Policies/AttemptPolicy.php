<?php

namespace App\Policies;

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

    private function owns(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
