<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Add user-directory capabilities to existing installations (ROLE-01). */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = [
            PermissionEnum::UsersView->value,
            PermissionEnum::UsersManage->value,
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Fresh installs seed this later; existing installs already have the
        // owner role and should receive both new capabilities immediately.
        $superAdmin = Role::query()->where('name', RoleEnum::SuperAdmin->value)->first();
        $superAdmin?->givePermissionTo($permissions);

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', [PermissionEnum::UsersView->value, PermissionEnum::UsersManage->value])
            ->get()
            ->each(function (Permission $permission): void {
                $permission->roles()->detach();
                $permission->delete();
            });

        $registrar->forgetCachedPermissions();
    }
};
