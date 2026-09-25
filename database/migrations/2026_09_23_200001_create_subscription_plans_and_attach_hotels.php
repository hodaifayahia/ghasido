<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('employee_limit');
            $table->unsignedBigInteger('price_dzd')->default(0);
            $table->unsignedInteger('points_per_employee')->default(2000);
            $table->unsignedInteger('bonus_points_per_employee')->default(1000);
            $table->unsignedInteger('voice_points_per_10_minutes')->default(100);
            $table->unsignedInteger('ai_action_points')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('subscription_plans')->insert([
            [
                'name' => 'Standard', 'slug' => 'standard', 'employee_limit' => 4,
                'price_dzd' => 12000, 'points_per_employee' => 2000,
                'bonus_points_per_employee' => 1000, 'voice_points_per_10_minutes' => 100,
                'ai_action_points' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Gold', 'slug' => 'gold', 'employee_limit' => 7,
                'price_dzd' => 20000, 'points_per_employee' => 2000,
                'bonus_points_per_employee' => 1000, 'voice_points_per_10_minutes' => 100,
                'ai_action_points' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Diamond', 'slug' => 'diamond', 'employee_limit' => 15,
                'price_dzd' => 40000, 'points_per_employee' => 2000,
                'bonus_points_per_employee' => 1000, 'voice_points_per_10_minutes' => 100,
                'ai_action_points' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $standardId = DB::table('subscription_plans')->where('slug', 'standard')->value('id');

        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->restrictOnDelete();
        });

        DB::table('hotels')->whereNull('subscription_plan_id')->update(['subscription_plan_id' => $standardId]);
        $this->placeExistingHotelsOnClosestPlan();

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('ai_points_allocated')->default(2000);
        });

        Schema::table('ai_usages', function (Blueprint $table) {
            $table->unsignedInteger('points_charged')->default(0);
        });

        Schema::table('roleplay_attempts', function (Blueprint $table) {
            $table->unsignedInteger('ai_points_charged')->default(0);
        });

        $this->grantSubscriptionPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table) {
            $table->dropColumn('ai_points_charged');
        });

        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropColumn('points_charged');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_points_allocated');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });

        Schema::dropIfExists('subscription_plans');

        $this->removeSubscriptionPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Make new controls available immediately after migrate, before a seeder is run. */
    private function grantSubscriptionPermissions(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $now = now();
        $rolePivot = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $permissionPivot = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        $grants = [
            [RoleEnum::SuperAdmin, [PermissionEnum::SubscriptionsManage, PermissionEnum::AiPointsManage]],
            [RoleEnum::Manager, [PermissionEnum::AiPointsManage]],
        ];

        foreach ($grants as [$role, $permissions]) {
            $roleId = DB::table('roles')->where('name', $role->value)->where('guard_name', $guard)->value('id');

            foreach ($permissions as $permission) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => $permission->value,
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($roleId === null) {
                    continue;
                }

                $permissionId = DB::table('permissions')
                    ->where('name', $permission->value)
                    ->where('guard_name', $guard)
                    ->value('id');

                DB::table('role_has_permissions')->insertOrIgnore([
                    $permissionPivot => $permissionId,
                    $rolePivot => $roleId,
                ]);
            }
        }
    }

    private function removeSubscriptionPermissions(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $permissions = [PermissionEnum::SubscriptionsManage->value, PermissionEnum::AiPointsManage->value];
        $ids = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', $guard)->pluck('id');
        $permissionPivot = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        DB::table('role_has_permissions')->whereIn($permissionPivot, $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    /** Preserve every current account while placing existing hotels in the closest included tier. */
    private function placeExistingHotelsOnClosestPlan(): void
    {
        $employeeRoleId = DB::table('roles')->where('name', RoleEnum::Employee->value)->value('id');

        if ($employeeRoleId === null) {
            return;
        }

        $counts = DB::table('users')
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->where('model_has_roles.role_id', $employeeRoleId)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->where('users.status', 'active')
            ->whereNotNull('users.hotel_id')
            ->groupBy('users.hotel_id')
            ->selectRaw('users.hotel_id as hotel_id, count(users.id) as employee_count')
            ->get();

        foreach ($counts as $row) {
            $count = (int) $row->employee_count;
            $slug = $count <= 4 ? 'standard' : ($count <= 7 ? 'gold' : 'diamond');
            $planId = DB::table('subscription_plans')->where('slug', $slug)->value('id');

            if ($planId !== null) {
                DB::table('hotels')->where('id', $row->hotel_id)->update(['subscription_plan_id' => $planId]);
            }
        }
    }
};
