<?php

namespace Tests\Feature\Admin;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Contact Requests (user request 2026-09-26): every Contact Us message on its
 * own sidebar page with the sender's details, and each one emailed to the
 * business email set in Website Management.
 */
class ContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_super_admin_sees_every_request_with_the_senders_details()
    {
        $owner = User::factory()->superAdmin()->create();
        $older = $this->message(['name' => 'Karim', 'created_at' => now()->subDay()]);
        $this->message([
            'name' => 'Amina Benali',
            'email' => 'amina@example.com',
            'phone' => '+213 555 00 11 22',
            'organisation' => 'Hotel El Djazair',
            'employees' => '16–50',
            'message' => 'We would like a quote for our front office team.',
        ]);
        $older->forceFill(['read_at' => now()])->save();

        $this->actingAs($owner)
            ->get(route('contact-messages'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ContactMessages')
                ->where('show', 'all')
                ->where('counts.all', 2)
                ->where('counts.new', 1)
                ->has('messages.data', 2)
                ->where('messages.data.0.name', 'Amina Benali')
                ->where('messages.data.0.email', 'amina@example.com')
                ->where('messages.data.0.phone', '+213 555 00 11 22')
                ->where('messages.data.0.organisation', 'Hotel El Djazair')
                ->where('messages.data.0.employees', '16–50')
                ->where('messages.data.0.message', 'We would like a quote for our front office team.')
                ->where('messages.data.0.read', false)
                ->where('messages.data.1.name', 'Karim')
                ->where('messages.data.1.read', true));
    }

    public function test_the_new_filter_shows_only_unread_requests()
    {
        $owner = User::factory()->superAdmin()->create();
        $this->message(['name' => 'Unread']);
        $this->message(['name' => 'Already read', 'read_at' => now()]);

        $this->actingAs($owner)
            ->get(route('contact-messages', ['show' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('show', 'new')
                ->has('messages.data', 1)
                ->where('messages.data.0.name', 'Unread'));
    }

    public function test_marking_a_request_read_from_the_page_returns_to_it()
    {
        $owner = User::factory()->superAdmin()->create();
        $message = $this->message();

        $this->actingAs($owner)
            ->from(route('contact-messages'))
            ->patch(route('contact-messages.read', $message))
            ->assertRedirect(route('contact-messages'));

        $this->assertNotNull($message->fresh()?->read_at);
    }

    public function test_users_without_the_website_permission_get_a_403()
    {
        $this->message();

        foreach ([User::factory()->admin()->create(), User::factory()->manager()->create(), User::factory()->employee()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('contact-messages'))
                ->assertForbidden();
        }
    }

    public function test_guests_are_sent_to_the_login_page()
    {
        $this->get(route('contact-messages'))->assertRedirect(route('login'));
    }

    public function test_a_request_is_emailed_to_the_business_email_with_the_phone_number()
    {
        Mail::fake();
        $store = app(LandingPageContentStore::class);
        $content = $store->current();
        $content['support']['email'] = 'sales@ghasido.com';
        $store->save($content, User::factory()->superAdmin()->create());

        $this->from(route('contact'))
            ->post(route('contact.store'), [
                'name' => 'Amina Benali',
                'email' => 'amina@example.com',
                'phone' => '+213 555 00 11 22',
                'message' => 'Please call me about the Reception plan.',
            ])
            ->assertSessionHasNoErrors();

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            return $mail->hasTo('sales@ghasido.com')
                && $mail->hasReplyTo('amina@example.com')
                && str_contains($mail->render(), '+213 555 00 11 22');
        });
    }

    /** @param array<string, mixed> $attributes */
    private function message(array $attributes = []): ContactMessage
    {
        $message = new ContactMessage;
        $message->forceFill([
            'name' => 'Karim',
            'email' => 'karim@example.com',
            'phone' => '+213 555 12 34 56',
            'message' => 'Please call me back about the plans.',
            ...$attributes,
        ])->save();

        return $message;
    }
}
