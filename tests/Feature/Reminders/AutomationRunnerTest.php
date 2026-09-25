<?php

namespace Tests\Feature\Reminders;

use App\Enums\AccountStatus;
use App\Enums\AutomationTrigger;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Jobs\RunAutomationRules;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reminders\AutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The daily automation pass (REM-03, REM-05, REM-07; spec 0003 B.7).
 *
 * Each trigger is a recipient query; the audience narrows it; the repeat
 * window stops the same rule nagging the same person every morning.
 */
class AutomationRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    // ---------------------------------------------------------- inactive_days

    public function test_inactive_days_picks_employees_idle_for_longer_than_the_window()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();

        $idle = $this->employee(['last_activity_at' => now()->subDays(6)]);
        $neverActiveOld = $this->employee(['last_activity_at' => null, 'created_at' => now()->subDays(10)]);
        $recent = $this->employee(['last_activity_at' => now()->subDays(2)]);
        $brandNew = $this->employee(['last_activity_at' => null, 'created_at' => now()->subDay()]);
        $deactivated = $this->employee(['last_activity_at' => now()->subDays(20), 'status' => AccountStatus::Inactive]);
        $manager = User::factory()->manager()->create(['last_activity_at' => now()->subDays(20)]);

        $summary = $this->runner()->run();

        $this->assertSame(2, $summary[$rule->id]['recipients']);
        $this->assertNull($summary[$rule->id]['note']);
        $this->assertRemindedExactly([$idle, $neverActiveOld], $rule);

        foreach ([$recent, $brandNew, $deactivated, $manager] as $left) {
            $this->assertDatabaseMissing('reminders', ['user_id' => $left->id]);
        }
    }

    public function test_the_inactivity_window_falls_back_to_the_configured_default()
    {
        config(['guesvia.reminders.inactive_days' => 3]);
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, null)->create();

        $idle = $this->employee(['last_activity_at' => now()->subDays(4)]);
        $this->employee(['last_activity_at' => now()->subDays(2)]);

        $this->runner()->run();

        $this->assertRemindedExactly([$idle], $rule);
    }

    public function test_email_triggers_still_honour_consent_by_writing_blocked_rows()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();

        $consenting = $this->employee(['last_activity_at' => now()->subDays(9)]);
        $silent = $this->employee(['last_activity_at' => now()->subDays(9), 'email_consent_at' => null]);

        $this->runner()->run();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $consenting->id,
            'automation_rule_id' => $rule->id,
            'channel' => ReminderChannel::Email->value,
            'status' => ReminderStatus::Queued->value,
        ]);
        $this->assertDatabaseHas('reminders', [
            'user_id' => $silent->id,
            'automation_rule_id' => $rule->id,
            'status' => ReminderStatus::Blocked->value,
            'blocked_reason' => Reminder::BLOCKED_NO_CONSENT,
        ]);
    }

    // --------------------------------------------------------------- audience

    public function test_the_audience_restricts_recipients_to_the_listed_hotels_and_departments()
    {
        $gazelle = Hotel::factory()->create();
        $aurassi = Hotel::factory()->create();
        $reception = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);
        $spa = Department::factory()->create(['name' => 'Spa', 'slug' => 'spa']);

        $rule = AutomationRule::factory()
            ->trigger(AutomationTrigger::InactiveDays, 5)
            ->audience(hotelIds: [$gazelle->id], departmentIds: [$reception->id])
            ->create();

        $idle = ['last_activity_at' => now()->subDays(9)];
        $match = $this->employee($idle + ['hotel_id' => $gazelle->id, 'department_id' => $reception->id]);
        $this->employee($idle + ['hotel_id' => $aurassi->id, 'department_id' => $reception->id]);
        $this->employee($idle + ['hotel_id' => $gazelle->id, 'department_id' => $spa->id]);

        $this->runner()->run();

        $this->assertRemindedExactly([$match], $rule);
    }

    public function test_an_empty_audience_means_everyone()
    {
        $rule = AutomationRule::factory()
            ->trigger(AutomationTrigger::InactiveDays, 5)
            ->create(['audience' => ['hotel_ids' => [], 'department_ids' => []]]);

        $one = $this->employee(['last_activity_at' => now()->subDays(9), 'hotel_id' => Hotel::factory()->create()->id]);
        $two = $this->employee(['last_activity_at' => now()->subDays(9), 'hotel_id' => Hotel::factory()->create()->id]);

        $this->runner()->run();

        $this->assertRemindedExactly([$one, $two], $rule);
    }

    // ---------------------------------------------------------- repeat window

    public function test_an_employee_reminded_by_the_rule_inside_the_window_is_skipped()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();
        $otherRule = AutomationRule::factory()->inactive()->create();

        $recentlyNudged = $this->employee(['last_activity_at' => now()->subDays(9)]);
        $nudgedLongAgo = $this->employee(['last_activity_at' => now()->subDays(9)]);
        $nudgedByAnotherRule = $this->employee(['last_activity_at' => now()->subDays(9)]);

        Reminder::factory()->for($recentlyNudged)->create(['automation_rule_id' => $rule->id, 'created_at' => now()->subDays(2)]);
        Reminder::factory()->for($nudgedLongAgo)->create(['automation_rule_id' => $rule->id, 'created_at' => now()->subDays(6)]);
        Reminder::factory()->for($nudgedByAnotherRule)->create(['automation_rule_id' => $otherRule->id, 'created_at' => now()->subDays(2)]);

        $this->runner()->run();

        $this->assertDatabaseCount('reminders', 5);
        $this->assertSame(1, Reminder::query()->where('user_id', $recentlyNudged->id)->count());
        $this->assertSame(2, Reminder::query()->where('user_id', $nudgedLongAgo->id)->count());
        $this->assertSame(2, Reminder::query()->where('user_id', $nudgedByAnotherRule->id)->count());
    }

    public function test_a_blocked_row_also_counts_as_reminded_so_nobody_collects_one_a_day()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();
        $silent = $this->employee(['last_activity_at' => now()->subDays(9), 'email_consent_at' => null]);

        $this->runner()->run();
        $this->runner()->run();

        $this->assertSame(1, Reminder::query()->where('user_id', $silent->id)->count());
        $this->assertSame($rule->id, Reminder::query()->where('user_id', $silent->id)->value('automation_rule_id'));
    }

    public function test_the_repeat_window_defaults_to_seven_days_when_the_rule_has_none()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::ConsentMissing, null)->create();

        $this->assertSame(AutomationRule::DEFAULT_REPEAT_DAYS, $rule->repeatWindowDays());

        $sixDaysAgo = $this->employee(['email_consent_at' => null]);
        $eightDaysAgo = $this->employee(['email_consent_at' => null]);
        Reminder::factory()->inApp()->for($sixDaysAgo)->create(['automation_rule_id' => $rule->id, 'created_at' => now()->subDays(6)]);
        Reminder::factory()->inApp()->for($eightDaysAgo)->create(['automation_rule_id' => $rule->id, 'created_at' => now()->subDays(8)]);

        $this->runner()->run();

        $this->assertSame(1, Reminder::query()->where('user_id', $sixDaysAgo->id)->count());
        $this->assertSame(2, Reminder::query()->where('user_id', $eightDaysAgo->id)->count());
    }

    // -------------------------------------------------------- consent_missing

    public function test_consent_missing_goes_in_app_and_reaches_only_non_consenting_employees()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::ConsentMissing)->create();

        $silent = $this->employee(['email_consent_at' => null]);
        $this->employee(['email_consent_at' => now()]);

        $this->runner()->run();

        $this->assertRemindedExactly([$silent], $rule);
        $this->assertDatabaseHas('reminders', [
            'user_id' => $silent->id,
            'channel' => ReminderChannel::InApp->value,
            'status' => ReminderStatus::Sent->value,
            'read_at' => null,
        ]);
    }

    // ------------------------------------------------------------ not_started

    public function test_not_started_targets_employees_with_no_completions_or_attempts()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::NotStarted, 3)->create();

        $untouched = $this->employee();
        $finishedALesson = $this->employee();
        $openedTheTest = $this->employee();

        LessonCompletion::factory()->for($finishedALesson)->create();
        TestAttempt::factory()->for($openedTheTest)->create();

        $this->runner()->run();

        $this->assertRemindedExactly([$untouched], $rule);
    }

    // ------------------------------------------------------ posttest_available

    public function test_posttest_available_reaches_only_employees_whose_post_test_is_unlocked()
    {
        // Gate 2 (JOURNEY-04) through User::postTestUnlocked(): the Pre-test
        // submitted and every published lesson of the department complete.
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::PosttestAvailable)->create();

        $department = Department::factory()->create();
        $hotel = Hotel::factory()->create();
        $course = Course::factory()->forDepartment($department->id)->published()->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['unit_id' => $unit->id]);
        $preTest = Test::factory()->pre()->create(['department_id' => $department->id]);

        $unlocked = $this->employee(['hotel_id' => $hotel->id, 'department_id' => $department->id]);
        TestAttempt::factory()->submitted()->create(['user_id' => $unlocked->id, 'test_id' => $preTest->id]);
        LessonCompletion::factory()->create(['user_id' => $unlocked->id, 'lesson_id' => $lesson->id]);

        $lessonsLeft = $this->employee(['hotel_id' => $hotel->id, 'department_id' => $department->id]);
        TestAttempt::factory()->submitted()->create(['user_id' => $lessonsLeft->id, 'test_id' => $preTest->id]);

        $noPreTest = $this->employee(['hotel_id' => $hotel->id, 'department_id' => $department->id]);
        LessonCompletion::factory()->create(['user_id' => $noPreTest->id, 'lesson_id' => $lesson->id]);

        $summary = $this->runner()->run();

        $this->assertNull($summary[$rule->id]['note']);
        $this->assertSame(1, $summary[$rule->id]['recipients']);
        $this->assertRemindedExactly([$unlocked], $rule);
        $this->assertNotNull($rule->fresh()?->last_run_at);
    }

    // ------------------------------------------------------------ bookkeeping

    public function test_inactive_rules_and_inactive_templates_are_left_alone()
    {
        $inactiveRule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->inactive()->create();
        $inactiveTemplate = AutomationRule::factory()
            ->trigger(AutomationTrigger::InactiveDays, 5)
            ->for(ReminderTemplate::factory()->inactive(), 'template')
            ->create();

        $this->employee(['last_activity_at' => now()->subDays(9)]);

        $summary = $this->runner()->run();

        $this->assertArrayNotHasKey($inactiveRule->id, $summary);
        $this->assertSame('template_inactive', $summary[$inactiveTemplate->id]['note']);
        $this->assertDatabaseCount('reminders', 0);
        $this->assertNull($inactiveRule->fresh()?->last_run_at);
    }

    public function test_a_run_stamps_last_run_at_and_audits_the_send()
    {
        Date::setTestNow(Date::parse('2026-09-18 08:00:00'));

        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();
        $this->employee(['last_activity_at' => now()->subDays(9)]);

        $this->runner()->run();

        $this->assertTrue($rule->fresh()?->last_run_at?->equalTo(Date::now()));

        $log = AuditLog::query()->where('action', 'reminders.sent')->first();
        $this->assertNotNull($log);
        $this->assertSame($rule->template_id, $log->auditable_id);
        $this->assertSame($rule->id, $log->changes['automation_rule_id'] ?? null);
        $this->assertSame(1, $log->changes['recipients'] ?? null);
        $this->assertNull($log->actor_id);

        Date::setTestNow();
    }

    public function test_a_rule_that_matches_nobody_still_stamps_last_run_at_and_writes_no_audit_row()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();

        $summary = $this->runner()->run();

        $this->assertSame(0, $summary[$rule->id]['recipients']);
        $this->assertNotNull($rule->fresh()?->last_run_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_daily_job_runs_the_runner_and_is_on_the_schedule()
    {
        $rule = AutomationRule::factory()->trigger(AutomationTrigger::InactiveDays, 5)->create();
        $idle = $this->employee(['last_activity_at' => now()->subDays(9)]);

        (new RunAutomationRules)->handle($this->runner());

        $this->assertRemindedExactly([$idle], $rule);

        $this->artisan('schedule:list')
            ->expectsOutputToContain(RunAutomationRules::class)
            ->assertSuccessful();
    }

    // ---------------------------------------------------------------- helpers

    private function runner(): AutomationRunner
    {
        return app(AutomationRunner::class);
    }

    /**
     * An active, consenting employee unless told otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        return User::factory()->employee()->create($attributes + ['email_consent_at' => now()]);
    }

    /**
     * @param  list<User>  $users
     */
    private function assertRemindedExactly(array $users, AutomationRule $rule): void
    {
        $expected = collect($users)->map(fn (User $user): int => $user->id)->sort()->values()->all();

        $actual = Reminder::query()
            ->where('automation_rule_id', $rule->id)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame($expected, $actual);
    }
}
