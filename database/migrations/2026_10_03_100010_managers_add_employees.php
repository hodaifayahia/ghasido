<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Client request 2026-10-02: a hotel manager adds their own employees
 * (within the plan's seats). The Hotel Manager role gets "Add employees";
 * the Super Admin can still take it away in Roles & Permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('name', 'manager')->where('guard_name', 'web')->first();
        $permission = Permission::query()->where('name', 'employees.create')->where('guard_name', 'web')->first();

        if ($role !== null && $permission !== null && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Left as granted: taking it away is a choice for the Super Admin.
    }
};
