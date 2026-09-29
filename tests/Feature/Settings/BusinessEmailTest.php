<?php

namespace Tests\Feature\Settings;

use App\Mail\ContactMessageMail;
use App\Mail\HotelApprovedMail;
use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * The business email and sender name set in Website Management: shown to
 * visitors and used on every outgoing email (user request 2026-09-26).
 */
class BusinessEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // The server sends for ghasido.com; tests capture mail in memory.
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@ghasido.com',
            'mail.from.name' => 'Server name',
        ]);
    }

    public function test_the_super_admin_saves_the_business_email_and_sender_name()
    {
        $admin = User::factory()->superAdmin()->create();
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['email'] = 'contact@ghasido.com';
        $content['support']['sender_name'] = 'GHASIDO Team';

        $this->actingAs($admin)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertRedirect(route('landing-page.edit'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('landing-page.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/LandingPage')
                ->where('content.support.email', 'contact@ghasido.com')
                ->where('content.support.sender_name', 'GHASIDO Team')
                ->where('mailSetup.from', 'contact@ghasido.com')
                ->where('mailSetup.fromName', 'GHASIDO Team')
                ->where('mailSetup.replyTo', null)
                ->where('mailSetup.sendsAsBusiness', true)
                ->where('mailSetup.delivering', false));

        // Visitors see the same address on the Contact Us page.
        $this->get(route('contact'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.support.email', 'contact@ghasido.com'));
    }

    public function test_a_sender_name_that_could_break_the_email_header_is_rejected()
    {
        $admin = User::factory()->superAdmin()->create();
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['sender_name'] = "GHASIDO <evil@example.com>\r\nBcc: x";

        $this->actingAs($admin)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertSessionHasErrors('content.support.sender_name');
    }

    public function test_emails_go_out_from_the_business_email_when_the_server_sends_for_its_domain()
    {
        $this->saveBusiness('contact@ghasido.com', 'GHASIDO Team');

        $this->sendHotelApproved();

        $email = $this->lastEmail();
        $this->assertSame('contact@ghasido.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('GHASIDO Team', $email->getFrom()[0]->getName());
        $this->assertSame([], $email->getReplyTo());

        // The signature carries the sender name and the business email.
        $html = (string) $email->getHtmlBody();
        $this->assertStringContainsString('GHASIDO Team', $html);
        $this->assertStringContainsString('mailto:contact@ghasido.com', $html);
    }

    public function test_a_business_email_on_another_domain_receives_the_replies()
    {
        $this->saveBusiness('hotel.team@gmail.com', 'GHASIDO Team');

        $this->sendHotelApproved();

        $email = $this->lastEmail();
        $this->assertSame('noreply@ghasido.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('GHASIDO Team', $email->getFrom()[0]->getName());
        $this->assertSame('hotel.team@gmail.com', $email->getReplyTo()[0]->getAddress());

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)
            ->get(route('landing-page.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailSetup.from', 'noreply@ghasido.com')
                ->where('mailSetup.replyTo', 'hotel.team@gmail.com')
                ->where('mailSetup.sendsAsBusiness', false)
                ->where('mailSetup.serverDomain', 'ghasido.com'));
    }

    public function test_a_contact_message_still_replies_to_the_visitor()
    {
        $this->saveBusiness('contact@ghasido.com', 'GHASIDO Team');
        $message = ContactMessage::query()->create([
            'name' => 'Amina Benali',
            'email' => 'amina@example.com',
            'message' => 'We would like a quote for our front office team.',
        ]);

        Mail::to('contact@ghasido.com')->sendNow(new ContactMessageMail($message));

        $email = $this->lastEmail();
        $this->assertSame('contact@ghasido.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('amina@example.com', $email->getReplyTo()[0]->getAddress());
    }

    public function test_notifications_such_as_the_password_reset_use_the_business_email_too()
    {
        $this->saveBusiness('contact@ghasido.com', 'GHASIDO Team');

        Notification::send(
            User::factory()->create(['email' => 'amine@example.com']),
            new ResetPassword('token'),
        );

        $email = $this->lastEmail();
        $this->assertSame('contact@ghasido.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('GHASIDO Team', $email->getFrom()[0]->getName());
    }

    public function test_without_a_business_email_the_server_address_sends()
    {
        $this->sendHotelApproved();

        $email = $this->lastEmail();
        $this->assertSame('noreply@ghasido.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('GHASIDO', $email->getFrom()[0]->getName());
        $this->assertSame([], $email->getReplyTo());
        $this->assertStringNotContainsString('Write to us at', (string) $email->getHtmlBody());
    }

    private function saveBusiness(string $email, string $senderName): void
    {
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['email'] = $email;
        $content['support']['sender_name'] = $senderName;

        app(LandingPageContentStore::class)->save($content, User::factory()->superAdmin()->create());
    }

    private function sendHotelApproved(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);

        Mail::to('manager@hotel.test')->sendNow(new HotelApprovedMail($hotel, $manager));
    }

    private function lastEmail(): Email
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $sent = $transport->messages()->last();
        $this->assertInstanceOf(SentMessage::class, $sent);

        $email = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);

        return $email;
    }
}
