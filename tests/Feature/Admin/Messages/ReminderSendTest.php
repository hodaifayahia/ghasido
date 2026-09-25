<?php

namespace Tests\Feature\Admin\Messages;

use App\Enums\AccountStatus;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Jobs\SendReminderEmail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Reminders\AutomationRunner;
use App\Services\Reminders\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * Send Reminder from the Messages screen: the consent gate, the toast, the
 * audit row, "select all matching", scheduling and its release (REM-01,
 * REM-02, REM-05, REM-06, SEC-06; spec 0003 Part D).
 */
class ReminderSendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Hotel $hotel;

    private Department $department;

    private ReminderTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();
        Mail::fake();

        $this->admin = User::factory()->superAdmin()->create();
        $this->hotel = Hotel::factory()->create();
        $this->department = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);
        $this->template = ReminderTemplate::factory()->create(['name' => 'Training comeback reminder']);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------- consent gate

    public function test_a_send_queues_the_consenting_and_blocks_the_rest_with_one_audit_row()
    {
        $consenting = $this->employee(['email_consent_at' => now()]);
        $silent = $this->employee(['email_consent_at' => null]);

        $this->send(['recipients' => [$consenting->id, $silent->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $consenting->id,
            'template_id' => $this->template->id,
            'channel' => 'email',
            'status' => ReminderStatus::Queued->value,
            'sent_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('reminders', [
            'user_id' => $silent->id,
            'status' => ReminderStatus::Blocked->value,
            'blocked_reason' => Reminder::BLOCKED_NO_CONSENT,
        ]);

        Queue::assertPushed(SendReminderEmail::class, 1);
        Mail::assertNothingSent();

        $audit = AuditLog::query()->where('action', 'reminders.sent')->get();
        $this->assertCount(1, $audit);
        $this->assertSame($this->admin->id, $audit->first()?->actor_id);
        $this->assertSame(2, $audit->first()?->changes['recipients'] ?? null);
        $this->assertSame(1, $audit->first()?->changes['blocked'] ?? null);

        $this->assertToast('Sent to 1, blocked 1 (no consent).');
    }

    public function test_an_in_app_send_needs_no_consent_and_lands_on_the_employees_messages()
    {
        $silent = $this->employee(['email_consent_at' => null]);

        $this->send(['recipients' => [$silent->id], 'channel' => 'in_app'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $silent->id,
            'channel' => ReminderChannel::InApp->value,
            'status' => ReminderStatus::Sent->value,
        ]);
        Queue::assertNothingPushed();
        $this->assertToast('Sent to 1.');
    }

    public function test_a_deactivated_account_is_skipped_and_said_so()
    {
        $active = $this->employee(['email_consent_at' => now()]);
        $gone = $this->employee(['email_consent_at' => now(), 'status' => AccountStatus::Inactive]);

        $this->send(['recipients' => [$active->id, $gone->id]])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('reminders', ['user_id' => $gone->id]);
        $this->assertToast('Sent to 1, skipped 1 (deactivated).');
    }

    // -------------------------------------------------------- select all

    public function test_select_all_sends_to_everyone_matching_the_filters_on_the_server()
    {
        $idleConsenting = $this->employee(['email_consent_at' => now(), 'last_activity_at' => now()->subDays(9)]);
        $idleSilent = $this->employee(['email_consent_at' => null, 'last_activity_at' => now()->subDays(9)]);
        $recent = $this->employee(['email_consent_at' => now(), 'last_activity_at' => now()->subDay()]);

        $this->send([
            'select_all' => 1,
            'consent' => 'consent-granted',
            'activity' => 'inactive-5-days',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reminders', ['user_id' => $idleConsenting->id, 'status' => 'queued']);
        $this->assertDatabaseMissing('reminders', ['user_id' => $idleSilent->id]);
        $this->assertDatabaseMissing('reminders', ['user_id' => $recent->id]);
        $this->assertToast('Sent to 1.');
    }

    // ------------------------------------------------------------ validation

    public function test_the_form_needs_a_template_a_channel_and_somebody_to_send_to()
    {
        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->post(route('messages-reminders.send'), [])
            ->assertRedirect(route('messages-reminders'))
            ->assertSessionHasErrors(['template_id', 'channel', 'recipients']);

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_an_inactive_template_cannot_be_sent()
    {
        $retired = ReminderTemplate::factory()->inactive()->create();
        $employee = $this->employee(['email_consent_at' => now()]);

        $this->send(['recipients' => [$employee->id], 'template_id' => $retired->id])
            ->assertSessionHasErrors(['template_id']);
    }

    public function test_a_schedule_must_be_in_the_future()
    {
        $employee = $this->employee(['email_consent_at' => now()]);

        $this->send(['recipients' => [$employee->id], 'scheduled_for' => now()->subHour()->toDateTimeString()])
            ->assertSessionHasErrors(['scheduled_for']);
    }

    // ------------------------------------------------------------- schedule

    public function test_a_scheduled_send_writes_scheduled_rows_and_is_released_when_due()
    {
        Date::setTestNow('2026-09-18 09:00:00');

        $consenting = $this->employee(['email_consent_at' => now()]);
        $silent = $this->employee(['email_consent_at' => null]);
        $when = now()->addDay()->setTime(10, 30);

        $this->send([
            'recipients' => [$consenting->id, $silent->id],
            'scheduled_for' => $when->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $scheduled = Reminder::query()->where('user_id', $consenting->id)->firstOrFail();
        $this->assertSame(ReminderStatus::Scheduled, $scheduled->status);
        $this->assertSame('2026-09-19 10:30:00', $scheduled->scheduled_for?->toDateTimeString());
        // The consent rule is applied when the batch is written too (REM-05).
        $this->assertDatabaseHas('reminders', ['user_id' => $silent->id, 'status' => 'blocked']);

        Queue::assertPushed(
            SendReminderEmail::class,
            fn (SendReminderEmail $job): bool => $job->reminder->is($scheduled) && $job->delay !== null,
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'reminders.scheduled']);
        $this->assertToast('Scheduled for 1 on 19 Sep 2026 10:30, blocked 1 (no consent).');

        // Run early: still scheduled.
        (new SendReminderEmail($scheduled))->handle(app(ReminderService::class));
        $this->assertSame(ReminderStatus::Scheduled, $scheduled->fresh()?->status);
        Mail::assertNothingSent();

        // Its moment passes: the daily pass releases it into the mail queue.
        Date::setTestNow('2026-09-19 10:31:00');
        app(AutomationRunner::class)->run();

        $this->assertSame(ReminderStatus::Queued, $scheduled->fresh()?->status);
        Queue::assertPushed(SendReminderEmail::class, 2);
    }

    public function test_a_scheduled_in_app_reminder_is_sent_when_released()
    {
        Date::setTestNow('2026-09-18 09:00:00');

        $employee = $this->employee(['email_consent_at' => null]);

        $this->send([
            'recipients' => [$employee->id],
            'channel' => 'in_app',
            'scheduled_for' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $reminder = Reminder::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(ReminderStatus::Scheduled, $reminder->status);

        Date::setTestNow('2026-09-18 11:01:00');
        $this->assertSame(1, app(ReminderService::class)->releaseDue());

        $reminder->refresh();
        $this->assertSame(ReminderStatus::Sent, $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->assertSame(1, Reminder::query()->unreadInApp()->where('user_id', $employee->id)->count());
    }

    public function test_a_consent_revoked_while_scheduled_blocks_the_release()
    {
        Date::setTestNow('2026-09-18 09:00:00');

        $employee = $this->employee(['email_consent_at' => now()]);

        $this->send([
            'recipients' => [$employee->id],
            'scheduled_for' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $employee->forceFill(['email_consent_at' => null])->save();

        Date::setTestNow('2026-09-18 10:01:00');
        app(ReminderService::class)->releaseDue();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $employee->id,
            'status' => ReminderStatus::Blocked->value,
            'blocked_reason' => Reminder::BLOCKED_NO_CONSENT,
        ]);
        Queue::assertPushed(SendReminderEmail::class, 1);
    }

    // --------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->post(route('messages-reminders.send'), $payload + [
                'template_id' => $this->template->id,
                'channel' => 'email',
            ]);
    }

    /**
     * Inertia::flash() keeps its data under one session key until the next
     * response reads it; that is where the toast is.
     */
    private function assertToast(string $message): void
    {
        /** @var array<string, mixed> $flash */
        $flash = session()->get(SessionKey::FLASH_DATA, []);
        $toast = $flash['toast'] ?? null;

        $this->assertIsArray($toast, 'No toast was flashed.');
        $this->assertSame($message, $toast['message'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        return User::factory()->employee()->withUsername()->create($attributes + [
            'hotel_id' => $this->hotel->id,
            'department_id' => $this->department->id,
            'status' => AccountStatus::Active,
            'last_activity_at' => now()->subDays(9),
        ]);
    }
}
