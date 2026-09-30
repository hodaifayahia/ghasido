<?php

namespace Tests\Feature\Admin\Messages;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Jobs\SendReminderEmail;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Support\SessionKey;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deleting a reminder template, an automation rule and one reminder log
 * entry from the Messages & Reminders modals (REM-03, REM-04, REM-06,
 * REM-07, SEC-01, SEC-06).
 *
 * Each delete is permission checked on the server, writes one audit row and
 * flashes a toast. A template an automation rule still sends is refused,
 * never cascaded: the rule would otherwise be deleted with it.
 */
class MessagesDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Hotel $mine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->superAdmin()->create();
        $this->mine = Hotel::factory()->create(['name' => 'Mine']);
    }

    // ------------------------------------------------------------ templates

    public function test_a_super_admin_deletes_an_unused_template_and_the_log_keeps_its_text()
    {
        $template = ReminderTemplate::factory()->create(['name' => 'Weekend nudge']);
        $reminder = Reminder::factory()->create([
            'user_id' => $this->employeeOf($this->mine)->id,
            'template_id' => $template->id,
            'subject' => 'Rendered subject',
        ]);

        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->delete(route('messages-reminders.templates.destroy', $template))
            ->assertRedirect(route('messages-reminders'));

        $this->assertModelMissing($template);
        $this->assertOneAuditRow($template, 'reminder_template.deleted');
        $this->assertToast('success', 'Weekend nudge was deleted.');

        // The log row stays, with the text the employee received.
        $reminder->refresh();
        $this->assertNull($reminder->template_id);
        $this->assertSame('Rendered subject', $reminder->subject);
    }

    public function test_a_template_still_used_by_a_rule_is_refused_and_the_rule_survives()
    {
        $template = ReminderTemplate::factory()->create(['name' => 'Weekend nudge']);
        $rule = AutomationRule::factory()->create(['template_id' => $template->id, 'name' => 'Inactive 5 days']);

        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->delete(route('messages-reminders.templates.destroy', $template))
            ->assertRedirect(route('messages-reminders'));

        $this->assertModelExists($template);
        $this->assertModelExists($rule);
        $this->assertSame(0, AuditLog::query()->where('action', 'reminder_template.deleted')->count());
        $this->assertToast(
            'error',
            'Weekend nudge is still used by these automation rules: Inactive 5 days. Point them at another template or delete them first.',
        );
    }

    public function test_a_manager_cannot_delete_a_template()
    {
        $template = ReminderTemplate::factory()->create();

        $this->actingAs($this->managerOf($this->mine))
            ->delete(route('messages-reminders.templates.destroy', $template))
            ->assertForbidden();

        $this->assertModelExists($template);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------------------------------------------------------------- rules

    public function test_a_super_admin_deletes_a_rule_and_its_reminders_stay_in_the_log()
    {
        $rule = AutomationRule::factory()->create(['name' => 'Inactive 5 days']);
        $reminder = Reminder::factory()->create([
            'user_id' => $this->employeeOf($this->mine)->id,
            'template_id' => $rule->template_id,
            'automation_rule_id' => $rule->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->delete(route('messages-reminders.rules.destroy', $rule))
            ->assertRedirect(route('messages-reminders'));

        $this->assertModelMissing($rule);
        $this->assertModelExists($reminder);
        $this->assertNotNull(ReminderTemplate::query()->find($rule->template_id), 'The template must stay.');
        $this->assertOneAuditRow($rule, 'automation_rule.deleted');
        $this->assertToast('success', 'Inactive 5 days was deleted.');
    }

    public function test_a_manager_cannot_delete_a_rule()
    {
        $rule = AutomationRule::factory()->create();

        $this->actingAs($this->managerOf($this->mine))
            ->delete(route('messages-reminders.rules.destroy', $rule))
            ->assertForbidden();

        $this->assertModelExists($rule);
        $this->assertSame(0, AuditLog::count());
    }

    // ------------------------------------------------------------------ log

    public function test_a_super_admin_deletes_one_log_entry_only()
    {
        $employee = $this->employeeOf($this->mine);
        $template = ReminderTemplate::factory()->create();
        $deleted = Reminder::factory()->create(['user_id' => $employee->id, 'template_id' => $template->id]);
        $kept = Reminder::factory()->create(['user_id' => $employee->id, 'template_id' => $template->id]);

        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->delete(route('messages-reminders.log.destroy', $deleted))
            ->assertRedirect(route('messages-reminders'));

        $this->assertModelMissing($deleted);
        $this->assertModelExists($kept);
        $this->assertModelExists($template);
        $this->assertModelExists($employee);
        $this->assertOneAuditRow($deleted, 'reminder.deleted');
        $this->assertToast('success', 'The reminder log entry was deleted.');
    }

    public function test_a_manager_deletes_a_log_entry_of_their_own_hotel_but_not_another()
    {
        $manager = $this->managerOf($this->mine);
        $own = Reminder::factory()->create(['user_id' => $this->employeeOf($this->mine)->id]);
        $foreign = Reminder::factory()->create([
            'user_id' => $this->employeeOf(Hotel::factory()->create(['name' => 'Theirs']))->id,
        ]);

        $this->actingAs($manager)
            ->delete(route('messages-reminders.log.destroy', $foreign))
            ->assertForbidden();
        $this->assertModelExists($foreign);

        $this->actingAs($manager)
            ->delete(route('messages-reminders.log.destroy', $own))
            ->assertRedirect();
        $this->assertModelMissing($own);
    }

    public function test_a_user_without_the_messages_permission_cannot_delete_a_log_entry()
    {
        RoleModel::findByName(Role::Manager->value)->revokePermissionTo(Permission::MessagesManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = $this->managerOf($this->mine);
        $reminder = Reminder::factory()->create(['user_id' => $this->employeeOf($this->mine)->id]);

        $this->actingAs($manager)
            ->delete(route('messages-reminders.log.destroy', $reminder))
            ->assertForbidden();

        $this->actingAs($this->employeeOf($this->mine))
            ->delete(route('messages-reminders.log.destroy', $reminder))
            ->assertForbidden();

        $this->assertModelExists($reminder);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_queued_reminder_deleted_from_the_log_is_never_mailed()
    {
        Mail::fake();

        $reminder = Reminder::factory()->create([
            'user_id' => $this->employeeOf($this->mine)->id,
            'status' => ReminderStatus::Queued,
            'sent_at' => null,
        ]);
        $job = new SendReminderEmail($reminder);

        $this->actingAs($this->admin)
            ->delete(route('messages-reminders.log.destroy', $reminder))
            ->assertRedirect();

        $this->assertTrue($job->deleteWhenMissingModels);
        $job->handle(app(ReminderService::class));

        Mail::assertNothingSent();
        $this->assertModelMissing($reminder);
    }

    public function test_the_log_falls_back_to_its_last_page_when_the_page_asked_for_is_empty()
    {
        $employee = $this->employeeOf($this->mine);
        Reminder::factory()->count(3)->create(['user_id' => $employee->id]);

        $this->actingAs($this->admin)
            ->get(route('messages-reminders', ['log_page' => 4]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs', 3)
                ->where('logsPagination.currentPage', 1)
                ->where('abilities.deleteLogs', true));
    }

    // --------------------------------------------------------------- helpers

    private function assertOneAuditRow(Model $model, string $action): void
    {
        $rows = AuditLog::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->where('action', $action)
            ->get();

        $this->assertCount(1, $rows, "Expected exactly one {$action} audit row.");
        $this->assertSame($this->admin->id, $rows->first()?->actor_id);
    }

    private function assertToast(string $type, string $message): void
    {
        /** @var array<string, mixed> $flash */
        $flash = session()->get(SessionKey::FLASH_DATA, []);
        $toast = $flash['toast'] ?? null;

        $this->assertIsArray($toast, 'No toast was flashed.');
        $this->assertSame($type, $toast['type'] ?? null);
        $this->assertSame($message, $toast['message'] ?? null);
    }

    private function managerOf(Hotel $hotel): User
    {
        return User::factory()->manager()->create([
            'hotel_id' => $hotel->id,
            'status' => AccountStatus::Active,
        ]);
    }

    private function employeeOf(Hotel $hotel): User
    {
        return User::factory()->employee()->withUsername()->create([
            'hotel_id' => $hotel->id,
            'department_id' => Department::factory()->create()->id,
            'status' => AccountStatus::Active,
            'email_consent_at' => now(),
        ]);
    }
}
