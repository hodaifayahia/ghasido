<?php

namespace Tests\Feature\Admin\Messages;

use App\Enums\AccountStatus;
use App\Enums\AutomationTrigger;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\ReminderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The tenant boundary on every messaging route, by GET and by form POST
 * (REM-07, ROLE-02, SEC-01; spec 0003 Part A rule 6).
 *
 * A refusal is a 403, never a 404 and never a redirect. A manager may send
 * to their own employees and nobody else's, and may not write templates or
 * rules at all.
 */
class MessagesCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $mine;

    private Hotel $theirs;

    private User $manager;

    private ReminderTemplate $template;

    private AutomationRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();

        $this->mine = Hotel::factory()->create(['name' => 'Mine']);
        $this->theirs = Hotel::factory()->create(['name' => 'Theirs']);
        $this->manager = User::factory()->manager()->create([
            'hotel_id' => $this->mine->id,
            'status' => AccountStatus::Active,
        ]);
        $this->template = ReminderTemplate::factory()->create();
        $this->rule = AutomationRule::factory()->create(['template_id' => $this->template->id]);
    }

    public function test_a_manager_cannot_send_to_another_hotels_employee_by_id()
    {
        $own = $this->employeeOf($this->mine);
        $other = $this->employeeOf($this->theirs);

        // One foreign id refuses the whole batch: nothing is written.
        $this->actingAs($this->manager)
            ->post(route('messages-reminders.send'), $this->sendPayload([$own->id, $other->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('reminders', 0);
        $this->assertSame(0, AuditLog::count());

        $this->actingAs($this->manager)
            ->post(route('messages-reminders.send'), $this->sendPayload([$other->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_a_manager_may_send_to_their_own_employees()
    {
        $own = $this->employeeOf($this->mine);

        $this->actingAs($this->manager)
            ->from(route('messages-reminders'))
            ->post(route('messages-reminders.send'), $this->sendPayload([$own->id]))
            ->assertRedirect(route('messages-reminders'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reminders', ['user_id' => $own->id, 'sent_by' => $this->manager->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'reminders.sent')->count());
    }

    public function test_select_all_never_crosses_the_hotel_even_with_a_foreign_hotel_filter()
    {
        $own = $this->employeeOf($this->mine);
        $other = $this->employeeOf($this->theirs);

        $this->actingAs($this->manager)
            ->post(route('messages-reminders.send'), [
                'template_id' => $this->template->id,
                'channel' => 'email',
                'select_all' => 1,
                'hotel' => (string) $this->theirs->id,
                'consent' => 'all-consent',
                'activity' => 'all-activity',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reminders', ['user_id' => $own->id]);
        $this->assertDatabaseMissing('reminders', ['user_id' => $other->id]);
    }

    public function test_a_manager_cannot_write_templates_or_rules()
    {
        $this->actingAs($this->manager);

        $this->post(route('messages-reminders.templates.store'), $this->templatePayload())->assertForbidden();
        $this->patch(route('messages-reminders.templates.update', $this->template), $this->templatePayload())->assertForbidden();
        $this->post(route('messages-reminders.rules.store'), $this->rulePayload())->assertForbidden();
        $this->patch(route('messages-reminders.rules.update', $this->rule), $this->rulePayload())->assertForbidden();
        $this->patch(route('messages-reminders.rules.toggle', $this->rule))->assertForbidden();

        $this->assertSame(0, AuditLog::count());
        $this->assertTrue($this->rule->fresh()?->is_active);

        // Reading and previewing is fine: the templates explain the log.
        $this->get(route('messages-reminders.templates.preview', ['subject' => 'Hi {{name}}']))
            ->assertOk()
            ->assertJsonPath('subject', 'Hi {{name}}');
    }

    public function test_a_preview_only_ever_samples_the_managers_own_employees()
    {
        $this->employeeOf($this->mine, ['name' => 'Own Employee']);
        $other = $this->employeeOf($this->theirs, ['name' => 'Other Employee']);

        $this->actingAs($this->manager)
            ->get(route('messages-reminders.templates.preview', ['subject' => 'Hi {{name}}', 'recipient' => $other->id]))
            ->assertOk()
            ->assertJsonPath('sample.name', 'Own Employee')
            ->assertJsonPath('subject', 'Hi Own Employee');
    }

    public function test_an_employee_is_refused_every_messaging_route()
    {
        $employee = $this->employeeOf($this->mine);

        $this->actingAs($employee);

        $this->get(route('messages-reminders'))->assertForbidden();
        $this->get(route('messages-reminders.templates.preview'))->assertForbidden();
        $this->post(route('messages-reminders.send'), $this->sendPayload([$employee->id]))->assertForbidden();
        $this->post(route('messages-reminders.templates.store'), $this->templatePayload())->assertForbidden();
        $this->patch(route('messages-reminders.templates.update', $this->template), $this->templatePayload())->assertForbidden();
        $this->post(route('messages-reminders.rules.store'), $this->rulePayload())->assertForbidden();
        $this->patch(route('messages-reminders.rules.update', $this->rule), $this->rulePayload())->assertForbidden();
        $this->patch(route('messages-reminders.rules.toggle', $this->rule))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login()
    {
        $this->post(route('messages-reminders.send'), [])->assertRedirect(route('login'));
    }

    // --------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employeeOf(Hotel $hotel, array $attributes = []): User
    {
        return User::factory()->employee()->withUsername()->create($attributes + [
            'hotel_id' => $hotel->id,
            'department_id' => Department::factory()->create()->id,
            'status' => AccountStatus::Active,
            'email_consent_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function sendPayload(array $ids): array
    {
        return ['template_id' => $this->template->id, 'channel' => 'email', 'recipients' => $ids];
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(): array
    {
        return ['name' => 'X', 'subject' => 'Y {{name}}', 'body' => 'Z {{login_url}}'];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulePayload(): array
    {
        return ['name' => 'R', 'trigger' => AutomationTrigger::NotStarted->value, 'template_id' => $this->template->id];
    }
}
