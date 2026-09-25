<?php

namespace Tests\Feature\Admin\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Reports & Export page reads real rows: the six stats, the charts,
 * every tab, the filters, the search, the pager and the drill-down, and
 * the tenant boundary a manager cannot cross (REP-01, REP-02, REP-04,
 * TEST-10, DATA-01..05, AIE-04, ROLE-02, ROLE-04; spec 0003 Part D).
 */
class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // ---------------------------------------------------------- happy path

    public function test_every_figure_on_the_page_comes_from_the_rows()
    {
        $world = ReportsWorld::build();

        $this->index()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ReportsExport')
                ->where('stats.0.key', 'totalEmployees')
                ->where('stats.0.value', 3)
                ->where('stats.0.detail', '1 Department')
                ->where('stats.1.key', 'activeAccounts')
                ->where('stats.1.value', 3)
                ->where('stats.1.detail', '100%')
                ->where('stats.2.key', 'completedPretest')
                ->where('stats.2.value', 3)
                ->where('stats.3.key', 'completedPosttest')
                ->where('stats.3.value', 1)
                ->where('stats.3.detail', '33%')
                ->where('stats.4.key', 'completedAiScenarios')
                ->where('stats.4.value', 1)
                ->where('stats.5.key', 'completedLessons')
                ->where('stats.5.value', 1)
                // Pre 60 / 40 / 80 → 60; Post 85 (TEST-10).
                ->where('prePost.0.label', 'Reception')
                ->where('prePost.0.first', 60)
                ->where('prePost.0.second', 85)
                ->where('aiPerformance.0.label', 'Reception')
                ->where('aiPerformance.0.value', 70)
                ->where('completion.completed', 33)
                ->where('completion.inProgress', 33)
                ->where('completion.notStarted', 34)
                ->where('activity.total', 3)
                ->where('activity.activeThisWeek', 1)
                ->where('activity.activeThisMonth', 1)
                ->where('activity.inactive', 1)
                ->where('activeTab', 'employeeResults')
                ->where('results.tab', 'employeeResults')
                ->has('results.rows', 3)
                ->where('results.rows.0.name', 'Alice Amrani')
                ->where('results.rows.0.initials', 'AA')
                ->where('results.rows.0.rank', 1)
                ->where('results.rows.0.department', 'Reception')
                ->where('results.rows.0.preScore', 60)
                ->where('results.rows.0.postScore', 85)
                ->where('results.rows.0.lessonsCompleted', 2)
                ->where('results.rows.0.lessonsTotal', 2)
                ->where('results.rows.0.scenariosCompleted', 1)
                ->where('results.rows.0.scenariosTotal', 1)
                ->where('results.rows.0.status', 'completed')
                ->where('results.rows.0.statusLabel', 'Completed')
                ->where('results.rows.1.name', 'Bob Benali')
                ->where('results.rows.1.postScore', null)
                ->where('results.rows.1.status', 'in_progress')
                ->where('results.rows.2.name', 'Carol Cherif')
                ->where('results.rows.2.status', 'inactive')
                ->where('results.rows.2.statusLabel', 'Inactive (45 days)')
                ->where('pagination.total', 3)
                ->where('pagination.currentPerPage', 7)
                ->where('canViewTranscripts', true)
                ->where('canExportAnonymised', true)
                ->has('datasets', 6)
                ->where('filters.range', 'last-90-days')
                ->has('filters.ranges', 4)
                ->where('filters.hotels.0.value', 'all-hotels')
                ->has('filters.hotels', 3)
                ->where('detail', null)
            );

        $this->assertSame($world->alice->id, $this->index()->inertiaProps('results.rows.0.id'));
    }

    public function test_filters_search_and_pager_run_on_the_server()
    {
        $world = ReportsWorld::build();

        $this->index(['search' => 'bob'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 1)
                ->where('results.rows.0.name', 'Bob Benali')
                ->where('search', 'bob')
            );

        $this->index(['completionStatus' => 'completed'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 1)
                ->where('results.rows.0.name', 'Alice Amrani')
                ->where('stats.0.value', 1)
            );

        $this->index(['hotel' => $world->hotelB->id])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 1)
                ->where('results.rows.0.name', 'Carol Cherif')
                ->where('filters.hotel', (string) $world->hotelB->id)
            );

        $this->index(['employee' => $world->bob->id])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 1)
                ->where('stats.2.value', 1)
                ->where('stats.3.value', 0)
            );

        // Last month only: nothing of Alice's Post-test (2 days ago).
        $this->index(['range' => 'last-month'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'last-month')
                ->where('stats.3.value', 0)
            );

        $this->index(['range' => 'custom', 'from' => now()->subDays(4)->toDateString(), 'to' => now()->toDateString()])
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'custom')
                ->where('stats.2.value', 0)
                ->where('stats.3.value', 1)
                ->where('stats.4.value', 1)
            );

        $this->index(['per_page' => 20, 'page' => 2])
            ->assertInertia(fn (Assert $page) => $page
                ->where('pagination.currentPerPage', 20)
                ->where('pagination.currentPage', 2)
                ->has('results.rows', 0)
            );

        $this->index(['per_page' => 99, 'tab' => 'bogus'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('pagination.currentPerPage', 7)
                ->where('activeTab', 'employeeResults')
            );
    }

    public function test_every_tab_reads_its_own_rows()
    {
        $world = ReportsWorld::build();

        $this->index(['tab' => 'detailedAnswers'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.tab', 'detailedAnswers')
                ->has('results.rows', 1)
                ->where('results.rows.0.id', $world->aliceAnswer->id)
                ->where('results.rows.0.employee', 'Alice Amrani')
                ->where('results.rows.0.context', 'Reception Pre-test')
                ->where('results.rows.0.skill', 'Situation')
                ->where('results.rows.0.question', 'A guest is at the reception desk with a large suitcase. What would you say?')
                ->where('results.rows.0.answer', 'A — Good evening! Can I help you with your luggage?')
                ->where('results.rows.0.isCorrect', true)
                ->where('results.rows.0.timeTakenMs', 12345)
                ->where('results.rows.0.version', 1)
                ->where('results.rows.0.audioUrl', null)
            );

        // The activity-type filter narrows the answers to tests or lessons.
        $this->index(['tab' => 'detailedAnswers', 'activityType' => 'lessons'])
            ->assertInertia(fn (Assert $page) => $page->has('results.rows', 0));

        $this->index(['tab' => 'roleplayLogs'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 1)
                ->where('results.rows.0.scenario', 'Check-in')
                ->where('results.rows.0.overallScore', 70)
                ->where('results.rows.0.criteria.0.key', 'pronunciation')
                ->where('results.rows.0.criteria.0.score', 70)
                ->where('results.rows.0.summary', 'Good try!')
                ->has('results.rows.0.transcript', 4)
                ->where('results.rows.0.transcript.0.text', 'Hello! I have a reservation for tonight.')
            );

        $this->index(['tab' => 'lessonProgress'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 3)
                ->where('results.rows.0.employee', 'Bob Benali')
                ->where('results.rows.0.lesson', 'Handling Guest Complaints')
                ->where('results.rows.0.course', 'Guest Service Basics')
            );

        $this->index(['tab' => 'comparison'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 3)
                ->where('results.rows.0.employee', 'Alice Amrani')
                ->where('results.rows.0.skill', 'Situation')
                ->where('results.rows.0.prePercent', 60)
                ->where('results.rows.0.postPercent', 100)
                ->where('results.rows.0.delta', 40)
                ->where('results.rows.1.employee', 'Bob Benali')
                ->where('results.rows.1.postPercent', null)
                ->where('pagination.total', 3)
            );

        $this->index(['tab' => 'downloadCenter'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.tab', 'downloadCenter')
                ->has('results.rows', 0)
                ->where('datasets.5.key', 'anonymised')
                ->where('datasets.5.anonymised', true)
            );
    }

    public function test_view_details_drills_into_one_employee()
    {
        $world = ReportsWorld::build();

        $this->index(['detail' => $world->alice->id])
            ->assertInertia(fn (Assert $page) => $page
                ->where('detail.id', $world->alice->id)
                ->where('detail.name', 'Alice Amrani')
                ->where('detail.hotel', 'Hotel Alpha')
                ->where('detail.participantCode', 'P-ALICE1')
                ->has('detail.lessons', 2)
                ->where('detail.lessons.0.title', 'Handling Guest Complaints')
                ->has('detail.tests', 2)
                ->where('detail.tests.0.type', 'pre')
                ->where('detail.tests.0.percent', 60)
                ->has('detail.roleplays', 1)
                ->where('detail.roleplays.0.overallScore', 70)
            );

        // An id that is not an employee resolves to nothing, never an error.
        $this->index(['detail' => 999999])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('detail', null));
    }

    // ------------------------------------------------------------ boundary

    public function test_a_manager_can_no_longer_open_reports()
    {
        // Client decision: Reports & Export is removed from the manager role,
        // so the page is a 403 by direct URL rather than a scoped view
        // (narrows REP-08). Only the Super Admin and Admin reach it.
        $world = ReportsWorld::build();

        $this->actingAs($world->managerOfA())
            ->get(route('reports-export'))
            ->assertForbidden();

        $this->actingAs(User::factory()->manager()->create(['hotel_id' => null]))
            ->get(route('reports-export'))
            ->assertForbidden();
    }

    public function test_an_employee_and_a_guest_are_refused()
    {
        $world = ReportsWorld::build();

        $this->get(route('reports-export'))->assertRedirect(route('login'));
        $this->actingAs($world->alice)->get(route('reports-export'))->assertForbidden();
    }

    // -------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $query
     */
    private function index(array $query = []): TestResponse
    {
        return $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('reports-export', $query));
    }
}
