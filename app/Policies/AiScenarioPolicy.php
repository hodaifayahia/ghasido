<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AiScenario;
use App\Models\User;

/**
 * Who may practise or change an AI role-play scenario (ROLE-01, ROLE-02,
 * RP-01, spec 0003 Part E).
 *
 * A learner may open a scenario that is published, in their department and
 * shared or their hotel's; an admin reader may see shared rows and their own
 * hotel's. The attempt count (RP-05) and the AI limits (AIL-03) are checked
 * by the start action, not here: they are about this attempt, not this user.
 */
class AiScenarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ScenariosView->value);
    }

    public function view(User $user, AiScenario $scenario): bool
    {
        if ($user->can(Permission::ScenariosView->value)) {
            return $this->isInReach($user, $scenario);
        }

        return $scenario->isVisibleTo($user);
    }

    /**
     * May this learner start a conversation on it (RP-01)?
     */
    public function attempt(User $user, AiScenario $scenario): bool
    {
        return $scenario->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ScenariosManage->value);
    }

    public function update(User $user, AiScenario $scenario): bool
    {
        return $user->can(Permission::ScenariosManage->value) && $this->isInReach($user, $scenario);
    }

    public function delete(User $user, AiScenario $scenario): bool
    {
        return $this->update($user, $scenario);
    }

    /**
     * "Delete" from the directory table (CMS-01). Shared content reaches
     * every hotel, so only a platform level admin (no hotel of their own)
     * may delete it; a hotel admin deletes their own hotel's rows only
     * (ROLE-02). Learner answers are never deleted either way (DATA-10).
     */
    public function destroy(User $user, AiScenario $scenario): bool
    {
        if (! $this->update($user, $scenario)) {
            return false;
        }

        return $scenario->hotel_id !== null || $user->hotel_id === null;
    }

    private function isInReach(User $user, AiScenario $scenario): bool
    {
        return $scenario->hotel_id === null || $scenario->hotel_id === $user->hotel_id;
    }
}
