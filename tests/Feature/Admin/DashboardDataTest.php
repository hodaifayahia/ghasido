<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\AiScenario;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Every figure on the Admin Dashboard comes from real rows (ADM-01, REP-01;
 * spec 0003 Part D): the stat cards, the donut and its per-department
 * breakdown, the progress bars, the three Needs Attention tabs and the
 * Recent Activity feed.
 */
class DashboardDataTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Department $reception;

    private Department $spa;

    private Course $course;

    /** @var list<Lesson> */
    private array $lessons = [];

    private Test $preTest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-09-18 12:00:00');

        config(['guesvia.reminders.inactive_days' => 5]);

        $this->hotel = Hotel::factory()->create(['name' => "La Gazelle d'Or"]);
        $this->reception = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception', 'position' => 0]);
        $this->spa = Department::factory()->create(['name' => 'Spa', 'slug' => 'spa', 'position' => 1]);

        // Four published Reception lessons on a shared course, plus a draft
        // one that must count for nobody.
        $this->course = Course::factory()->published()->forDepartment($this->reception->id)->create();
        $unit = Unit::factory()->create(['course_id' => $this->course->id]);

        foreach (range(1, 4) as $position) {
            $this->lessons[] = Lesson::factory()->published()->at($position)->create([
                'unit_id' => $unit->id,
                'title' => "Lesson title {$position}",
            ]);
        }

        Lesson::factory()->at(5)->create(['unit_id' => $unit->id]);

        $this->preTest = Test::factory()->pre()->create(['department_id' => $this->reception->id]);
    }

    // ------------------------------------------------------------ the page

    public function test_the_stat_cards_and_the_donut_come_from_the_employee_rows()
    {
        // Reception: one finished, one halfway, one who only sat the Pre-test,
        // one untouched. Spa: one untouched. Plus noise that must not count:
        // an inactive account, a manager, an employee of an archived hotel.
        $finished = $this->employee($this->reception, ['name' => 'Fatima Laouar', 'training_started_at' => now()->subDays(10), 'training_completed_at' => now()->subDays(2)]);
        $this->completeLessons($finished, 4);

        $halfway = $this->employee($this->reception, ['name' => 'Amine Ben Ali', 'training_started_at' => now()->subDays(6)]);
        $this->completeLessons($halfway, 2);

        $tested = $this->employee($this->reception, ['name' => 'Noura Saidi']);
        $this->submitPreTest($tested);

        $this->employee($this->reception, ['name' => 'Samira Touati']);
        $this->employee($this->spa, ['name' => 'Sara Khelifi']);

        $this->employee($this->reception, ['name' => 'Gone', 'status' => AccountStatus::Inactive]);
        User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);

        $archived = Hotel::factory()->archived()->create();
        User::factory()->employee()->create(['hotel_id' => $archived->id, 'department_id' => $this->reception->id]);
        Hotel::factory()->create(['name' => 'Hotel El Aurassi']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // Two hotels in the portfolio (the archived one drops out).
                ->where('stats.0.key', 'hotels')
                ->where('stats.0.value', 2)
                ->where('stats.0.label', 'Hotels')
                ->where('stats.0.detail', 'Active portfolio')
                ->where('stats.1.key', 'departments')
                ->where('stats.1.value', 2)
                ->where('stats.2.key', 'employees')
                ->where('stats.2.value', 5)
                // Started: finished + halfway + pre-test only = 3 of 5.
                ->where('stats.3.key', 'trainingStarted')
                ->where('stats.3.value', 3)
                ->where('stats.3.detail', '60%')
                ->where('stats.4.key', 'trainingCompleted')
                ->where('stats.4.value', 1)
                ->where('stats.4.detail', '20%')
                // Mean of 100, 50, 0, 0, 0.
                ->where('stats.5.key', 'averageProgress')
                ->where('stats.5.value', 30)
                ->where('stats.5.unit', '%')
                ->where('trainingOverview.all', ['completed' => 1, 'inProgress' => 2, 'notStarted' => 2])
                ->has('trainingOverview.departments', 2)
                ->where('trainingOverview.departments.0.name', 'Reception')
                ->where('trainingOverview.departments.0.breakdown', ['completed' => 1, 'inProgress' => 2, 'notStarted' => 1])
                ->where('trainingOverview.departments.1.name', 'Spa')
                ->where('trainingOverview.departments.1.breakdown', ['completed' => 0, 'inProgress' => 0, 'notStarted' => 1])
                // Reception: (100 + 50 + 0 + 0) / 4 = 37.5 → 38. Spa: 0.
                ->has('departmentProgress', 2)
                ->where('departmentProgress.0.name', 'Reception')
                ->where('departmentProgress.0.percent', 38)
                ->where('departmentProgress.1.percent', 0)
            );
    }

    public function test_a_single_hotel_is_named_on_its_stat_card()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.0.value', 1)
                ->where('stats.0.label', 'Hotel')
                ->where('stats.0.detail', "La Gazelle d'Or")
                ->where('stats.2.value', 0)
                ->where('stats.3.detail', '0%')
                ->where('stats.5.value', 0)
                ->where('trainingOverview.all', ['completed' => 0, 'inProgress' => 0, 'notStarted' => 0])
                ->has('needsAttention', 3)
                ->where('needsAttention.0.total', 0)
                ->where('recentActivity', [])
            );
    }

    public function test_needs_attention_lists_idle_unstarted_and_pretest_only_learners()
    {
        // Idle: no activity for longer than the configured window, training
        // not finished. Six of them, so the tab carries the full count while
        // the table shows five, longest idle first.
        foreach ([12, 6, 8, 10, 7, 9] as $days) {
            $this->employee($this->reception, [
                'name' => "Idle {$days}",
                'training_started_at' => now()->subDays(20),
                'last_login_at' => now()->subDays($days)->subHours(2),
                'last_activity_at' => now()->subDays($days),
            ]);
        }

        // Idle exactly at the window's edge is not idle yet; a finished
        // learner is never chased; a recently active one is fine.
        $this->employee($this->reception, ['name' => 'Edge', 'training_started_at' => now()->subDays(20), 'last_activity_at' => now()->subDays(5)->addMinute()]);
        $done = $this->employee($this->reception, ['name' => 'Done', 'training_completed_at' => now()->subDays(20), 'last_activity_at' => now()->subDays(20)]);
        $this->completeLessons($done, 4);
        $this->employee($this->reception, ['name' => 'Fresh', 'training_started_at' => now()->subDays(20), 'last_activity_at' => now()->subDay()]);

        // Never started: no lessons, no test, no start stamp. Most recent
        // login first, never logged in last.
        $this->employee($this->spa, ['name' => 'Lina Bouzid', 'last_login_at' => now()->subDays(2)]);
        $this->employee($this->spa, ['name' => 'Nadia Hamdi', 'last_login_at' => now()->subDay()]);
        $this->employee($this->spa, ['name' => 'Never Here']);

        // Pre-test finished, no lesson yet.
        $tested = $this->employee($this->reception, ['name' => 'Walid Ferhat', 'last_login_at' => now()->subDays(3)]);
        $this->submitPreTest($tested);
        $testedAndLearning = $this->employee($this->reception, ['name' => 'Imane Saadi']);
        $this->submitPreTest($testedAndLearning);
        $this->completeLessons($testedAndLearning, 1);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('needsAttention.0.key', 'inactive')
                ->where('needsAttention.0.label', 'Inactive Employees')
                ->where('needsAttention.0.total', 6)
                ->has('needsAttention.0.employees', 5)
                ->where('needsAttention.0.employees.0.name', 'Idle 12')
                ->where('needsAttention.0.employees.0.department', 'Reception')
                ->where('needsAttention.0.employees.0.lastLogin', '12 days ago')
                ->where('needsAttention.0.employees.1.name', 'Idle 10')
                ->where('needsAttention.0.employees.4.name', 'Idle 7')
                ->where('needsAttention.1.key', 'notStarted')
                ->where('needsAttention.1.total', 3)
                ->where('needsAttention.1.employees.0.name', 'Nadia Hamdi')
                ->where('needsAttention.1.employees.0.lastLogin', '1 day ago')
                ->where('needsAttention.1.employees.1.name', 'Lina Bouzid')
                ->where('needsAttention.1.employees.1.lastLogin', '2 days ago')
                ->where('needsAttention.1.employees.2.name', 'Never Here')
                ->where('needsAttention.1.employees.2.lastLogin', 'Never')
                ->where('needsAttention.2.key', 'pretestFinished')
                ->where('needsAttention.2.total', 1)
                ->where('needsAttention.2.employees.0.name', 'Walid Ferhat')
                ->where('needsAttention.2.employees.0.lastLogin', '3 days ago')
            );
    }

    public function test_recent_activity_is_the_latest_five_events_across_every_source()
    {
        $amine = $this->employee($this->reception, ['name' => 'Amine Ben Ali', 'training_started_at' => now()->subDays(3)->setTime(11, 5)]);
        $noura = $this->employee($this->reception, ['name' => 'Noura Saidi']);
        $karim = $this->employee($this->reception, ['name' => 'Karim Boudiaf']);
        $fatima = $this->employee($this->reception, ['name' => 'Fatima Laouar', 'training_completed_at' => now()->subDays(2)->setTime(16, 20)]);
        $hakim = $this->employee($this->reception, ['name' => 'Hakim Chenini', 'training_started_at' => now()->subDays(9)->setTime(9, 0)]);

        // Newest first: lesson (today 14:32), test (today 12:15), role-play
        // (today 10:48), training completed (2 days ago), Amine started
        // (3 days ago). Hakim's start, an older lesson and a preview
        // role-play fall outside the five.
        LessonCompletion::factory()->create(['user_id' => $amine->id, 'lesson_id' => $this->lessons[2]->id, 'completed_at' => now()->setTime(14, 32)]);
        LessonCompletion::factory()->create(['user_id' => $hakim->id, 'lesson_id' => $this->lessons[0]->id, 'completed_at' => now()->subDays(8)]);
        TestAttempt::factory()->submitted(19, 25)->create(['user_id' => $noura->id, 'test_id' => $this->preTest->id, 'submitted_at' => now()->setTime(12, 15)]);

        $scenario = AiScenario::factory()->published()->create(['department_id' => $this->reception->id, 'title' => 'Handling a complaint']);
        RoleplayAttempt::factory()->completed()->create(['user_id' => $karim->id, 'ai_scenario_id' => $scenario->id, 'started_at' => now()->setTime(10, 48)]);
        RoleplayAttempt::factory()->preview()->create(['user_id' => $karim->id, 'ai_scenario_id' => $scenario->id, 'started_at' => now()->setTime(11, 0)]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentActivity', 5)
                ->where('recentActivity.0', ['id' => 1, 'date' => '18 Sep 2026', 'time' => '14:32', 'employee' => 'Amine Ben Ali', 'type' => 'lessonCompleted', 'activity' => 'Completed a lesson', 'details' => 'Lesson 3 – Lesson title 3'])
                ->where('recentActivity.1', ['id' => 2, 'date' => '18 Sep 2026', 'time' => '12:15', 'employee' => 'Noura Saidi', 'type' => 'pretestFinished', 'activity' => 'Finished Pre-test', 'details' => 'Score: 76% (19/25)'])
                ->where('recentActivity.2', ['id' => 3, 'date' => '18 Sep 2026', 'time' => '10:48', 'employee' => 'Karim Boudiaf', 'type' => 'roleplayUsed', 'activity' => 'Used AI Role-play', 'details' => 'Scenario: Handling a complaint'])
                ->where('recentActivity.3', ['id' => 4, 'date' => '16 Sep 2026', 'time' => '16:20', 'employee' => 'Fatima Laouar', 'type' => 'trainingCompleted', 'activity' => 'Completed Training', 'details' => '100% – All lessons'])
                ->where('recentActivity.4', ['id' => 5, 'date' => '15 Sep 2026', 'time' => '11:05', 'employee' => 'Amine Ben Ali', 'type' => 'trainingStarted', 'activity' => 'Started Training', 'details' => 'Lesson 1 – Lesson title 1'])
                ->where('needsAttention.2.employees.0.name', 'Noura Saidi')
            );

        $fatima->refresh();
        $this->assertNotNull($fatima->training_completed_at);
    }

    // ----------------------------------------------------------- hotel scope

    public function test_a_hotel_scope_confines_every_figure_to_that_hotel()
    {
        $other = Hotel::factory()->create(['name' => 'Elsewhere']);

        $mine = $this->employee($this->reception, ['name' => 'Mine', 'training_started_at' => now()->subDays(3), 'last_activity_at' => now()->subDays(9)]);
        $this->completeLessons($mine, 2);

        $theirs = User::factory()->employee()->create([
            'name' => 'Theirs',
            'hotel_id' => $other->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
            'training_started_at' => now()->subDay(),
            'training_completed_at' => now(),
            'last_activity_at' => now()->subDays(20),
        ]);
        $this->completeLessons($theirs, 4);
        $this->submitPreTest($theirs);

        $props = app(DashboardStats::class)->build($this->hotel);

        $this->assertSame(1, $props['stats'][0]['value']);
        $this->assertSame("La Gazelle d'Or", $props['stats'][0]['detail']);
        $this->assertSame(1, $props['stats'][2]['value']);
        $this->assertSame(['completed' => 0, 'inProgress' => 1, 'notStarted' => 0], $props['trainingOverview']['all']);
        $this->assertSame(50, $props['stats'][5]['value']);
        $this->assertSame(1, $props['needsAttention'][0]['total']);
        $this->assertSame('Mine', $props['needsAttention'][0]['employees'][0]['name']);
        $this->assertSame(0, $props['needsAttention'][2]['total']);

        $employees = array_column($props['recentActivity'], 'employee');
        $this->assertNotEmpty($employees);
        $this->assertSame(['Mine'], array_values(array_unique($employees)));

        // Unscoped, both hotels count.
        $all = app(DashboardStats::class)->build();
        $this->assertSame(2, $all['stats'][2]['value']);
        $this->assertSame(['completed' => 1, 'inProgress' => 1, 'notStarted' => 0], $all['trainingOverview']['all']);
    }

    // ----------------------------------------------------------- authorization

    public function test_a_manager_gets_a_dashboard_scoped_to_their_hotel()
    {
        $this->employee($this->reception, ['name' => 'Someone']);
        $manager = User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.0.value', 1)
                ->where('stats.2.value', 1)
                ->where('trainingOverview.all', ['completed' => 0, 'inProgress' => 0, 'notStarted' => 1])
                ->has('needsAttention')
                ->has('recentActivity')
            );
    }

    public function test_an_employee_is_sent_to_the_learner_home()
    {
        $employee = $this->employee($this->reception, ['name' => 'Learner']);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertRedirect(route('learn.home'));
    }

    // -------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(Department $department, array $attributes = []): User
    {
        return User::factory()->employee()->create($attributes + [
            'hotel_id' => $this->hotel->id,
            'department_id' => $department->id,
            'status' => AccountStatus::Active,
        ]);
    }

    private function completeLessons(User $user, int $count): void
    {
        foreach (array_slice($this->lessons, 0, $count) as $index => $lesson) {
            LessonCompletion::factory()->create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'completed_at' => now()->subDays(10)->addDays($index),
            ]);
        }
    }

    private function submitPreTest(User $user): void
    {
        TestAttempt::factory()->submitted()->create([
            'user_id' => $user->id,
            'test_id' => $this->preTest->id,
            'submitted_at' => now()->subDays(7),
        ]);
    }
}
