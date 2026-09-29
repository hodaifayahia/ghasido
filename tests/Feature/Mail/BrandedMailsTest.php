<?php

namespace Tests\Feature\Mail;

use App\Mail\AccountRejectedMail;
use App\Mail\ApiCreditAlertMail;
use App\Mail\BrandedMailable;
use App\Mail\ContactMessageMail;
use App\Mail\CustomerMessageMail;
use App\Mail\HotelApprovedMail;
use App\Mail\IndividualApprovedMail;
use App\Mail\PaymentSubmittedMail;
use App\Mail\ReminderMail;
use App\Mail\TestEmailMail;
use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\PaymentSubmission;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

/**
 * Every GHASIDO email (client request 2026-09-29) renders in the branded
 * layout with the logo, an HTML and a plain-text part, in English and in
 * Arabic laid out right to left; Laravel's password reset mail too.
 */
class BrandedMailsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, BrandedMailable>
     */
    private function mailables(): array
    {
        $hotel = Hotel::factory()->make(['name' => 'Sunset Bay Resort']);
        $user = User::factory()->make(['name' => 'Leila Ben Ali', 'username' => 'leila.benali']);
        $contact = new ContactMessage(['name' => 'Omar', 'email' => 'omar@example.com', 'message' => "Hello\nWorld"]);
        $reminder = Reminder::factory()->make(['subject' => 'Your next lesson', 'body' => "Hi Leila,\nSee https://example.com/login"]);
        $payment = new PaymentSubmission;
        $payment->forceFill(['plan_name' => 'Hotel Pro', 'payer_name' => 'Omar', 'amount' => 1200, 'currency' => 'USD', 'reference' => 'TX-1', 'method' => 'bank_transfer']);
        $payment->id = 7;
        $payment->setRelation('hotel', $hotel);
        $summary = [
            'account' => 'qwen', 'service' => 'Qwen AI', 'provider' => 'Alibaba', 'state' => 'low', 'mode' => 'real',
            'creditUsd' => 50.0, 'remainingUsd' => 9.5, 'usedUsd' => 40.5, 'shareLeft' => 0.19,
            'meters' => [['meter' => 'tokens', 'label' => 'Tokens', 'granted' => 1000, 'left' => 190]],
        ];

        return [
            'rejected' => new AccountRejectedMail('Omar', 'Dar El Marsa', 'Wrong amount'),
            'credit' => new ApiCreditAlertMail('low', $summary, false, 'Leila'),
            'contact' => new ContactMessageMail($contact),
            'customer' => new CustomerMessageMail('Omar', 'About your plan', 'Thank you.'),
            'hotel' => new HotelApprovedMail($hotel, $user),
            'individual' => new IndividualApprovedMail($user, 'Individual'),
            'payment' => new PaymentSubmittedMail($payment),
            'reminder' => new ReminderMail($reminder),
            'test' => new TestEmailMail('smtp.hostinger.com:465', 'GHASIDO <contact@ghasido.com>', '2026-09-29 10:00'),
        ];
    }

    public function test_every_mail_has_the_branded_html_and_a_text_part_in_english()
    {
        foreach ($this->mailables() as $name => $mail) {
            $mail->locale('en');

            $mail->assertSeeInHtml('/brand/email/ghasido-logo.png', false);
            $mail->assertSeeInHtml('dir="ltr"', false);
            $mail->assertSeeInHtml('All rights reserved.', false);
            $mail->assertSeeInText('GHASIDO');
            $mail->assertDontSeeInText('<table');

            $this->assertStringStartsWith('mail.text.', (string) $mail->content()->text, $name);
        }
    }

    public function test_every_mail_is_right_to_left_in_arabic()
    {
        foreach ($this->mailables() as $mail) {
            $mail->locale('ar');

            $mail->assertSeeInHtml('dir="rtl"', false);
            $mail->assertSeeInHtml('lang="ar"', false);
            $mail->assertSeeInHtml("'Cairo'", false);
            $mail->assertSeeInHtml('/brand/email/ghasido-logo.png', false);
        }
    }

    public function test_images_use_the_absolute_app_url()
    {
        config(['app.url' => 'https://ghasido.com']);

        $mail = new CustomerMessageMail('Omar', 'Hello', 'Body');

        $mail->assertSeeInHtml('src="https://ghasido.com/brand/email/ghasido-logo.png"', false);
        $this->assertFileExists(public_path('brand/email/ghasido-logo.png'));
        $this->assertFileExists(public_path('brand/email/palm-island-tagline.png'));
    }

    public function test_person_written_text_is_escaped_and_links_are_clickable()
    {
        $mail = new CustomerMessageMail('Omar', 'Hi', "<script>x</script>\nRead https://ghasido.com/help");

        $mail->assertDontSeeInHtml('<script>x</script>', false);
        $mail->assertSeeInHtml('<a href="https://ghasido.com/help"', false);
    }

    public function test_reminders_carry_a_list_unsubscribe_header_to_the_profile()
    {
        config(['mail.from.address' => 'contact@ghasido.com']);
        $mail = new ReminderMail(Reminder::factory()->make());

        $header = $mail->headers()->text['List-Unsubscribe'] ?? '';

        $this->assertStringContainsString(route('profile.edit'), $header);
        $this->assertStringContainsString('mailto:contact@ghasido.com', $header);
    }

    public function test_the_password_reset_mail_uses_the_branded_layout()
    {
        $user = User::factory()->make(['email' => 'leila@example.com', 'locale' => 'ar']);
        $this->assertSame('ar', $user->preferredLocale());

        foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $dir) {
            app()->setLocale($locale);
            $message = (new ResetPassword('token-123'))->toMail($user);

            $html = (string) $message->render();
            $this->assertStringContainsString('/brand/email/ghasido-logo.png', $html);
            $this->assertStringContainsString('dir="'.$dir.'"', $html);
            $this->assertStringContainsString('token-123', $html);
            $this->assertStringContainsString('v:roundrect', $html);

            $text = (string) app(Markdown::class)->renderText((string) $message->markdown, $message->data());
            $this->assertStringContainsString('token-123', $text);
            $this->assertStringNotContainsString('<table', $text);
        }
    }
}
