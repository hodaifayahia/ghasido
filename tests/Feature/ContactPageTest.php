<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public Contact Us page and its settings (client decision 2026-09-26).
 */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_anyone_can_open_the_contact_page_with_the_configured_phone_and_email()
    {
        $this->saveSupport('+213 555 12 34 56', 'hello@ghasido.com');

        $this->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contact')
                ->where('content.support.phone', '+213 555 12 34 56')
                ->where('content.support.email', 'hello@ghasido.com')
                ->has('content.contact.enterprise_points', 4));
    }

    public function test_a_message_is_stored_and_forwarded_to_the_support_email()
    {
        Mail::fake();
        $this->saveSupport('', 'hello@ghasido.com');

        $this->from(route('contact'))
            ->post(route('contact.store'), [
                'name' => 'Amina Benali',
                'email' => 'amina@example.com',
                'phone' => '+213 555 00 11 22',
                'organisation' => 'Hotel El Djazair',
                'employees' => '16–50',
                'message' => 'We would like a quote for our front office team.',
            ])
            ->assertRedirect(route('contact'))
            ->assertSessionHasNoErrors();

        $message = ContactMessage::query()->sole();
        $this->assertSame('Amina Benali', $message->name);
        $this->assertSame('Hotel El Djazair', $message->organisation);

        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('hello@ghasido.com'));
    }

    public function test_invalid_or_bot_messages_are_rejected()
    {
        $this->from(route('contact'))
            ->post(route('contact.store'), ['name' => '', 'email' => 'nope', 'message' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->from(route('contact'))
            ->post(route('contact.store'), [
                'name' => 'Bot',
                'email' => 'bot@example.com',
                'message' => 'Buy cheap things now, great offer inside.',
                'website' => 'https://spam.example',
            ])
            ->assertSessionHasErrors(['website']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_the_super_admin_sets_the_phone_and_email_and_reads_messages()
    {
        $owner = User::factory()->superAdmin()->create();
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['phone'] = '+213 21 00 00 00';
        $content['support']['email'] = 'contact@ghasido.com';

        $this->actingAs($owner)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertSessionHasNoErrors();

        $saved = app(LandingPageContentStore::class)->current();
        $this->assertSame('+213 21 00 00 00', $saved['support']['phone']);
        $this->assertSame('contact@ghasido.com', $saved['support']['email']);

        $message = ContactMessage::query()->create([
            'name' => 'Karim',
            'email' => 'karim@example.com',
            'message' => 'Please call me back about the plans.',
        ]);

        $this->actingAs($owner)
            ->get(route('landing-page.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/LandingPage')
                ->where('contactMessages.0.email', 'karim@example.com')
                ->where('contactMessages.0.read', false));

        $this->actingAs($owner)
            ->patch(route('contact-messages.read', $message))
            ->assertRedirect();

        $this->assertNotNull($message->fresh()?->read_at);
    }

    public function test_an_invalid_support_email_is_rejected()
    {
        $owner = User::factory()->superAdmin()->create();
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['email'] = 'not-an-email';

        $this->actingAs($owner)
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertSessionHasErrors(['content.support.email']);
    }

    public function test_a_user_without_the_landing_permission_cannot_read_messages()
    {
        $employee = User::factory()->employee()->create();
        $message = ContactMessage::query()->create([
            'name' => 'Karim',
            'email' => 'karim@example.com',
            'message' => 'Please call me back about the plans.',
        ]);

        $this->actingAs($employee)
            ->patch(route('contact-messages.read', $message))
            ->assertForbidden();

        $this->assertNull($message->fresh()?->read_at);
    }

    public function test_the_landing_page_offers_both_currencies()
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('plans.0.priceUsd')
                ->where('content.pricing.region_algeria', 'Algeria (DZD)')
                ->where('content.pricing.region_international', 'International (USD)'));
    }

    private function saveSupport(string $phone, string $email): void
    {
        $store = app(LandingPageContentStore::class);
        $content = $store->current();
        $content['support']['phone'] = $phone;
        $content['support']['email'] = $email;
        $store->save($content, User::factory()->superAdmin()->create());
    }
}
