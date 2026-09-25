<?php

namespace Tests\Feature\Admin\Employees;

use App\Enums\AccountStatus;
use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\SeatQuota;
use App\Models\Unit;
use App\Models\User;
use App\Services\Employees\EmployeeDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The directory reads real rows: stat cards, rows, derived status and
 * progress, search, the three filters and the pager (spec 0003 Part D,
 * Manage Employees; PROG-02, DATA-07).
 */
class EmployeeDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Hotel $hotel;

    private Department $reception;

    private Department $spa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $this->hotel = Hotel::factory()->create(['name' => "La Gazelle d'Or"]);
        $this->reception = Department::factory()->create(['name' => 'Reception', 'position' => 1]);
        $this->spa = Department::factory()->create(['name' => 'Spa', 'position' => 2]);
        SeatQuota::factory()->create(['hotel_id' => $this->hotel->id, 'department_id' => $this->reception->id, 'allowed_seats' => 5]);
        SeatQuota::factory()->create(['hotel_id' => $this->hotel->id, 'department_id' => $this->spa->id, 'allowed_seats' => 5]);
    }

    public function test_the_stat_cards_and_rows_are_derived_from_the_accounts()
    {
        [$lessonA, $lessonB] = $this->curriculum($this->reception, 2);

        $done = $this->employee('Amine Ben Ali', 'abenali', ['last_login_at' => '2026-09-06 10:00:00', 'training_started_at' => now(), 'training_completed_at' => now()]);
        $this->complete($done, $lessonA);
        $this->complete($done, $lessonB);

        $half = $this->employee('Sara Khelifi', 'skhelifi', ['last_login_at' => '2026-09-02 09:00:00']);
        $this->complete($half, $lessonA);

        $fresh = $this->employee('Mourad Zitouni', 'mzitouni');
        $off = $this->employee('Leila Brahimi', 'lbrahimi', ['status' => AccountStatus::Inactive]);

        // A manager and a Super Admin are never employees.
        User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);

        $this->index()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Employees')
                ->where('stats.0.key', 'totalEmployees')
                ->where('stats.0.value', 4)
                ->where('stats.1.key', 'activeAccounts')
                ->where('stats.1.value', 3)
                ->where('stats.2.key', 'inactiveAccounts')
                ->where('stats.2.value', 1)
                ->where('stats.3.key', 'startedTraining')
                ->where('stats.3.value', 2)
                ->where('stats.3.detail', '50.0%')
                ->where('stats.4.key', 'completedTraining')
                ->where('stats.4.value', 1)
                ->where('stats.4.detail', '25.0%')
                ->where('stats.5.key', 'notStarted')
                ->where('stats.5.value', 1)
                ->where('stats.5.detail', '25.0%')
                // Name order.
                ->has('employees', 4)
                ->where('employees.0.name', 'Amine Ben Ali')
                ->where('employees.0.rank', 1)
                ->where('employees.0.username', 'abenali')
                ->where('employees.0.hotel', "La Gazelle d'Or")
                ->where('employees.0.department', 'Reception')
                ->where('employees.0.progress', 100)
                ->where('employees.0.status', 'completed')
                ->where('employees.0.lastLogin', '06 Sep 2026')
                ->where('employees.0.lessonsCompleted', 2)
                ->where('employees.0.lessonsTotal', 2)
                ->where('employees.1.name', 'Leila Brahimi')
                ->where('employees.1.status', 'inactive')
                ->where('employees.1.accountStatus', 'inactive')
                ->where('employees.1.lastLogin', '-')
                ->where('employees.2.name', 'Mourad Zitouni')
                ->where('employees.2.status', 'not_started')
                ->where('employees.2.progress', 0)
                ->where('employees.3.name', 'Sara Khelifi')
                ->where('employees.3.status', 'in_progress')
                ->where('employees.3.progress', 50)
                ->where('pagination.total', 4)
                ->where('pagination.pages', [1])
                ->has('createForm.hotels', 1)
                ->where('createForm.defaultHotel', (string) $this->hotel->id)
                ->has('createForm.departments', 2)
                ->where('createForm.departments.0.label', 'Reception')
            );

        $this->assertNotNull($fresh->participant_code);
        $this->assertNotNull($off->participant_code);
    }

    public function test_the_selected_progress_matches_the_users_own_progress_percent()
    {
        [$lessonA, $lessonB, $lessonC] = $this->curriculum($this->reception, 3);
        // A lesson of another department never counts.
        $this->curriculum($this->spa, 2);

        $user = $this->employee('Noura Saidi', 'nsaidi');
        $this->complete($user, $lessonA);
        $this->complete($user, $lessonC);

        $this->assertSame(67, $user->progressPercent());

        $this->index()->assertInertia(fn (Assert $page) => $page
            ->where('employees.0.progress', 67)
            ->where('employees.0.status', 'in_progress')
        );

        $this->complete($user, $lessonB);

        $this->assertSame(100, $user->fresh()?->progressPercent());
        $this->index()->assertInertia(fn (Assert $page) => $page
            ->where('employees.0.progress', 100)
            ->where('employees.0.status', 'completed')
        );
    }

    public function test_search_and_filters_run_on_the_server()
    {
        $other = Hotel::factory()->create(['name' => 'Hotel El Aurassi']);
        SeatQuota::factory()->create(['hotel_id' => $other->id, 'department_id' => $this->spa->id, 'allowed_seats' => 5]);

        $this->employee('Amine Ben Ali', 'abenali', ['email' => 'amine.benali@hotel.dz']);
        $this->employee('Sara Khelifi', 'skhelifi', ['department_id' => $this->spa->id]);
        $this->employee('Fatima Laouar', 'flaouar', ['hotel_id' => $other->id, 'department_id' => $this->spa->id, 'status' => AccountStatus::Inactive]);

        $this->index(['search' => 'benali@hotel'])->assertInertia(fn (Assert $page) => $page
            ->has('employees', 1)
            ->where('employees.0.username', 'abenali')
            ->where('filters.search', 'benali@hotel')
        );

        $this->index(['hotel' => $other->id])->assertInertia(fn (Assert $page) => $page
            ->has('employees', 1)
            ->where('employees.0.name', 'Fatima Laouar')
            ->where('filters.hotel', (string) $other->id)
        );

        $this->index(['department' => $this->spa->id])->assertInertia(fn (Assert $page) => $page
            ->has('employees', 2)
            ->where('filters.department', (string) $this->spa->id)
        );

        $this->index(['status' => 'inactive'])->assertInertia(fn (Assert $page) => $page
            ->has('employees', 1)
            ->where('employees.0.name', 'Fatima Laouar')
            ->where('filters.status', 'inactive')
        );

        $this->index(['status' => 'not_started'])->assertInertia(fn (Assert $page) => $page
            ->has('employees', 2)
        );

        // The stat cards describe the whole, never the filtered page.
        $this->index(['status' => 'inactive'])->assertInertia(fn (Assert $page) => $page
            ->where('stats.0.value', 3)
        );
    }

    public function test_the_pager_shows_ten_rows_and_the_mockup_window()
    {
        foreach (range(1, 23) as $i) {
            $this->employee(sprintf('Employee %02d', $i), sprintf('employee.%02d', $i));
        }

        $this->index()->assertInertia(fn (Assert $page) => $page
            ->has('employees', 10)
            ->where('pagination.from', 1)
            ->where('pagination.to', 10)
            ->where('pagination.total', 23)
            ->where('pagination.lastPage', 3)
            ->where('pagination.pages', [1, 2, 3])
        );

        $this->index(['page' => 3])->assertInertia(fn (Assert $page) => $page
            ->has('employees', 3)
            ->where('employees.0.rank', 21)
            ->where('pagination.currentPage', 3)
        );

        $this->assertSame([1, 2, 3, 4, 5, 'ellipsis', 8], EmployeeDirectory::pageWindow(1, 8));
        $this->assertSame([1, 'ellipsis', 3, 4, 5, 6, 7, 8], EmployeeDirectory::pageWindow(5, 8));
        $this->assertSame([1, 'ellipsis', 4, 5, 6, 7, 8], EmployeeDirectory::pageWindow(8, 8));
        $this->assertSame([1, 'ellipsis', 8, 9, 10, 11, 12, 'ellipsis', 20], EmployeeDirectory::pageWindow(10, 20));
    }

    // -------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(string $name, string $username, array $attributes = []): User
    {
        return User::factory()->employee()->create([
            'name' => $name,
            'username' => $username,
            'hotel_id' => $this->hotel->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
            'participant_code' => User::generateParticipantCode(),
            'last_login_at' => null,
            ...$attributes,
        ]);
    }

    /**
     * A published shared course with N published lessons.
     *
     * @return list<Lesson>
     */
    private function curriculum(Department $department, int $lessons): array
    {
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'hotel_id' => null,
            'status' => ContentStatus::Published,
        ]);
        $unit = Unit::factory()->create(['course_id' => $course->id, 'status' => ContentStatus::Published]);

        $rows = [];

        foreach (range(1, $lessons) as $position) {
            $rows[] = Lesson::factory()->create([
                'unit_id' => $unit->id,
                'course_id' => $course->id,
                'hotel_id' => null,
                'position' => $position,
                'status' => ContentStatus::Published,
            ]);
        }

        return $rows;
    }

    private function complete(User $user, Lesson $lesson): void
    {
        LessonCompletion::query()->create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function index(array $query = []): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('employees', $query));
    }
}
