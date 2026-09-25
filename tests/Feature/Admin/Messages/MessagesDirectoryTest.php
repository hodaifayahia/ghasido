<?php

namespace Tests\Feature\Admin\Messages;

use App\Enums\AccountStatus;
use App\Enums\ReminderStatus;
use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Reminders\MessagesDirectory;
use App\Services\Reminders\RecipientQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Messages screen reads real rows: stat cards, the filtered recipients
 * table, templates, rules, the paged log and the manager's hotel boundary
 * (REM-02, REM-06, REM-07; spec 0003 Part D).
 */
class MessagesDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $gazelle;

    private Hotel $aurassi;

    private Department $reception;

    private Department $spa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['guesvia.reminders.inactive_days' => 5]);

        $this->gazelle = Hotel::factory()->create(['name' => "La Gazelle d'Or"]);
        $this->aurassi = Hotel::factory()->create(['name' => 'Hotel El Aurassi']);
        $this->reception = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception', 'position' => 1]);
        $this->spa = Department::factory()->create(['name' => 'Spa', 'slug' => 'spa', 'position' => 2]);
    }

    // ---------------------------------------------------------- happy path

    public function test_the_page_reads_stats_recipients_templates_rules_and_the_log()
    {
        $amina = $this->employee(['name' => 'Amina Saadi', 'last_activity_at' => now()->subDays(2)]);
        $karim = $this->employee(['name' => 'Karim Ben Ali', 'last_activity_at' => now()->subDays(7)]);
        $sofia = $this->employee(['name' => 'Sofia Merad', 'last_activity_at' => now()->subDays(12), 'status' => AccountStatus::Inactive], $this->aurassi, $this->spa);
        $hicham = $this->employee(['name' => 'Hicham Zitoune', 'email_consent_at' => null, 'last_activity_at' => null, 'created_at' => now()->subDays(20)]);

        $template = ReminderTemplate::factory()->create(['name' => 'Training comeback reminder']);
        ReminderTemplate::factory()->inactive()->create(['name' => 'Retired template']);
        AutomationRule::factory()->create(['name' => 'Inactive learner follow-up', 'template_id' => $template->id]);

        Reminder::factory()->for($karim)->create(['template_id' => $template->id, 'sent_at' => now()->subHour()]);
        Reminder::factory()->for($hicham)->blocked()->create(['template_id' => $template->id, 'created_at' => now()->subHours(3)]);
        Reminder::factory()->for($sofia)->create([
            'template_id' => $template->id,
            'status' => ReminderStatus::Scheduled,
            'sent_at' => null,
            'scheduled_for' => now()->addDays(2),
        ]);

        $this->index(User::factory()->superAdmin()->create())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/MessagesReminders')
                ->where('stats.0.key', 'consentedEmployees')
                ->where('stats.0.value', 3)
                ->where('stats.0.detail', 'of 4 employees')
                ->where('stats.1.key', 'scheduledReminders')
                ->where('stats.1.value', 1)
                ->where('stats.2.key', 'followUpNeeded')
                // Karim (7 days) and Hicham (never, created 20 days ago);
                // Sofia is deactivated and Amina is recent.
                ->where('stats.2.value', 2)
                ->where('stats.2.detail', 'inactive 5+ days')
                ->where('stats.3.key', 'sentToday')
                ->where('stats.3.value', 1)
                ->where('stats.4.key', 'templates')
                ->where('stats.4.value', 1)
                // Default filters: consent granted + inactive 5+ days.
                ->where('filters.hotel', RecipientQuery::ALL_HOTELS)
                ->where('filters.consent', RecipientQuery::CONSENT_GRANTED)
                ->where('filters.activity', RecipientQuery::ACTIVITY_INACTIVE)
                ->has('filters.hotels', 3)
                ->has('filters.departments', 3)
                ->has('recipients', 2)
                ->where('recipients.0.name', 'Karim Ben Ali')
                ->where('recipients.0.hotel', "La Gazelle d'Or")
                ->where('recipients.0.department', 'Reception')
                ->where('recipients.0.consentStatus', 'granted')
                ->where('recipients.0.status', 'in_progress')
                ->where('recipients.0.inactivityLabel', '1 week ago')
                ->where('recipients.1.name', 'Sofia Merad')
                ->where('recipients.1.status', 'inactive')
                ->where('recipientsPagination.total', 2)
                ->has('templates', 2)
                ->where('templates.0.name', 'Retired template')
                ->where('templates.0.isActive', false)
                ->where('templates.1.name', 'Training comeback reminder')
                ->where('templates.1.audience', 'Inactive employees')
                ->has('automations', 1)
                ->where('automations.0.trigger', 'No progress for 5 days')
                ->where('automations.0.audience', 'All hotels')
                ->where('automations.0.active', true)
                ->has('logs', 3)
                ->where('logs.0.recipient', 'Sofia Merad')
                ->where('logs.0.status', 'scheduled')
                ->where('logs.1.recipient', 'Karim Ben Ali')
                ->where('logs.1.status', 'sent')
                ->where('logs.2.status', 'blocked')
                ->where('logsPagination.total', 3)
                ->where('abilities.send', true)
                ->where('abilities.manageTemplates', true)
                ->where('abilities.manageRules', true)
            );

        $this->assertSame('Amina Saadi', $amina->name);
    }

    public function test_the_activity_consent_search_and_department_filters_drive_the_table()
    {
        $this->employee(['name' => 'Recent Reception', 'last_activity_at' => now()->subDay()]);
        $this->employee(['name' => 'Idle Reception', 'last_activity_at' => now()->subDays(9)]);
        $this->employee(['name' => 'Never Spa', 'last_activity_at' => null, 'created_at' => now()->subDays(30)], $this->gazelle, $this->spa);
        $this->employee(['name' => 'Silent Spa', 'email_consent_at' => null, 'last_activity_at' => now()->subDays(9)], $this->aurassi, $this->spa);

        $admin = User::factory()->superAdmin()->create();

        $this->index($admin, ['activity' => 'all-activity', 'consent' => 'all-consent'])
            ->assertInertia(fn (Assert $page) => $page->has('recipients', 4));

        $this->index($admin, ['activity' => 'not-started', 'consent' => 'all-consent'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 4)
            );

        $this->index($admin, ['activity' => 'inactive-5-days', 'consent' => 'consent-missing'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 1)
                ->where('recipients.0.name', 'Silent Spa')
                ->where('recipients.0.status', 'consent_pending')
            );

        $this->index($admin, ['activity' => 'all-activity', 'consent' => 'all-consent', 'department' => (string) $this->spa->id])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 2)
                ->where('filters.department', (string) $this->spa->id)
            );

        $this->index($admin, ['activity' => 'all-activity', 'consent' => 'all-consent', 'hotel' => (string) $this->aurassi->id])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 1)
                ->where('recipients.0.name', 'Silent Spa')
            );

        $this->index($admin, ['activity' => 'all-activity', 'consent' => 'all-consent', 'search' => 'aurassi'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 1)
                ->where('filters.search', 'aurassi')
            );

        $this->index($admin, ['activity' => 'all-activity', 'consent' => 'all-consent', 'search' => 'zzzz'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 0)
                ->where('recipientsPagination.total', 0)
            );
    }

    public function test_recipients_and_the_log_are_paged_independently()
    {
        foreach (range(1, MessagesDirectory::PER_PAGE + 3) as $index) {
            $this->employee(['name' => sprintf('Employee %02d', $index), 'last_activity_at' => now()->subDays(9)]);
        }

        $template = ReminderTemplate::factory()->create();
        $recipient = User::query()->employees()->firstOrFail();

        foreach (range(1, MessagesDirectory::LOG_PER_PAGE + 2) as $index) {
            Reminder::factory()->for($recipient)->create(['template_id' => $template->id, 'sent_at' => now()->subHours($index)]);
        }

        $admin = User::factory()->superAdmin()->create();

        $this->index($admin)
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', MessagesDirectory::PER_PAGE)
                ->where('recipientsPagination.total', MessagesDirectory::PER_PAGE + 3)
                ->where('recipientsPagination.lastPage', 2)
                ->has('logs', MessagesDirectory::LOG_PER_PAGE)
                ->where('logsPagination.lastPage', 2)
            );

        $this->index($admin, ['page' => 2, 'log_page' => 2])
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 3)
                ->where('recipientsPagination.currentPage', 2)
                ->where('recipients.0.name', 'Employee 09')
                ->has('logs', 2)
                ->where('logsPagination.currentPage', 2)
            );
    }

    // -------------------------------------------------------------- manager

    public function test_a_manager_sees_only_their_own_hotel_and_cannot_widen_it()
    {
        $mine = $this->employee(['name' => 'Mine', 'last_activity_at' => now()->subDays(9)]);
        $theirs = $this->employee(['name' => 'Theirs', 'last_activity_at' => now()->subDays(9)], $this->aurassi, $this->spa);

        $template = ReminderTemplate::factory()->create();
        Reminder::factory()->for($mine)->create(['template_id' => $template->id]);
        Reminder::factory()->for($theirs)->create(['template_id' => $template->id]);

        $manager = User::factory()->manager()->create(['hotel_id' => $this->gazelle->id]);

        // A hotel filter naming the other hotel is ignored (REM-07).
        $this->index($manager, ['hotel' => (string) $this->aurassi->id, 'activity' => 'all-activity', 'consent' => 'all-consent'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.hotel', (string) $this->gazelle->id)
                ->has('filters.hotels', 1)
                ->where('filters.hotels.0.label', "La Gazelle d'Or")
                ->where('stats.0.detail', 'of 1 employees')
                ->has('recipients', 1)
                ->where('recipients.0.name', 'Mine')
                ->has('logs', 1)
                ->where('logs.0.recipient', 'Mine')
                ->where('abilities.send', true)
                ->where('abilities.manageTemplates', false)
                ->where('abilities.manageRules', false)
            );
    }

    public function test_a_manager_without_a_hotel_sees_nobody()
    {
        $this->employee(['last_activity_at' => now()->subDays(9)]);

        $this->index(User::factory()->manager()->create(['hotel_id' => null]), ['activity' => 'all-activity', 'consent' => 'all-consent'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recipients', 0)
                ->where('stats.0.detail', 'of 0 employees')
            );
    }

    // -------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $query
     */
    private function index(User $actor, array $query = []): TestResponse
    {
        return $this->actingAs($actor)->get(route('messages-reminders', $query));
    }

    /**
     * A consenting, active employee of La Gazelle d'Or's reception unless
     * the attributes say otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = [], ?Hotel $hotel = null, ?Department $department = null): User
    {
        return User::factory()->employee()->withUsername()->create($attributes + [
            'hotel_id' => ($hotel ?? $this->gazelle)->id,
            'department_id' => ($department ?? $this->reception)->id,
            'status' => AccountStatus::Active,
            'email_consent_at' => now(),
            'last_activity_at' => now()->subDays(9),
        ]);
    }
}
