<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ReminderTemplate;
use App\Models\User;

/**
 * Reminder templates are platform content (REM-04; spec 0003 Part D).
 *
 * Anyone who may open the Messages screen may read and preview them. Writing
 * them stays with the platform owner: a manager sends the templates, never
 * edits them (REM-07, AGENTS.md role table). The Super Admin already passes
 * through Gate::before; the explicit role check here keeps the rule true
 * even without that hook.
 */
class ReminderTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MessagesView->value);
    }

    public function view(User $user, ReminderTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->owns($user);
    }

    public function update(User $user, ReminderTemplate $template): bool
    {
        return $this->owns($user);
    }

    private function owns(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin->value)
            && $user->can(Permission::MessagesManage->value);
    }
}
