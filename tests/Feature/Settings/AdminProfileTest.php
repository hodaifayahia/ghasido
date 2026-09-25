<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin accounts complete their contact profile before using the app
 * (owner request 2026-09-25): first and last name, email, phone and
 * address. Learners are never asked for a phone or an address (PRIV-03).
 */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_admin_with_an_incomplete_profile_is_sent_to_it()
    {
        $admin = User::factory()->superAdmin()->create(['phone' => null, 'address' => null]);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->actingAs($admin)->get(route('hotels'))->assertRedirect(route('profile.edit'));

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('adminProfile.incomplete', true));
    }

    public function test_a_hotel_admin_is_asked_too_but_not_a_manager_or_an_employee()
    {
        $hotelAdmin = User::factory()->admin()->create(['first_name' => null]);
        $this->actingAs($hotelAdmin)->get(route('dashboard'))->assertRedirect(route('profile.edit'));

        foreach ([User::factory()->manager()->create(), User::factory()->employee()->create()] as $user) {
            $this->actingAs($user)->get(route('profile.edit'))
                ->assertInertia(fn (Assert $page) => $page->where('adminProfile', null));
        }
    }

    public function test_completing_the_profile_opens_the_app_and_sets_the_name()
    {
        $admin = User::factory()->superAdmin()->create(['first_name' => null, 'last_name' => null, 'phone' => null, 'address' => null]);

        $this->actingAs($admin)
            ->patch(route('profile.update'), [
                'first_name' => 'Amina',
                'last_name' => 'Belkacem',
                'email' => 'amina@research.test',
                'phone' => '+213 555 12 34 56',
                'address' => '12 Rue Didouche Mourad, Algiers',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $admin->refresh();
        $this->assertSame('Amina Belkacem', $admin->name);
        $this->assertSame('+213 555 12 34 56', $admin->phone);
        $this->assertFalse($admin->adminProfileIncomplete());

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_every_admin_field_is_required()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('profile.update'), ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => 'call me', 'address' => ''])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone', 'address']);
    }

    public function test_an_employee_profile_takes_no_phone_or_address()
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->patch(route('profile.update'), [
                'name' => 'Samira Haddad',
                'email' => 'samira@hotel.test',
                'phone' => '+213 555 00 00 00',
                'address' => 'Somewhere',
            ])
            ->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertSame('Samira Haddad', $employee->name);
        $this->assertNull($employee->phone);
        $this->assertNull($employee->address);
    }
}
