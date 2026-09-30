<?php

namespace Tests\Feature\Payments;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A hotel that just bought a plan is seen at once (client request
 * 2026-09-29): a bell notification for the Super Admin, and the hotel at
 * the top of the Hotels list while it waits for approval.
 */
class NewSignupVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();
    }

    public function test_a_new_hotel_request_rings_the_bell_and_tops_the_hotels_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Hotel::factory()->active()->create(['name' => 'Aaa Existing Hotel']);

        $this->post(route('checkout.store', 'gold'), [
            'name' => 'Zzz New Hotel',
            'city' => 'Oran',
            'manager_name' => 'Nassim Benali',
            'manager_email' => 'manager@zzz.test',
            'manager_username' => 'zzz.manager',
            'phone' => '+213 555 12 34 56',
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
            'proof' => UploadedFile::fake()->image('receipt.jpg')->size(120),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('hotels'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Alphabetically last, but first while it waits.
                ->where('hotels.0.name', 'Zzz New Hotel')
                ->where('notifications.unread', 1)
                ->where('notifications.items.0.subject', 'New payment from Zzz New Hotel'));
    }
}
