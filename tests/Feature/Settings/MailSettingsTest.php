<?php

namespace Tests\Feature\Settings;

use App\Jobs\SendReminderEmail;
use App\Mail\HotelApprovedMail;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\MailLog;
use App\Models\MailSetting;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Mail\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings → Email (client request 2026-09-29): Super Admin only, the
 * password is encrypted and never sent back, the stored mailbox overrides
 * config('mail.*') on requests and in queue workers, "send immediately"
 * delivers queued emails at once while other jobs stay queued, and the
 * test email reports the mail server's answer.
 */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'mailer' => 'smtp',
            'host' => 'smtp.hostinger.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'contact@ghasido.com',
            'password' => 'Mailbox-Secret-42',
            'fromAddress' => 'contact@ghasido.com',
            'fromName' => 'GHASIDO',
            'replyTo' => '',
            'sendImmediately' => true,
        ], $overrides);
    }

    private function storeRow(array $values = []): MailSetting
    {
        return MailSetting::query()->create(array_merge([
            'mailer' => 'log',
            'host' => 'smtp.hostinger.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'contact@ghasido.com',
            'password' => 'Mailbox-Secret-42',
            'from_address' => 'contact@ghasido.com',
            'from_name' => 'GHASIDO',
            'send_immediately' => true,
        ], $values));
    }

    private function freshSettings(): MailSettings
    {
        $settings = app(MailSettings::class);
        $settings->refresh();

        return $settings;
    }

    public function test_the_super_admin_sees_the_page_with_hostinger_defaults()
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'boss@example.com']);

        $this->actingAs($admin)
            ->get(route('mail-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Email')
                ->where('settings.stored', false)
                ->where('settings.hasPassword', false)
                ->where('settings.values.host', 'smtp.hostinger.com')
                ->where('settings.values.port', 465)
                ->where('settings.values.encryption', 'ssl')
                ->where('settings.values.username', 'contact@ghasido.com')
                ->where('settings.values.fromAddress', 'contact@ghasido.com')
                ->where('settings.values.fromName', 'GHASIDO')
                ->where('settings.values.sendImmediately', true)
                ->where('defaultTestTo', 'boss@example.com')
            );
    }

    public function test_everyone_else_is_refused()
    {
        foreach (['admin', 'manager', 'employee'] as $state) {
            $user = User::factory()->{$state}()->create();

            $this->actingAs($user)->get(route('mail-settings.edit'))->assertForbidden();
            $this->actingAs($user)->patch(route('mail-settings.update'), $this->form())->assertForbidden();
            $this->actingAs($user)->post(route('mail-settings.test'), ['to' => 'a@example.com'])->assertForbidden();
        }

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_saving_encrypts_the_password_and_never_returns_or_logs_it()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('mail-settings.update'), $this->form())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $raw = DB::table('mail_settings')->value('password');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('Mailbox-Secret-42', $raw);
        $this->assertSame('Mailbox-Secret-42', Crypt::decryptString($raw));

        $page = $this->actingAs($admin)->get(route('mail-settings.edit'))->assertOk();
        $this->assertStringNotContainsString('Mailbox-Secret-42', (string) $page->getContent());
        $page->assertInertia(fn (Assert $page) => $page->where('settings.hasPassword', true)->where('settings.stored', true));

        $log = AuditLog::query()->where('action', 'mail-settings.updated')->sole();
        $this->assertStringNotContainsString('Mailbox-Secret-42', (string) json_encode($log->changes));
        $this->assertTrue($log->changes['password_changed']);
    }

    public function test_a_blank_password_keeps_the_saved_one()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->storeRow(['mailer' => 'smtp']);

        $this->actingAs($admin)
            ->patch(route('mail-settings.update'), $this->form(['password' => '', 'fromName' => 'GHASIDO Team']))
            ->assertSessionHasNoErrors();

        $row = MailSetting::query()->sole();
        $this->assertSame('Mailbox-Secret-42', $row->secret());
        $this->assertSame('GHASIDO Team', $row->from_name);
    }

    public function test_the_first_smtp_save_needs_a_password_and_a_matching_from_address()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('mail-settings.update'), $this->form(['password' => '', 'fromAddress' => 'hello@ghasido.com']))
            ->assertSessionHasErrors(['password', 'fromAddress']);

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_log_only_needs_no_server()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('mail-settings.update'), $this->form(['mailer' => 'log', 'host' => '', 'port' => null, 'username' => '', 'password' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('log', MailSetting::query()->sole()->mailer);
    }

    public function test_without_a_saved_row_env_applies_unchanged()
    {
        $this->freshSettings()->apply();

        $this->assertSame('array', config('mail.default'));
    }

    public function test_the_saved_mailbox_overrides_the_mail_config()
    {
        $this->storeRow(['mailer' => 'smtp', 'reply_to' => 'help@ghasido.com']);

        $this->freshSettings()->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.hostinger.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('contact@ghasido.com', config('mail.mailers.smtp.username'));
        $this->assertSame('Mailbox-Secret-42', config('mail.mailers.smtp.password'));
        $this->assertSame(['address' => 'contact@ghasido.com', 'name' => 'GHASIDO'], config('mail.from'));
        $this->assertSame('help@ghasido.com', config('mail.reply_to.address'));
    }

    public function test_tls_on_587_requires_starttls()
    {
        $this->storeRow(['mailer' => 'smtp', 'encryption' => 'tls', 'port' => 587]);

        $this->freshSettings()->apply();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertTrue(config('mail.mailers.smtp.require_tls'));
    }

    public function test_the_mail_manager_applies_it_when_first_resolved()
    {
        $this->storeRow(['from_address' => 'contact@ghasido.com', 'from_name' => 'GHASIDO']);
        config(['mail.from' => ['address' => 'hello@example.com', 'name' => 'Laravel']]);
        $this->app->forgetInstance(MailSettings::class);
        $this->app->forgetInstance('mail.manager');

        $this->app->make('mail.manager');

        $this->assertSame('log', config('mail.default'));
        $this->assertSame('contact@ghasido.com', config('mail.from.address'));
    }

    public function test_a_queue_worker_reads_the_settings_again_before_each_job()
    {
        $this->storeRow(['mailer' => 'smtp', 'from_address' => 'contact@ghasido.com']);
        config(['mail.default' => 'array', 'mail.from.address' => 'hello@example.com']);

        dispatch(function (): void {
            Cache::put('mail-test.seen', [config('mail.default'), config('mail.from.address')]);
        });

        $this->assertSame(['smtp', 'contact@ghasido.com'], Cache::get('mail-test.seen'));
    }

    public function test_immediate_mode_sends_queued_emails_now_while_other_jobs_stay_queued()
    {
        config(['queue.default' => 'database']);
        $this->storeRow(['mailer' => 'log', 'send_immediately' => true]);
        $this->freshSettings();
        Event::fake([MessageSent::class]);

        $manager = User::factory()->manager()->create();
        Mail::to('manager@example.com')->queue(new HotelApprovedMail(Hotel::factory()->create(), $manager));

        Event::assertDispatched(MessageSent::class);
        $this->assertDatabaseCount('jobs', 0);

        dispatch(function (): void {});

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_without_immediate_mode_emails_wait_on_the_queue()
    {
        config(['queue.default' => 'database']);
        $this->storeRow(['mailer' => 'log', 'send_immediately' => false]);
        $this->freshSettings();
        Event::fake([MessageSent::class]);

        $manager = User::factory()->manager()->create();
        Mail::to('manager@example.com')->queue(new HotelApprovedMail(Hotel::factory()->create(), $manager));

        Event::assertNotDispatched(MessageSent::class);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_an_immediate_send_that_fails_is_queued_instead_of_breaking_the_request()
    {
        config(['queue.default' => 'database']);
        $this->storeRow(['mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'send_immediately' => true]);
        $this->freshSettings();
        $this->app->make('mail.manager')->forgetMailers();

        $manager = User::factory()->manager()->create();
        Mail::to('manager@example.com')->queue(new HotelApprovedMail(Hotel::factory()->create(), $manager));

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_reminder_jobs_run_at_once_in_immediate_mode()
    {
        $reminder = Reminder::factory()->create();

        // Nothing saved yet: immediate, like the ticked box on the form.
        $this->assertSame('sync', (new SendReminderEmail($reminder))->connection);

        $row = $this->storeRow(['send_immediately' => false]);
        $this->freshSettings();

        $this->assertNull((new SendReminderEmail($reminder))->connection);

        $row->update(['send_immediately' => true]);
        $this->freshSettings();

        $job = new SendReminderEmail($reminder);
        $this->assertSame('sync', $job->connection);
        $this->assertTrue($job->immediate);
    }

    public function test_the_test_email_reports_success_and_is_audited()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->storeRow(['mailer' => 'log']);
        Event::fake([MessageSent::class]);

        $this->actingAs($admin)
            ->post(route('mail-settings.test'), ['to' => 'someone@gmail.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->getTo()[0]->getAddress() === 'someone@gmail.com');
        $this->assertSame('ok', MailSetting::query()->sole()->last_test['status'] ?? null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mail-settings.test_sent']);
    }

    public function test_the_test_email_explains_a_server_failure_in_plain_words()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->storeRow(['mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1]);

        $this->actingAs($admin)
            ->post(route('mail-settings.test'), ['to' => 'someone@gmail.com'])
            ->assertRedirect();

        $result = MailSetting::query()->sole()->last_test;
        $this->assertSame('failed', $result['status'] ?? null);
        $this->assertStringContainsString('Port 1 could not be reached', (string) $result['message']);
        $this->assertNotEmpty($result['detail']);
        $this->assertSame(['server' => 'ok', 'port' => 'failed', 'login' => 'skipped'], collect($result['steps'])->pluck('status', 'key')->all());
        $this->assertStringNotContainsString('Mailbox-Secret-42', (string) json_encode($result));
    }

    public function test_the_test_uses_the_form_on_screen_before_it_is_saved()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->storeRow(['mailer' => 'log']);

        $this->actingAs($admin)
            ->post(route('mail-settings.test'), [
                'to' => 'someone@gmail.com',
                'mailer' => 'smtp',
                'host' => '127.0.0.1',
                'port' => 1,
                'encryption' => 'ssl',
                'username' => 'contact@ghasido.com',
                'password' => 'Typed-Not-Saved-7',
            ])
            ->assertRedirect();

        $row = MailSetting::query()->sole();
        $this->assertSame('log', $row->mailer, 'the test never saves the form');
        $this->assertSame('failed', $row->last_test['status'] ?? null);
        $this->assertTrue($row->last_test['unsaved'] ?? false);
        $this->assertStringNotContainsString('Typed-Not-Saved-7', (string) json_encode($row->last_test));
    }

    public function test_the_test_says_when_no_password_is_saved_or_typed()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->storeRow(['mailer' => 'smtp', 'password' => null]);

        $this->actingAs($admin)
            ->post(route('mail-settings.test'), ['to' => 'someone@gmail.com'])
            ->assertRedirect();

        $result = MailSetting::query()->sole()->last_test;
        $this->assertSame('failed', $result['status'] ?? null);
        $this->assertSame('password', $result['steps'][0]['key'] ?? null);
    }

    public function test_every_email_lands_in_recent_emails()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->storeRow(['mailer' => 'log']);

        $this->actingAs($admin)
            ->post(route('mail-settings.test'), ['to' => 'someone@gmail.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('mail_logs', ['to' => 'someone@gmail.com', 'status' => MailLog::LOGGED, 'mailable' => 'TestEmailMail']);

        $this->actingAs($admin)
            ->get(route('mail-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent.0.to', 'someone@gmail.com')
                ->where('queue', ['waiting' => 0, 'failed' => 0]));
    }

    public function test_waiting_emails_are_sent_now_on_request()
    {
        config(['queue.default' => 'database']);
        $this->storeRow(['mailer' => 'log', 'send_immediately' => false]);
        $this->freshSettings();

        $manager = User::factory()->manager()->create();
        Mail::to('manager@example.com')->queue(new HotelApprovedMail(Hotel::factory()->create(), $manager));
        dispatch(function (): void {});
        $this->assertDatabaseCount('jobs', 2);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('mail-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('queue.waiting', 1));

        $this->actingAs($admin)
            ->post(route('mail-settings.flush'))
            ->assertRedirect();

        // The email left; the other job still waits for its worker.
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('mail_logs', ['to' => 'manager@example.com', 'status' => MailLog::LOGGED]);
    }

    public function test_a_waiting_email_that_fails_again_is_kept_for_the_next_try()
    {
        config(['queue.default' => 'database']);
        $this->storeRow(['mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'send_immediately' => false]);
        $this->freshSettings();

        $manager = User::factory()->manager()->create();
        Mail::to('manager@example.com')->queue(new HotelApprovedMail(Hotel::factory()->create(), $manager));

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('mail-settings.flush'))
            ->assertRedirect();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('mail_logs', ['status' => MailLog::FAILED]);
    }

    public function test_only_the_super_admin_may_send_waiting_emails()
    {
        $this->actingAs(User::factory()->manager()->create())
            ->post(route('mail-settings.flush'))
            ->assertForbidden();
    }

    public function test_the_test_email_needs_a_valid_address()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('mail-settings.test'), ['to' => 'not-an-email'])
            ->assertSessionHasErrors('to');
    }
}
