<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;

/**
 * Who may do what to a department (ROLE-01, ROLE-02, SEC-01; spec 0003
 * Part D).
 *
 * Two questions every method answers, in this order: does this user hold the
 * capability at all, and is this particular department inside what they may
 * see (Department::isVisibleTo). There is no tenant scope on the model, so
 * these methods are the whole authorization; a row outside the boundary is a
 * 403, never a 404, because route binding finds it and the policy refuses it.
 *
 * The Super Admin never reaches any of this: Gate::before in
 * AppServiceProvider returns true for them first (spec 0001, AC-2).
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DepartmentsView->value);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can(Permission::DepartmentsView->value)
            && $department->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DepartmentsManage->value);
    }

    /**
     * A shared catalogue row belongs to the platform, so only the Super Admin
     * (through Gate::before) may edit it; anyone else holding the capability
     * may edit their own hotel's departments.
     */
    public function update(User $user, Department $department): bool
    {
        return $user->can(Permission::DepartmentsManage->value)
            && $this->owns($user, $department);
    }

    /** Safe delete: refused while anything still uses the department. */
    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }

    public function toggle(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }

    private function owns(User $user, Department $department): bool
    {
        return $department->hotel_id !== null
            && $user->hotel_id !== null
            && $department->hotel_id === $user->hotel_id;
    }
}
