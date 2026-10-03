<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Super Admin manages roles and each role's permissions from the panel
 * (client request 2026-09-23). The four system roles cannot be deleted, their
 * names are fixed, and super_admin always holds every permission (spec 0001,
 * invariants 1 and 3).
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_the_super_admin_sees_every_role_and_the_permission_catalogue()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('roles'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Roles')
                ->has('roles', 4)
                ->has('permissionGroups')
            );
    }

    public function test_a_manager_cannot_reach_the_roles_screen()
    {
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('roles'))
            ->assertForbidden();
    }

    public function test_an_employee_cannot_reach_the_roles_screen()
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get(route('roles'))
            ->assertForbidden();
    }

    public function test_the_super_admin_can_create_a_custom_role_with_permissions()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('roles.store'), [
                'name' => 'Content Auditor',
                'permissions' => [Permission::LessonsView->value, Permission::ReportsView->value],
            ])
            ->assertRedirect(route('roles'));

        $this->forgetCache();
        $role = Role::findByName('Content Auditor');

        $this->assertTrue($role->hasPermissionTo(Permission::LessonsView->value));
        $this->assertTrue($role->hasPermissionTo(Permission::ReportsView->value));
        $this->assertFalse($role->hasPermissionTo(Permission::HotelsManage->value));
    }

    public function test_a_custom_role_can_be_renamed_and_its_permissions_changed()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Content Auditor');
        $role->syncPermissions([Permission::LessonsView->value]);
        $this->forgetCache();

        $this->actingAs($superAdmin)
            ->put(route('roles.update', $role), [
                'name' => 'Content Reviewer',
                'permissions' => [Permission::ReportsView->value],
            ])
            ->assertRedirect();

        $this->forgetCache();
        $this->assertNull(Role::where('name', 'Content Auditor')->first());

        $renamed = Role::findByName('Content Reviewer');
        $this->assertTrue($renamed->hasPermissionTo(Permission::ReportsView->value));
        $this->assertFalse($renamed->hasPermissionTo(Permission::LessonsView->value));
    }

    public function test_the_super_admin_role_keeps_every_permission_when_edited()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $role = Role::findByName(RoleEnum::SuperAdmin->value);

        // Try to strip every permission from the platform owner.
        $this->actingAs($superAdmin)
            ->put(route('roles.update', $role), ['permissions' => []])
            ->assertRedirect();

        $this->forgetCache();
        $this->assertCount(
            count(Permission::names()),
            Role::findByName(RoleEnum::SuperAdmin->value)->permissions,
        );
    }

    public function test_a_system_role_name_is_fixed_but_its_permissions_are_editable()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $manager = Role::findByName(RoleEnum::Manager->value);

        $this->actingAs($superAdmin)
            ->put(route('roles.update', $manager), [
                'name' => 'Regional Boss',
                'permissions' => [Permission::ReportsView->value],
            ])
            ->assertRedirect();

        $this->forgetCache();
        $this->assertNotNull(Role::where('name', RoleEnum::Manager->value)->first());
        $this->assertNull(Role::where('name', 'Regional Boss')->first());

        $refreshed = Role::findByName(RoleEnum::Manager->value);
        $this->assertTrue($refreshed->hasPermissionTo(Permission::ReportsView->value));
        $this->assertFalse($refreshed->hasPermissionTo(Permission::EmployeesManage->value));
    }

    public function test_a_system_role_saves_when_the_form_sends_its_own_name_back()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $manager = Role::findByName(RoleEnum::Manager->value);

        // The edit dialog sends the fixed name "manager" with the
        // permissions (client report 2026-10-02: "reserved" error).
        $this->actingAs($superAdmin)
            ->put(route('roles.update', $manager), [
                'name' => RoleEnum::Manager->value,
                'permissions' => [Permission::EmployeesView->value, Permission::EmployeesCreate->value],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->forgetCache();
        $this->assertTrue(Role::findByName(RoleEnum::Manager->value)->hasPermissionTo(Permission::EmployeesCreate->value));
    }

    public function test_a_reserved_system_name_cannot_be_used_for_a_new_role()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('roles.store'), ['name' => RoleEnum::Manager->value])
            ->assertSessionHasErrors('name');

        $this->assertSame(4, Role::count());
    }

    public function test_a_system_role_cannot_be_deleted()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $manager = Role::findByName(RoleEnum::Manager->value);

        $this->actingAs($superAdmin)
            ->delete(route('roles.destroy', $manager))
            ->assertForbidden();

        $this->assertNotNull(Role::where('name', RoleEnum::Manager->value)->first());
    }

    public function test_a_custom_role_can_be_deleted()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Content Auditor');
        $this->forgetCache();

        $this->actingAs($superAdmin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles'));

        $this->assertNull(Role::where('name', 'Content Auditor')->first());
    }

    public function test_a_custom_role_that_is_still_assigned_is_not_deleted()
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $role = Role::findOrCreate('Content Auditor');
        $this->forgetCache();

        User::factory()->create()->assignRole($role);

        $this->actingAs($superAdmin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect();

        $this->assertNotNull(Role::where('name', 'Content Auditor')->first());
    }

    public function test_a_manager_cannot_create_a_role()
    {
        $this->actingAs(User::factory()->manager()->create())
            ->post(route('roles.store'), ['name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertNull(Role::where('name', 'Sneaky')->first());
    }
}
