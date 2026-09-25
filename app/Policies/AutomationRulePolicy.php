<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AutomationRule;
use App\Models\User;

/**
 * Automatic reminder rules run across the platform (REM-03; spec 0003
 * Part D), so only the platform owner writes them. A manager sees them,
 * because they explain the automatic rows in their hotel's log, and changes
 * nothing (REM-07).
 */
class AutomationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MessagesView->value);
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->owns($user);
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $this->owns($user);
    }

    /**
     * Switch a rule on or off without opening the editor.
     */
    public function toggle(User $user, AutomationRule $rule): bool
    {
        return $this->owns($user);
    }

    private function owns(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin->value)
            && $user->can(Permission::MessagesManage->value);
    }
}
