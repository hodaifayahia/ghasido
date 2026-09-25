<?php

namespace Tests\Feature\Admin\Departments;

use App\Enums\AccountStatus;
use App\Enums\DepartmentStatus;
use App\Models\AiScenario;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\SeatQuota;
use App\Models\Test;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The directory reads real rows: stat cards, rows, sidebar, search, both
 * filters and the pager (spec 0003 Part D; ORG-02, ORG-04, SUB-01, REP-01).
 */
class DepartmentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
    }

    // ---------------------------------------------------------- happy path

    public function test_every_figure_on_the_page_comes_from_the_database()
    {
        [$gazelle, $aurassi] = $this->twoHotels();

        $reception = $this->catalogue('Reception', 0, 'Guest arrival, greeting and check-in language.');
        $spa = $this->catalogue('Spa', 1);
        $spa->update(['status' => DepartmentStatus::Review]);
        $kitchen = Department::factory()->forHotel($aurassi->id)->create([
            'name' => 'Kitchen Communication',
            'slug' => 'kitchen-communication',
            'position' => 2,
            'status' => DepartmentStatus::Draft,
        ]);
        $archived = $this->catalogue('Old Wing', 3);
        $archived->update(['is_active' => false]);

        // Reception: two hotels, 5 of 5 and 3 of 4 seats; one inactive
        // account that must not count.
        $this->quota($gazelle, $reception, 5, 5);
        $this->quota($aurassi, $reception, 4, 3);
        User::factory()->employee()->forHotel($gazelle, $reception)->create(['status' => AccountStatus::Inactive]);

        // Spa: one hotel, over quota.
        $this->quota($gazelle, $spa, 6, 7);

        // Reception content: two published lessons of three, a draft course
        // whose lesson must not count, a paired pre/post test, one scenario.
        $course = Course::factory()->forDepartment($reception->id)->published()->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->published()->count(2)->create(['unit_id' => $unit->id]);
        Lesson::factory()->create(['unit_id' => $unit->id]);
        $draftCourse = Course::factory()->forDepartment($reception->id)->create();
        $draftUnit = Unit::factory()->create(['course_id' => $draftCourse->id]);
        Lesson::factory()->published()->create(['unit_id' => $draftUnit->id]);
        $pre = Test::factory()->pre()->create(['department_id' => $reception->id]);
        Test::factory()->post()->create(['department_id' => $reception->id, 'paired_test_id' => $pre->id]);
        Test::factory()->post()->draft()->create(['department_id' => $reception->id]);
        AiScenario::factory()->forDepartment($reception->id)->published()->create();
        AiScenario::factory()->forDepartment($reception->id)->create();

        // Spa has a published course and scenario but no test: not ready.
        Course::factory()->forDepartment($spa->id)->published()->create();
        AiScenario::factory()->forDepartment($spa->id)->published()->create();

        $this->index()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Departments')
                // Four rows, one archived: 3 live (the archived one is out).
                ->where('stats.0.key', 'totalDepartments')
                ->where('stats.0.value', 3)
                ->where('stats.1.key', 'activeDepartments')
                ->where('stats.1.value', 1)
                ->where('stats.2.key', 'sharedTemplates')
                ->where('stats.2.value', 2)
                ->where('stats.3.key', 'assignedEmployees')
                ->where('stats.3.value', 15)
                ->where('stats.4.key', 'contentReady')
                ->where('stats.4.value', 1)
                ->has('departments', 3)
                ->where('departments.0.rank', 1)
                ->where('departments.0.name', 'Reception')
                ->where('departments.0.focus', 'Guest arrival, greeting and check-in language.')
                ->where('departments.0.scope', 'shared')
                ->where('departments.0.scopeLabel', 'Shared Across Hotels')
                ->where('departments.0.hotelCount', 2)
                ->where('departments.0.employeeCount', 8)
                ->where('departments.0.usedSeats', 8)
                ->where('departments.0.totalSeats', 9)
                ->where('departments.0.lessonCount', 3)
                ->where('departments.0.testCount', 2)
                ->where('departments.0.scenarioCount', 1)
                ->where('departments.0.status', 'active')
                ->where('departments.0.isActive', true)
                ->where('departments.1.name', 'Spa')
                ->where('departments.1.status', 'review')
                ->where('departments.1.usedSeats', 7)
                ->where('departments.1.totalSeats', 6)
                ->where('departments.2.name', 'Kitchen Communication')
                ->where('departments.2.scope', 'hotel')
                ->where('departments.2.scopeLabel', 'Hotel El Aurassi only')
                ->where('departments.2.hotelId', $aurassi->id)
                ->where('departments.2.status', 'draft')
                ->where('pagination.from', 1)
                ->where('pagination.to', 3)
                ->where('pagination.total', 3)
                // The sidebar opens on the first row.
                ->where('overview.id', $reception->id)
                ->where('overview.name', 'Reception')
                ->where('overview.hotelCount', 2)
                ->where('overview.employeeCount', 8)
                ->where('overview.lessonCount', 3)
                ->where('overview.testCount', 2)
                ->where('overview.scenarioCount', 1)
                ->where('overview.notes', [
                    'Shared template currently powers 2 hotel teams.',
                    "La Gazelle d'Or has reached its reception seat quota.",
                    'Pre-test and post-test are already paired for this department.',
                ])
                ->has('overview.assignments', 2)
                ->where('overview.assignments.0.hotel', 'Hotel El Aurassi')
                ->where('overview.assignments.0.manager', 'Yacine Merabet')
                ->where('overview.assignments.0.usedSeats', 3)
                ->where('overview.assignments.0.totalSeats', 4)
                ->where('overview.assignments.0.state', 'available')
                ->where('overview.assignments.1.hotel', "La Gazelle d'Or")
                ->where('overview.assignments.1.state', 'full')
                ->has('hotelOptions', 2)
                ->where('hotelOptions.0.label', 'Hotel El Aurassi')
            );
    }

    public function test_a_hotel_specific_department_gets_its_own_notes()
    {
        [$gazelle] = $this->twoHotels();
        $spa = Department::factory()->forHotel($gazelle->id)->create(['name' => 'Spa & Wellness', 'slug' => 'spa-wellness']);

        $this->index()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.scopeLabel', "La Gazelle d'Or only")
                ->where('overview.notes', [
                    'Hotel-specific department with no seat quota yet.',
                    'Pre-test and post-test are not paired for this department yet.',
                ])
                ->where('overview.assignments', [])
            );

        // 4 allowed, 6 used: over quota, which earns its own note.
        $this->quota($gazelle, $spa, 4, 6);

        $this->index()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.notes', [
                    'Hotel-specific department with 4 allowed seats.',
                    "La Gazelle d'Or has reached its spa & wellness seat quota.",
                    'Pre-test and post-test are not paired for this department yet.',
                ])
                ->where('overview.assignments.0.state', 'over')
            );
    }

    // ------------------------------------------------------------- filters

    public function test_search_matches_the_name_and_the_focus_line()
    {
        $this->catalogue('Reception', 0, 'Guest arrival and check-in language.');
        $this->catalogue('Housekeeping', 1, 'Room status and apology language.');
        $this->catalogue('Kitchen', 2, 'Back-of-house requests.');

        $this->index(['search' => 'language'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 2)
                ->where('filters.search', 'language')
                ->where('departments.0.name', 'Reception')
                ->where('departments.1.name', 'Housekeeping')
            );

        $this->index(['search' => 'kitch'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 1)
                ->where('departments.0.name', 'Kitchen')
                ->where('overview.name', 'Kitchen')
            );

        $this->index(['search' => 'zzz'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 0)
                ->where('overview', null)
                ->where('pagination.total', 0)
            );
    }

    public function test_the_scope_and_status_filters_narrow_the_rows()
    {
        [$gazelle] = $this->twoHotels();
        $this->catalogue('Reception', 0);
        $review = $this->catalogue('Spa', 1);
        $review->update(['status' => DepartmentStatus::Review]);
        Department::factory()->forHotel($gazelle->id)->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'position' => 2]);
        $archived = $this->catalogue('Old Wing', 3);
        $archived->update(['is_active' => false]);

        $this->index(['scope' => 'hotel'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.scope', 'hotel')
                ->has('departments', 1)
                ->where('departments.0.name', 'Kitchen')
            );

        $this->index(['scope' => 'shared', 'status' => 'review'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 1)
                ->where('departments.0.name', 'Spa')
            );

        // Archived rows are out by default and filter back in.
        $this->index()
            ->assertInertia(fn (Assert $page) => $page->has('departments', 3));

        $this->index(['status' => 'archived'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 1)
                ->where('departments.0.name', 'Old Wing')
                ->where('departments.0.isActive', false)
            );
    }

    public function test_the_pager_shows_seven_rows_per_page_and_honours_the_selection()
    {
        $departments = collect(range(1, 9))
            ->map(fn (int $i): Department => $this->catalogue(sprintf('Dept %02d', $i), $i));

        $this->index()
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 7)
                ->where('pagination.from', 1)
                ->where('pagination.to', 7)
                ->where('pagination.total', 9)
                ->where('pagination.lastPage', 2)
                ->where('pagination.pages', [1, 2])
            );

        $ninth = $departments->last();

        $this->index(['page' => 2])
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 2)
                ->where('pagination.from', 8)
                ->where('departments.0.rank', 8)
                ->where('overview.name', 'Dept 08')
            );

        // Selecting a row off the current page still shows it in the sidebar.
        $this->index(['department' => $ninth?->id])
            ->assertInertia(fn (Assert $page) => $page
                ->where('pagination.currentPage', 1)
                ->where('overview.id', $ninth?->id)
            );

        // A row that does not exist falls back to the first row.
        $this->index(['department' => 999999])
            ->assertInertia(fn (Assert $page) => $page->where('overview.name', 'Dept 01'));
    }

    public function test_a_manager_sees_the_shared_catalogue_and_their_own_hotel_only()
    {
        [$gazelle, $aurassi] = $this->twoHotels();
        $reception = $this->catalogue('Reception', 0);
        $mine = Department::factory()->forHotel($gazelle->id)->create(['name' => 'Mine', 'slug' => 'mine', 'position' => 1]);
        $theirs = Department::factory()->forHotel($aurassi->id)->create(['name' => 'Theirs', 'slug' => 'theirs', 'position' => 2]);

        $this->quota($gazelle, $reception, 5, 3);
        $this->quota($aurassi, $reception, 5, 4);

        $manager = User::factory()->manager()->create(['hotel_id' => $gazelle->id]);

        $this->actingAs($manager)
            ->get(route('departments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('departments', 2)
                ->where('departments.0.id', $reception->id)
                ->where('departments.1.id', $mine->id)
                ->where('stats.0.value', 2)
                ->where('stats.3.value', 3)
                // The hotel coverage is their own row only (ROLE-02).
                ->has('overview.assignments', 1)
                ->where('overview.assignments.0.hotel', "La Gazelle d'Or")
                ->where('overview.hotelCount', 1)
                // A manager cannot add departments, so no hotel list ships.
                ->where('hotelOptions', [])
            );

        // Asking for the other hotel's row by id falls back to the first row.
        $this->actingAs($manager)
            ->get(route('departments', ['department' => $theirs->id]))
            ->assertInertia(fn (Assert $page) => $page->where('overview.id', $reception->id));
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $query
     */
    private function index(array $query = []): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('departments', $query));
    }

    /**
     * @return array{0: Hotel, 1: Hotel}
     */
    private function twoHotels(): array
    {
        $gazelle = Hotel::factory()->create(['name' => "La Gazelle d'Or", 'manager_name' => 'Meriem Haddad']);
        $aurassi = Hotel::factory()->create(['name' => 'Hotel El Aurassi', 'manager_name' => 'Yacine Merabet']);

        return [$gazelle, $aurassi];
    }

    private function catalogue(string $name, int $position, ?string $focus = null): Department
    {
        return Department::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'position' => $position,
            'focus' => $focus,
        ]);
    }

    private function quota(Hotel $hotel, Department $department, int $allowed, int $used): SeatQuota
    {
        User::factory()->employee()->forHotel($hotel, $department)->count($used)->create();

        return SeatQuota::factory()->create([
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'allowed_seats' => $allowed,
        ]);
    }
}
