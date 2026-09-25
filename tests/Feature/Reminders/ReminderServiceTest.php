<?php

namespace Tests\Feature\Reminders;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Jobs\SendReminderEmail;
use App\Mail\ReminderMail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use App\Services\Reminders\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Sending reminders: consent gate, channels, rendering, audit and the mail
 * job (REM-01, REM-04, REM-05, REM-06, PRIV-02, SEC-06; spec 0003 B.7).
 */
class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------- email gate

    public function test_a_consenting_employee_with_an_email_is_queued_and_the_job_dispatched()
    {
        Queue::fake();

        $user = $this->employee(['email_consent_at' => now()]);
        $template = ReminderTemplate::factory()->create();

        $reminders = $this->service()->send(new Collection([$user]), $template, ReminderChannel::Email, null);

        $this->assertCount(1, $reminders);
        $reminder = $reminders->first();

        $this->assertSame(ReminderStatus::Queued, $reminder?->status);
        $this->assertNull($reminder?->sent_at);
        $this->assertNull($reminder?->blocked_reason);
        $this->assertSame($template->id, $reminder?->template_id);

        Queue::assertPushed(
            SendReminderEmail::class,
            fn (SendReminderEmail $job): bool => $job->reminder->is($reminder),
        );
    }

    public function test_an_employee_without_consent_is_blocked_and_nothing_is_dispatched()
    {
        Queue::fake();

        $user = $this->employee(['email_consent_at' => null]);

        $reminder = $this->sendOne($user, ReminderChannel::Email);

        $this->assertSame(ReminderStatus::Blocked, $reminder->status);
        $this->assertSame(Reminder::BLOCKED_NO_CONSENT, $reminder->blocked_reason);
        $this->assertNull($reminder->sent_at);
        $this->assertDatabaseHas('reminders', ['user_id' => $user->id, 'status' => 'blocked']);

        Queue::assertNothingPushed();
    }

    public function test_an_employee_without_an_email_is_blocked_even_with_consent()
    {
        Queue::fake();

        // An employee signs in by username and may have no email (AUTH-01).
        $user = $this->employee(['email' => null, 'email_consent_at' => now()]);

        $reminder = $this->sendOne($user, ReminderChannel::Email);

        $this->assertSame(ReminderStatus::Blocked, $reminder->status);
        $this->assertSame(Reminder::BLOCKED_NO_EMAIL, $reminder->blocked_reason);

        Queue::assertNothingPushed();
    }

    public function test_one_send_writes_one_row_per_recipient_with_mixed_outcomes()
    {
        Queue::fake();

        $consenting = $this->employee(['email_consent_at' => now()]);
        $silent = $this->employee(['email_consent_at' => null]);

        $reminders = $this->service()->send(
            new Collection([$consenting, $silent]),
            ReminderTemplate::factory()->create(),
            ReminderChannel::Email,
            null,
        );

        $this->assertSame(
            [ReminderStatus::Queued, ReminderStatus::Blocked],
            $reminders->map(fn (Reminder $reminder) => $reminder->status)->all(),
        );
        Queue::assertPushed(SendReminderEmail::class, 1);
    }

    // ----------------------------------------------------------------- in-app

    public function test_in_app_reminders_are_sent_at_once_unread_and_need_no_consent()
    {
        Queue::fake();

        $user = $this->employee(['email_consent_at' => null]);

        $reminder = $this->sendOne($user, ReminderChannel::InApp);

        $this->assertSame(ReminderStatus::Sent, $reminder->status);
        $this->assertSame(ReminderChannel::InApp, $reminder->channel);
        $this->assertNotNull($reminder->sent_at);
        $this->assertNull($reminder->read_at);

        $this->assertSame(1, Reminder::query()->unreadInApp()->where('user_id', $user->id)->count());

        $reminder->markRead();
        $this->assertSame(0, Reminder::query()->unreadInApp()->where('user_id', $user->id)->count());

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------- rendering

    public function test_template_variables_are_rendered_per_recipient()
    {
        Queue::fake();

        $hotel = Hotel::factory()->active(30)->create(['name' => "La Gazelle d'Or"]);
        $department = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);
        $user = $this->employee([
            'name' => 'Amina Saadi',
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'email_consent_at' => now(),
        ]);

        $template = ReminderTemplate::factory()->create([
            'subject' => 'Hi {{name}}, a note from {{ hotel }}',
            'body' => "Dept: {{department}}\nProgress: {{progress}}%\nDays: {{days_remaining}}\nLogin: {{login_url}}\nKept: {{unknown}}",
        ]);

        $reminder = $this->sendOne($user, ReminderChannel::Email, $template);

        $this->assertSame("Hi Amina Saadi, a note from La Gazelle d'Or", $reminder->subject);
        $this->assertStringContainsString('Dept: Reception', $reminder->body);
        $this->assertMatchesRegularExpression('/Progress: \d+%/', $reminder->body);
        $this->assertStringContainsString('Days: 30', $reminder->body);
        $this->assertStringContainsString('Login: '.route('login'), $reminder->body);
        // An unknown placeholder is left visible rather than silently dropped.
        $this->assertStringContainsString('Kept: {{unknown}}', $reminder->body);
    }

    public function test_the_renderer_copes_with_no_hotel_and_no_department()
    {
        $user = $this->employee(['hotel_id' => null, 'department_id' => null]);

        $values = app(TemplateRenderer::class)->values($user);

        $this->assertSame('', $values['hotel']);
        $this->assertSame('', $values['department']);
        $this->assertSame('', $values['days_remaining']);
        $this->assertMatchesRegularExpression('/^\d+$/', $values['progress']);
    }

    // ------------------------------------------------------------------ audit

    public function test_every_send_writes_one_audit_row_with_the_recipient_count()
    {
        Queue::fake();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $template = ReminderTemplate::factory()->create();
        $recipients = new Collection([
            $this->employee(['email_consent_at' => now()]),
            $this->employee(['email_consent_at' => now()]),
            $this->employee(['email_consent_at' => null]),
        ]);

        $this->service()->send($recipients, $template, ReminderChannel::Email, $admin);

        $logs = AuditLog::query()->where('action', 'reminders.sent')->get();
        $this->assertCount(1, $logs);

        $log = $logs->first();
        $this->assertSame($template->getMorphClass(), $log?->auditable_type);
        $this->assertSame($template->id, $log?->auditable_id);
        $this->assertSame($admin->id, $log?->actor_id);
        $this->assertSame(3, $log?->changes['recipients'] ?? null);
        $this->assertSame(2, $log?->changes['queued'] ?? null);
        $this->assertSame(1, $log?->changes['blocked'] ?? null);
        $this->assertSame('email', $log?->changes['channel'] ?? null);
    }

    public function test_an_empty_send_writes_nothing()
    {
        $result = $this->service()->send(new Collection, ReminderTemplate::factory()->create(), ReminderChannel::Email, null);

        $this->assertTrue($result->isEmpty());
        $this->assertDatabaseCount('reminders', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    // ------------------------------------------------------------------- job

    public function test_the_email_job_sends_the_mail_and_marks_the_reminder_sent()
    {
        Mail::fake();

        $user = $this->employee(['email_consent_at' => now()]);
        $reminder = Reminder::factory()->queued()->for($user)->create(['subject' => 'Come back to Guesvia']);

        (new SendReminderEmail($reminder))->handle($this->service());

        Mail::assertSent(
            ReminderMail::class,
            fn (ReminderMail $mail): bool => $mail->hasTo((string) $user->email) && $mail->reminder->is($reminder),
        );

        $reminder->refresh();
        $this->assertSame(ReminderStatus::Sent, $reminder->status);
        $this->assertNotNull($reminder->sent_at);
    }

    public function test_the_email_job_re_checks_consent_at_send_time()
    {
        Mail::fake();

        // Queued while consenting, consent revoked before the worker ran.
        $user = $this->employee(['email_consent_at' => null]);
        $reminder = Reminder::factory()->queued()->for($user)->create();

        (new SendReminderEmail($reminder))->handle($this->service());

        Mail::assertNothingSent();
        $this->assertSame(ReminderStatus::Blocked, $reminder->fresh()?->status);
        $this->assertSame(Reminder::BLOCKED_NO_CONSENT, $reminder->fresh()?->blocked_reason);
    }

    public function test_the_email_job_never_sends_a_reminder_twice()
    {
        Mail::fake();

        $user = $this->employee(['email_consent_at' => now()]);
        $reminder = Reminder::factory()->for($user)->create(['status' => ReminderStatus::Sent]);

        (new SendReminderEmail($reminder))->handle($this->service());

        Mail::assertNothingSent();
    }

    public function test_the_email_job_marks_the_reminder_failed_when_every_try_is_spent()
    {
        $reminder = Reminder::factory()->queued()->create();

        (new SendReminderEmail($reminder))->failed(new RuntimeException('Connection refused'));

        $this->assertSame(ReminderStatus::Failed, $reminder->fresh()?->status);
        $this->assertSame('Connection refused', $reminder->fresh()?->blocked_reason);
    }

    public function test_the_mail_carries_the_stored_subject_and_body()
    {
        $reminder = Reminder::factory()->create([
            'subject' => 'Your training is waiting',
            'body' => "Hello Amina,\nCome back & finish lesson 2.",
        ]);

        $mail = new ReminderMail($reminder);

        $this->assertSame('Your training is waiting', $mail->envelope()->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Hello Amina,<br />', $html);
        $this->assertStringContainsString('Come back &amp; finish lesson 2.', $html);
    }

    // ---------------------------------------------------------------- helpers

    private function service(): ReminderService
    {
        return app(ReminderService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        return User::factory()->employee()->create($attributes);
    }

    private function sendOne(User $user, ReminderChannel $channel, ?ReminderTemplate $template = null): Reminder
    {
        $template ??= ReminderTemplate::factory()->create();

        $reminder = $this->service()
            ->send(new Collection([$user]), $template, $channel, null)
            ->first();

        $this->assertInstanceOf(Reminder::class, $reminder);

        return $reminder;
    }
}
