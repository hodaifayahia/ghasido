<?php

namespace Tests\Feature\Auth;

use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client requests 2026-10-01:
 * - a customer says which helper languages they need when they subscribe,
 *   and the Super Admin sees them on the request, with a shortcut to
 *   translate into each or to add one that is not offered yet;
 * - every new request rings the Super Admin's bell, including a hotel
 *   request sent without a payment and a contact message.
 */
class SignupHelperLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        Storage::fake('local');
    }

    /** @return array<string, mixed> */
    private function hotelSignup(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Blue Coast Hotel',
            'city' => 'Oran',
            'manager_name' => 'Nassim Benali',
            'manager_email' => 'manager@bluecoast.test',
            'manager_username' => 'blue.coast.manager',
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
        ], $overrides);
    }

    public function test_the_sign_up_forms_offer_the_helper_languages_with_flags()
    {
        $this->get(route('hotel-signup'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('helperLanguages.0.value', 'ar')
                ->where('helperLanguages.0.flag', 'dz')
                ->where('helperLanguages.1.value', 'fr'));
    }

    public function test_a_hotel_request_keeps_the_languages_and_rings_the_bell()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post(route('hotel-signup.store'), $this->hotelSignup([
            'helper_languages' => ['fr'],
            'helper_language_other' => 'Turkish',
        ]))->assertSessionHasNoErrors();

        $manager = User::query()->where('username', 'blue.coast.manager')->firstOrFail();
        $this->assertSame([['code' => 'fr', 'name' => 'French'], ['code' => null, 'name' => 'Turkish']], $manager->requested_helper_languages);
        $this->assertSame('fr', $manager->meaning_locale);

        $hotel = Hotel::query()->where('name', 'Blue Coast Hotel')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('hotels.show', $hotel))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 1)
                ->where('notifications.items.0.subject', 'New hotel request: Blue Coast Hotel')
                ->where('approval.helperLanguages.0.code', 'fr')
                ->where('approval.helperLanguages.0.offered', true)
                ->where('approval.helperLanguages.0.translationsUrl', route('translations', ['language' => 'fr']))
                ->where('approval.helperLanguages.1.name', 'Turkish')
                ->where('approval.helperLanguages.1.offered', false));

        $this->actingAs($admin)->post(route('hotels.request-seen', $hotel))->assertRedirect();

        $this->assertNotNull($hotel->refresh()->request_read_at);
        $this->actingAs($admin)
            ->get(route('hotels.show', $hotel))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0));
    }

    public function test_the_languages_are_optional_and_checked()
    {
        $this->post(route('hotel-signup.store'), $this->hotelSignup())->assertSessionHasNoErrors();
        $this->assertNull(User::query()->where('username', 'blue.coast.manager')->value('requested_helper_languages'));

        $this->post(route('hotel-signup.store'), $this->hotelSignup([
            'manager_email' => 'other@bluecoast.test',
            'manager_username' => 'other.manager',
            'helper_languages' => ['xx'],
        ]))->assertSessionHasErrors('helper_languages.0');
    }

    public function test_a_checkout_shows_the_languages_on_the_payment()
    {
        $admin = User::factory()->superAdmin()->create();

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
            'helper_languages' => ['ar', 'fr'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('payments'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.0.helperLanguages.0.code', 'ar')
                ->where('payments.0.helperLanguages.1.code', 'fr')
                // The payment rings the bell, not a second hotel item.
                ->where('notifications.unread', 1));
    }

    public function test_a_contact_message_rings_the_bell_until_seen()
    {
        $admin = User::factory()->superAdmin()->create();
        $message = ContactMessage::query()->create([
            'name' => 'Amina Benali',
            'email' => 'amina@example.com',
            'message' => 'We would like a quote for our front office team.',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 1)
                ->where('notifications.items.0.subject', 'New message from Amina Benali'));

        $this->actingAs($admin)->post(route('contact-messages.seen', $message))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0));
    }

    public function test_a_manager_never_sees_another_hotels_request_in_the_bell()
    {
        $this->post(route('hotel-signup.store'), $this->hotelSignup())->assertSessionHasNoErrors();

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0));
    }
}
