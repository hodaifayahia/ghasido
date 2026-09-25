<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The role catalogue itself (spec 0001, AC-3, AC-4).
 */
class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_four_account_roles_and_a_row_per_permission(): void
    {
        $this->assertSame(4, Role::count());
        $this->assertSame(count(PermissionEnum::cases()), Permission::count());
    }

    public function test_the_seeder_is_safe_to_run_twice(): void
    {
        // Re-running leaves one row per permission, not two (AC-3). TestCase
        // already ran it once, so this is the second run.
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertSame(count(PermissionEnum::cases()), Permission::count());
        $this->assertCount(
            count(RoleEnum::Manager->permissionNames()),
            Role::findByName(RoleEnum::Manager->value)->permissions,
        );
    }

    public function test_the_super_admin_holds_every_permission_row(): void
    {
        // In addition to the Gate::before override, so auth.permissions is
        // never empty for the platform owner (invariant 3).
        $this->assertSame(
            PermissionEnum::names(),
            RoleEnum::SuperAdmin->permissionNames(),
        );

        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertCount(count(PermissionEnum::cases()), $superAdmin->permissionNames());
    }

    public function test_an_employee_holds_no_admin_permission(): void
    {
        $this->assertSame([], RoleEnum::Employee->permissionNames());
        $this->assertSame([], User::factory()->employee()->create()->permissionNames());
    }

    public function test_a_manager_may_read_but_not_change_the_lesson_library(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue($manager->can(PermissionEnum::LessonsView->value));
        $this->assertFalse($manager->can(PermissionEnum::LessonsManage->value));
    }

    public function test_admin_and_manager_can_open_their_hotel_and_manage_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$admin, $manager] as $user) {
            $this->assertTrue($user->can(PermissionEnum::HotelsView->value));
            $this->assertTrue($user->can(PermissionEnum::EmployeesView->value));
            $this->assertTrue($user->can(PermissionEnum::EmployeesManage->value));
            $this->assertFalse($user->can(PermissionEnum::HotelsManage->value));
        }

        $this->assertTrue($admin->can(PermissionEnum::DepartmentsManage->value));
        $this->assertFalse($manager->can(PermissionEnum::DepartmentsManage->value));

        // Adding an account is its own capability: an Admin holds it, a Manager
        // may edit but not add (client decision narrowing SUB-02).
        $this->assertTrue($admin->can(PermissionEnum::EmployeesCreate->value));
        $this->assertFalse($manager->can(PermissionEnum::EmployeesCreate->value));
    }

    public function test_a_manager_can_no_longer_open_or_export_reports(): void
    {
        // Client decision: Reports & Export is hidden from managers, so the
        // capability is dropped from the role (narrows REP-08). An Admin still
        // reads and exports their own hotel.
        $manager = User::factory()->manager()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse($manager->can(PermissionEnum::ReportsView->value));
        $this->assertFalse($manager->can(PermissionEnum::ReportsExport->value));
        $this->assertTrue($admin->can(PermissionEnum::ReportsView->value));
        $this->assertTrue($admin->can(PermissionEnum::ReportsExport->value));
    }

    public function test_a_manager_cannot_read_transcripts_or_reset_progress(): void
    {
        // The PhD privacy boundary: Super Admin only (ROLE-04, PRIV-04,
        // PROG-06).
        $manager = User::factory()->manager()->create();

        $this->assertFalse($manager->can(PermissionEnum::TranscriptsView->value));
        $this->assertFalse($manager->can(PermissionEnum::ProgressReset->value));
        $this->assertFalse($manager->can(PermissionEnum::ReportsExportAnonymised->value));
    }

    public function test_a_user_holds_at_most_one_role(): void
    {
        // Assigning a second replaces the first rather than adding to it
        // (AC-4, invariant 1).
        $user = User::factory()->manager()->create();

        $user->setRole(RoleEnum::Employee);

        $this->assertSame([RoleEnum::Employee->value], $user->getRoleNames()->all());
        $this->assertSame(RoleEnum::Employee->value, $user->roleName());
    }

    public function test_a_permission_reaches_a_user_only_through_a_role(): void
    {
        // model_has_permissions stays empty (invariant 2).
        User::factory()->superAdmin()->create();

        $this->assertDatabaseCount('model_has_permissions', 0);
    }

    public function test_the_middleware_argument_is_built_from_the_enum(): void
    {
        // No bare permission string appears in a route file (invariant 5).
        $this->assertSame(
            'permission:hotels.view',
            PermissionEnum::HotelsView->middleware(),
        );
    }
}
