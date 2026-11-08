<?php

namespace Tests\Feature\Security;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): a delegate granted roles.manage or
 * users.manage cannot hand out, or act on, more than they hold themselves
 * (ROLE-01, SEC-01).
 */
class TenancyRolesTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $delegate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->hotel = Hotel::factory()->create();
        $this->delegate = User::factory()->admin()->forHotel($this->hotel, Department::factory()->create())->create();
        $this->delegate->givePermissionTo(
            Permission::RolesView->value,
            Permission::RolesManage->value,
            Permission::UsersView->value,
            Permission::UsersManage->value,
        );
        $this->forgetCache();
    }

    public function test_a_delegate_cannot_create_a_role_with_permissions_they_do_not_hold()
    {
        $this->actingAs($this->delegate)
            ->post(route('roles.store'), ['name' => 'Escalated', 'permissions' => [Permission::HotelsManage->value]])
            ->assertForbidden();

        $this->assertSame(0, Role::query()->where('name', 'Escalated')->count());

        // Roles are platform-wide: a hotel-bound delegate creates none
        // (security audit pass 3); platform staff stay within their ceiling.
        $this->actingAs($this->delegate)
            ->post(route('roles.store'), ['name' => 'Front Desk Lead', 'permissions' => [Permission::EmployeesView->value]])
            ->assertForbidden();

        $this->actingAs($this->platformDelegate())
            ->post(route('roles.store'), ['name' => 'Front Desk Lead', 'permissions' => [Permission::EmployeesView->value]])
            ->assertRedirect(route('roles'));
    }

    public function test_a_delegate_cannot_raise_their_own_role()
    {
        $admin = Role::findByName(RoleEnum::Admin->value);

        $this->actingAs($this->delegate)
            ->put(route('roles.update', $admin), ['permissions' => [...RoleEnum::Admin->permissionNames(), Permission::HotelsManage->value]])
            ->assertForbidden();

        $this->forgetCache();
        $this->assertFalse($admin->fresh()?->hasPermissionTo(Permission::HotelsManage->value));
    }

    public function test_a_delegate_cannot_edit_the_super_admin_role()
    {
        $this->actingAs($this->delegate)
            ->put(route('roles.update', Role::findByName(RoleEnum::SuperAdmin->value)), ['permissions' => []])
            ->assertForbidden();
    }

    public function test_a_delegate_grants_another_role_only_permissions_they_hold()
    {
        $manager = Role::findByName(RoleEnum::Manager->value);

        $this->actingAs($this->delegate)
            ->put(route('roles.update', $manager), ['permissions' => [...RoleEnum::Manager->permissionNames(), Permission::TranscriptsView->value]])
            ->assertForbidden();

        $this->forgetCache();
        $this->assertFalse($manager->fresh()?->hasPermissionTo(Permission::TranscriptsView->value));

        // System roles hold accounts in every hotel: no delegate edits them
        // (security audit pass 3), not even within their ceiling.
        $this->actingAs($this->delegate)
            ->put(route('roles.update', $manager), ['permissions' => [...RoleEnum::Manager->permissionNames(), Permission::ReportsView->value]])
            ->assertForbidden();

        $employee = Role::findByName(RoleEnum::Employee->value);
        $platform = $this->platformDelegate();

        $this->actingAs($platform)
            ->put(route('roles.update', $employee), ['permissions' => [Permission::EmployeesView->value]])
            ->assertForbidden();

        $custom = Role::create(['name' => 'Front Desk']);
        $this->forgetCache();

        $this->actingAs($platform)
            ->put(route('roles.update', $custom), ['name' => 'Front Desk', 'permissions' => [Permission::EmployeesView->value]])
            ->assertRedirect();

        $this->actingAs($platform)
            ->put(route('roles.update', $custom), ['name' => 'Front Desk', 'permissions' => [Permission::TranscriptsView->value]])
            ->assertForbidden();

        $this->forgetCache();
        $this->assertTrue($custom->fresh()?->hasPermissionTo(Permission::EmployeesView->value));
        $this->assertFalse($custom->fresh()?->hasPermissionTo(Permission::TranscriptsView->value));
    }

    private function platformDelegate(): User
    {
        $role = Role::create(['name' => 'Platform Staff']);
        $role->syncPermissions([
            Permission::RolesView->value,
            Permission::RolesManage->value,
            Permission::EmployeesView->value,
        ]);
        $user = User::factory()->withAdminProfile()->create(['hotel_id' => null]);
        $user->syncRoles(['Platform Staff']);
        $this->forgetCache();

        return $user;
    }

    public function test_a_delegate_cannot_reset_the_password_of_a_more_privileged_account()
    {
        $lead = Role::create(['name' => 'Ops Lead']);
        $lead->syncPermissions([Permission::UsersManage->value, Permission::HotelsManage->value]);
        $target = User::factory()->withAdminProfile()->create([
            'hotel_id' => $this->hotel->id,
            'password' => 'original-password',
        ]);
        $target->syncRoles(['Ops Lead']);
        $this->forgetCache();

        $this->actingAs($this->delegate)
            ->put(route('users.update', $target), [
                'name' => $target->name,
                'username' => $target->username ?? 'ops.lead',
                'email' => $target->email,
                'password' => 'taken-over-123',
                'role_id' => Role::findByName(RoleEnum::Admin->value)->id,
                'status' => AccountStatus::Active->value,
            ])
            ->assertForbidden();

        $target->refresh();
        $this->assertTrue(Hash::check('original-password', $target->password));
        $this->assertSame('Ops Lead', $target->roleName());
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
