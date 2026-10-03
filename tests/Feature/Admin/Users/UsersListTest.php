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

    public function test_a_hotel_admin_is_given_a_hotel_and_listed_with_it(): void
    {
        // Client report 2026-10-02: "there is a Hotel Admin role but no way
        // to give it to someone".
        $owner = User::factory()->superAdmin()->create();
        $hotel = Hotel::factory()->active()->create(['name' => 'Blue Coast']);
        $adminRole = Role::findByName(RoleEnum::Admin->value);

        $payload = [
            'name' => 'Hotel Boss',
            'username' => 'hotel.boss',
            'email' => 'boss@bluecoast.test',
            'role_id' => $adminRole->id,
            'password' => 'SecretPass123',
        ];

        // A Hotel Admin without a hotel is refused, in plain words.
        $this->actingAs($owner)->post(route('users.store'), $payload)->assertSessionHasErrors('hotel_id');

        $this->actingAs($owner)
            ->post(route('users.store'), [...$payload, 'hotel_id' => $hotel->id])
            ->assertSessionHasNoErrors();

        $boss = User::query()->where('username', 'hotel.boss')->firstOrFail();
        $this->assertSame($hotel->id, $boss->hotel_id);
        $this->assertTrue($boss->hasRole(RoleEnum::Admin->value));

        $this->actingAs($owner)
            ->get(route('users'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['username'] === 'hotel.boss' && $row['hotelName'] === 'Blue Coast'))
                ->where('hotels.0.label', 'Blue Coast'));
    }

    public function test_a_custom_role_can_be_given_with_or_without_a_hotel(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $hotel = Hotel::factory()->active()->create();
        $custom = Role::create(['name' => 'Front office lead', 'guard_name' => 'web']);

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'Lead One',
                'username' => 'lead.one',
                'role_id' => $custom->id,
                'hotel_id' => $hotel->id,
                'password' => 'SecretPass123',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'Lead Two',
                'username' => 'lead.two',
                'role_id' => $custom->id,
                'password' => 'SecretPass123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($hotel->id, User::query()->where('username', 'lead.one')->value('hotel_id'));
        $this->assertNull(User::query()->where('username', 'lead.two')->value('hotel_id'));
        $this->assertTrue(User::query()->where('username', 'lead.one')->firstOrFail()->hasRole('Front office lead'));
    }
}
