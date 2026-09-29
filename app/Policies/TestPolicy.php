<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TestAttemptStatus;
use App\Models\Test;
use App\Models\User;

/**
 * Who may sit a test (ROLE-02, JOURNEY-01, TEST-05, spec 0003 Part E).
 *
 * Availability goes through Test::scopeForLearner(), the one place the
 * department and hotel rule is written, so the policy and the listing can
 * never disagree. The Super Admin never reaches this: Gate::before in
 * AppServiceProvider answers first (ROLE-03).
 */
class TestPolicy
{
    /** Admin test-library access (TSTM-01, TSTM-02, ROLE-01). */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TestsView->value);
    }

    /** Test definitions are authoring records, not learner records. */
    public function create(User $user): bool
    {
        return $user->can(Permission::TestsManage->value);
    }

    public function update(User $user, Test $test): bool
    {
        return $user->can(Permission::TestsManage->value) && $this->isInAdminReach($user, $test);
    }

    /** Safe delete: refused while any learner has taken the test. */
    public function delete(User $user, Test $test): bool
    {
        return $this->update($user, $test);
    }

    public function publish(User $user, Test $test): bool
    {
        return $this->update($user, $test);
    }

    /**
     * May this employee open the test's intro and questions?
     */
    public function view(User $user, Test $test): bool
    {
        if ($user->can(Permission::TestsView->value)) {
            return $this->isInAdminReach($user, $test);
        }

        return $this->isAvailableTo($user, $test);
    }

    /**
     * May this employee start (or resume) a sitting?
     *
     * An in-progress sitting is always resumable, so a dropped connection or
     * a device switch never locks a learner out (AUTH-09). The Pre-test may
     * be sat once: a submitted attempt closes it for good, because showing it
     * again would bias the research baseline (TEST-05).
     */
    public function start(User $user, Test $test): bool
    {
        if (! $this->isAvailableTo($user, $test)) {
            return false;
        }

        $attempts = $test->attempts()->where('user_id', $user->id);

        if ((clone $attempts)->where('status', TestAttemptStatus::InProgress->value)->exists()) {
            return true;
        }

        if ($test->type->isSingleAttempt()
            && (clone $attempts)->where('status', TestAttemptStatus::Submitted->value)->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Published, for the employee's department, and shared or their hotel's.
     * Only employees sit tests; a manager reads results elsewhere.
     */
    private function isAvailableTo(User $user, Test $test): bool
    {
        if (! $user->hasRole(Role::Employee->value)) {
            return false;
        }

        return Test::query()
            ->forLearner($user)
            ->whereKey($test->getKey())
            ->exists();
    }

    /** Shared rows or the current hotel's rows only (ROLE-02). */
    private function isInAdminReach(User $user, Test $test): bool
    {
        return $test->hotel_id === null || $test->hotel_id === $user->hotel_id;
    }
}
