<?php

namespace Tests\Feature\Admin\Users;

use App\Enums\Role as RoleEnum;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Users page lists only the people who run the platform (client
 * request 2026-09-29); hotel managers are managed from their hotel.
 */
class UsersListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_only_super_admins_and_admins_are_listed(): void
    {
        $owner = User::factory()->superAdmin()->create(['name' => 'Aaa Owner']);
        User::factory()->admin()->create(['name' => 'Bbb Admin']);
        User::factory()->manager()->create(['name' => 'Ccc Manager', 'hotel_id' => Hotel::factory()->create()->id]);
        User::factory()->employee()->create(['name' => 'Ddd Learner']);
        User::factory()->create(['name' => 'Eee No Role']);

        $this->actingAs($owner)
            ->get(route('users'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Users')
                ->has('accounts.data', 2)
                ->where('accounts.data.0.name', 'Aaa Owner')
                ->where('accounts.data.1.name', 'Bbb Admin')
                ->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain(RoleEnum::Manager->value)));
    }

    public function test_a_hotel_manager_cannot_be_edited_or_created_here(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->withUsername('hotel.manager')->create(['hotel_id' => Hotel::factory()->create()->id]);
        $managerRole = Role::findByName(RoleEnum::Manager->value);

        $this->actingAs($owner)
            ->put(route('users.update', $manager), [
                'name' => 'Changed',
                'username' => $manager->username,
                'email' => $manager->email,
                'role_id' => $managerRole->id,
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'New Manager',
                'username' => 'new.manager',
                'email' => 'new.manager@example.test',
                'role_id' => $managerRole->id,
                'password' => 'SecretPass123',
                'password_confirmation' => 'SecretPass123',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['username' => 'new.manager']);
    }
}
