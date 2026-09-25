<?php

namespace Tests\Feature\Learn;

use App\Enums\AccountStatus;
use App\Models\Department;
use App\Models\Test;
use App\Models\User;
use App\Services\Learning\JourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A manager learns as an employee in a department they choose (client
 * decision 2026-09-23): the learner routes admit them, a chooser gate stands
 * before the training until they pick a department, and their content is
 * scoped to that department exactly like an employee's (ROLE-02, SEC-01).
 */
class ManagerTrainingTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create([
            'hotel_id' => $this->hotel->id,
            'status' => AccountStatus::Active,
            'username' => 'manager1',
        ]);
    }

    public function test_a_manager_is_sent_to_the_department_chooser_before_training()
    {
        $this->actingAs($this->manager())
            ->get(route('learn.home'))
            ->assertRedirect(route('learn.training-department.edit'));
    }

    public function test_a_manager_can_choose_a_department_and_then_reach_home()
    {
        $this->publishedLesson();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('learn.training-department.update'), ['department_id' => $this->department->id])
            ->assertRedirect(route('learn.home'));

        $this->actingAs($manager)
            ->get(route('learn.home'))
            ->assertOk();
    }

    public function test_a_manager_cannot_train_in_a_department_without_content()
    {
        $empty = Department::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('learn.training-department.update'), ['department_id' => $empty->id])
            ->assertSessionHasErrors('department_id');
    }

    public function test_a_managers_content_is_scoped_to_the_chosen_department()
    {
        $this->publishedLesson();
        $ownTest = Test::factory()->pre()->create(['department_id' => $this->department->id]);

        $otherDepartment = Department::factory()->create();
        Test::factory()->pre()->create(['department_id' => $otherDepartment->id]);

        $manager = $this->manager();
        $manager->trainingDepartmentId = $this->department->id;

        $this->assertSame(
            $ownTest->id,
            app(JourneyService::class)->preTest($manager)?->id,
        );
    }

    public function test_a_manager_gets_403_starting_a_test_outside_their_department()
    {
        $this->publishedLesson();

        $otherDepartment = Department::factory()->create();
        $outsideTest = Test::factory()->pre()->create(['department_id' => $otherDepartment->id]);

        $this->actingAs($this->manager())
            ->withSession(['training_department_id' => $this->department->id])
            ->post(route('learn.tests.start', $outsideTest))
            ->assertForbidden();
    }

    public function test_an_employee_reaches_home_without_the_department_chooser()
    {
        $this->actingAs($this->learner())
            ->get(route('learn.home'))
            ->assertOk();
    }
}
