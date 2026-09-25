<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds `scores.override` (AIE-05; spec 0005 §2.5) to an existing database and
 * grants it to the Super Admin role only, without touching any other role.
 *
 * The repo's pattern for a new permission (see 2026_09_23_200001): the
 * seeder would sync every built-in role and wipe the edits an admin made on
 * the Roles & Permissions screen, so a running install gets the permission
 * from here instead. Idempotent: insertOrIgnore on both rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $now = now();
        $rolePivot = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $permissionPivot = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        DB::table('permissions')->insertOrIgnore([
            'name' => PermissionEnum::ScoresOverride->value,
            'guard_name' => $guard,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', PermissionEnum::ScoresOverride->value)
            ->where('guard_name', $guard)
            ->value('id');
        $roleId = DB::table('roles')
            ->where('name', RoleEnum::SuperAdmin->value)
            ->where('guard_name', $guard)
            ->value('id');

        if ($permissionId !== null && $roleId !== null) {
            DB::table('role_has_permissions')->insertOrIgnore([
                $permissionPivot => $permissionId,
                $rolePivot => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', PermissionEnum::ScoresOverride->value)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
