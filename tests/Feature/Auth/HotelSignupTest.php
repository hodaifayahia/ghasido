<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\HotelAccessState;
use App\Enums\Role;
use App\Mail\HotelApprovedMail;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HotelSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_request_a_hotel_and_an_inactive_manager_account_is_created()
    {
        $this->post(route('hotel-signup.store'), $this->validSignup())
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $hotel = Hotel::query()->where('name', 'Blue Coast Hotel')->firstOrFail();
        $manager = User::query()->where('username', 'blue.coast.manager')->firstOrFail();

        $this->assertSame(HotelAccessState::Pending, $hotel->access_state);
        $this->assertNull($hotel->contract_starts_on);
        $this->assertNull($hotel->contract_ends_on);

        $this->assertSame($hotel->id, $manager->hotel_id);
        $this->assertSame(AccountStatus::Inactive, $manager->status);
        $this->assertTrue($manager->hasRole(Role::Manager->value));
        $this->assertSame('manager@bluecoast.test', $manager->email);
    }

    public function test_a_requested_manager_cannot_sign_in_until_the_hotel_is_approved()
    {
        $this->post(route('hotel-signup.store'), $this->validSignup());

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'blue.coast.manager',
            'password' => 'SecretPass123',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => HotelAccessState::Pending->blockedMessage(),
        ]);
    }

    public function test_approving_a_requested_hotel_activates_the_manager_and_queues_the_email()
    {
        Mail::fake();

        $this->post(route('hotel-signup.store'), $this->validSignup());

        $owner = User::factory()->superAdmin()->create();
        $hotel = Hotel::query()->where('name', 'Blue Coast Hotel')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('hotels.approve', $hotel))
            ->assertRedirect();

        $manager = User::query()->where('username', 'blue.coast.manager')->firstOrFail();

        $this->assertSame(AccountStatus::Active, $manager->status);

        Mail::assertQueued(
            HotelApprovedMail::class,
            fn (HotelApprovedMail $mail): bool => $mail->hasTo('manager@bluecoast.test')
                && $mail->manager->is($manager)
                && $mail->hotel->is($hotel),
        );
    }

    /**
     * @return array<string, string>
     */
    private function validSignup(): array
    {
        return [
            'name' => 'Blue Coast Hotel',
            'city' => 'Oran',
            'manager_name' => 'Nassim Benali',
            'manager_email' => 'manager@bluecoast.test',
            'manager_username' => 'blue.coast.manager',
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
        ];
    }
}
