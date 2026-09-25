<?php

namespace Tests\Feature\Admin\Messages;

use App\Enums\AutomationTrigger;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\ReminderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Templates and automation rules: add, edit, preview, toggle, each write
 * with exactly one audit row (REM-03, REM-04, SEC-06; spec 0003 Part D).
 */
class TemplateAndRuleActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->superAdmin()->create();
    }

    // ------------------------------------------------------------ templates

    public function test_adding_a_template_writes_the_row_and_an_audit_row()
    {
        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->post(route('messages-reminders.templates.store'), [
                'name' => 'Weekend nudge',
                'subject' => 'Hi {{name}}',
                'body' => "Come back to {{hotel}}.\n{{login_url}}",
                'audience_label' => 'Everyone',
                'trigger_label' => '',
            ])
            ->assertRedirect(route('messages-reminders'))
            ->assertSessionHasNoErrors();

        $template = ReminderTemplate::query()->where('name', 'Weekend nudge')->firstOrFail();
        $this->assertSame('Everyone', $template->audience_label);
        $this->assertNull($template->trigger_label);
        $this->assertTrue($template->is_active);
        $this->assertOneAuditRow($template, 'reminder_template.created');
    }

    public function test_editing_a_template_keeps_what_was_already_sent()
    {
        $template = ReminderTemplate::factory()->create(['name' => 'Old name', 'subject' => 'Old subject']);
        $employee = User::factory()->employee()->create(['email_consent_at' => now()]);
        $reminder = $template->reminders()->create([
            'user_id' => $employee->id,
            'channel' => 'email',
            'subject' => 'Old subject rendered',
            'body' => 'Old body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('messages-reminders.templates.update', $template), [
                'name' => 'New name',
                'subject' => 'New subject',
                'body' => 'New body',
                'is_active' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('New name', $template->name);
        $this->assertFalse($template->is_active);
        $this->assertSame('Old subject rendered', $reminder->fresh()?->subject);
        $this->assertOneAuditRow($template, 'reminder_template.updated');

        $log = AuditLog::query()->where('action', 'reminder_template.updated')->firstOrFail();
        $this->assertSame('Old name', $log->changes['attributes']['name']['from'] ?? null);
        $this->assertSame('New name', $log->changes['attributes']['name']['to'] ?? null);
    }

    public function test_a_template_needs_a_name_subject_and_body()
    {
        $this->actingAs($this->admin)
            ->post(route('messages-reminders.templates.store'), ['name' => '', 'subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['name', 'subject', 'body']);

        $this->assertDatabaseCount('reminder_templates', 0);
    }

    public function test_the_preview_fills_the_variables_for_a_sample_employee()
    {
        $hotel = Hotel::factory()->active(30)->create(['name' => "La Gazelle d'Or"]);
        $department = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);
        User::factory()->employee()->create(['name' => 'Zed Last', 'hotel_id' => $hotel->id, 'department_id' => $department->id]);
        $amina = User::factory()->employee()->create(['name' => 'Amina Saadi', 'hotel_id' => $hotel->id, 'department_id' => $department->id]);

        $this->actingAs($this->admin)
            ->get(route('messages-reminders.templates.preview', [
                'subject' => 'Hi {{name}} from {{hotel}}',
                'body' => '{{department}} · {{days_remaining}} days · {{unknown}}',
                'recipient' => $amina->id,
            ]))
            ->assertOk()
            ->assertJsonPath('sample.name', 'Amina Saadi')
            ->assertJsonPath('subject', "Hi Amina Saadi from La Gazelle d'Or")
            ->assertJsonPath('body', 'Reception · 30 days · {{unknown}}')
            ->assertJsonPath('values.name', 'Amina Saadi');

        // No recipient given: the first employee by name.
        $this->actingAs($this->admin)
            ->get(route('messages-reminders.templates.preview', ['subject' => '{{name}}']))
            ->assertJsonPath('subject', 'Amina Saadi');
    }

    public function test_the_preview_copes_with_no_employees()
    {
        $this->actingAs($this->admin)
            ->get(route('messages-reminders.templates.preview', ['subject' => 'Hi {{name}}']))
            ->assertOk()
            ->assertJsonPath('sample', null)
            ->assertJsonPath('subject', 'Hi {{name}}');
    }

    // ---------------------------------------------------------------- rules

    public function test_adding_a_rule_derives_its_audience_label_and_audits_it()
    {
        $template = ReminderTemplate::factory()->create();
        $gazelle = Hotel::factory()->create(['name' => "La Gazelle d'Or"]);
        $reception = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);
        $spa = Department::factory()->create(['name' => 'Spa', 'slug' => 'spa']);

        $this->actingAs($this->admin)
            ->post(route('messages-reminders.rules.store'), [
                'name' => 'Spa comeback',
                'trigger' => AutomationTrigger::InactiveDays->value,
                'days' => 3,
                'template_id' => $template->id,
                'hotel_ids' => [$gazelle->id],
                'department_ids' => [$spa->id, $reception->id],
                'is_active' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rule = AutomationRule::query()->where('name', 'Spa comeback')->firstOrFail();
        $this->assertSame(AutomationTrigger::InactiveDays, $rule->trigger);
        $this->assertSame(3, $rule->days);
        $this->assertSame([$gazelle->id], $rule->hotelIds());
        $this->assertSame([$spa->id, $reception->id], $rule->departmentIds());
        $this->assertSame("La Gazelle d'Or · Reception + Spa", $rule->audience_label);
        $this->assertOneAuditRow($rule, 'automation_rule.created');
    }

    public function test_editing_a_rule_to_everyone_clears_the_audience()
    {
        $template = ReminderTemplate::factory()->create();
        $rule = AutomationRule::factory()->audience(hotelIds: [Hotel::factory()->create()->id])->create(['template_id' => $template->id]);

        $this->actingAs($this->admin)
            ->patch(route('messages-reminders.rules.update', $rule), [
                'name' => 'Everyone now',
                'trigger' => AutomationTrigger::NotStarted->value,
                'days' => '',
                'template_id' => $template->id,
                'hotel_ids' => [],
                'department_ids' => [],
                'is_active' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rule->refresh();
        $this->assertSame('Everyone now', $rule->name);
        $this->assertNull($rule->audience);
        $this->assertNull($rule->days);
        $this->assertSame('All hotels', $rule->audience_label);
        $this->assertFalse($rule->is_active);
        $this->assertOneAuditRow($rule, 'automation_rule.updated');
    }

    public function test_a_rule_needs_a_known_trigger_and_an_existing_template()
    {
        $this->actingAs($this->admin)
            ->post(route('messages-reminders.rules.store'), [
                'name' => 'Broken',
                'trigger' => 'full_moon',
                'template_id' => 999,
                'hotel_ids' => [999],
            ])
            ->assertSessionHasErrors(['trigger', 'template_id', 'hotel_ids.0']);

        $this->assertDatabaseCount('automation_rules', 0);
    }

    public function test_toggling_a_rule_flips_it_and_audits_each_flip()
    {
        $rule = AutomationRule::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('messages-reminders.rules.toggle', $rule))
            ->assertRedirect();
        $this->assertFalse($rule->fresh()?->is_active);
        $this->assertOneAuditRow($rule, 'automation_rule.paused');

        $this->actingAs($this->admin)
            ->patch(route('messages-reminders.rules.toggle', $rule))
            ->assertRedirect();
        $this->assertTrue($rule->fresh()?->is_active);
        $this->assertOneAuditRow($rule, 'automation_rule.activated');
    }

    // --------------------------------------------------------------- helpers

    private function assertOneAuditRow(ReminderTemplate|AutomationRule $model, string $action): void
    {
        $rows = AuditLog::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->id)
            ->where('action', $action)
            ->get();

        $this->assertCount(1, $rows, "Expected exactly one {$action} audit row.");
        $this->assertSame($this->admin->id, $rows->first()?->actor_id);
    }
}
