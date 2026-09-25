<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The four account roles (including the learner role) and twenty permissions
 * behind them (ROLE-01, AC-3). Super Admin, Admin and Manager are the three
 * back-office roles; Employee is the learner role.
 *
 * Idempotent: findOrCreate never duplicates a row and syncPermissions rewrites
 * the matrix rather than appending to it, so running this twice leaves twenty
 * permissions, not forty.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Read the tables rather than a stale cache while seeding, and leave a
        // clean one behind. DatabaseSeeder uses WithoutModelEvents, which
        // mutes the package's own invalidation (spec 0001, invariant 7).
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // No guard argument: the package falls back to the default guard, so
        // the guard name is never duplicated here and in config/auth.php.
        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        // Clear again before the roles look those permissions up. Called
        // through DatabaseSeeder the writes above happen with model events
        // muted, so the package never invalidated its own cache and the
        // registrar is still holding the empty set it read a moment ago:
        // syncPermissions() would then fail with PermissionDoesNotExist on a
        // fresh database (spec 0001, invariant 7).
        $registrar->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value)
                ->syncPermissions($role->permissionNames());
        }

        $registrar->forgetCachedPermissions();
    }
}
