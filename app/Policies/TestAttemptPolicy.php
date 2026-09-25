<?php

namespace App\Policies;

use App\Enums\TestAttemptStatus;
use App\Models\TestAttempt;
use App\Models\User;

/**
 * A test sitting belongs to one learner (ROLE-02, SEC-01, spec 0003 Part E).
 *
 * Owner only, by id: a user with a different id gets false, which the
 * controller turns into a 403 rather than a 404 (AGENTS.md §6). Answers may
 * only be written while the sitting is in progress; a submitted or expired
 * sitting is read-only for everyone but the Super Admin.
 */
class TestAttemptPolicy
{
    public function view(User $user, TestAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    public function update(User $user, TestAttempt $attempt): bool
    {
        return $this->owns($user, $attempt)
            && $attempt->status === TestAttemptStatus::InProgress;
    }

    private function owns(User $user, TestAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
