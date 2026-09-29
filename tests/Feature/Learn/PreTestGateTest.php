<?php

namespace Tests\Feature\Learn;

use App\Enums\BlockType;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gate 1 (JOURNEY-01, JOURNEY-02, ROLE-02; spec 0003 Part E).
 *
 * A lesson step is a 403 until the Pre-test is submitted, and stays a 403
 * for a lesson outside the learner's hotel or department afterwards. The
 * shared `journey` prop reports the same gate.
 */
class PreTestGateTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_a_lesson_step_is_forbidden_before_the_pre_test()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $this->publishedPreTest();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertForbidden();

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', ['lesson' => $lesson]))
            ->assertForbidden();

        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $block]))
            ->assertForbidden();

        $this->assertDatabaseCount('block_completions', 0);
    }

    public function test_an_all_departments_pre_test_gates_a_department_without_its_own()
    {
        // Client request 2026-09-29: the Pre-test comes first for every
        // employee, even in a department that has no Pre-test of its own.
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $shared = $this->publishedPreTest();
        $shared->forceFill(['department_id' => null])->save();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertForbidden();

        $this->assertSame($shared->id, app(\App\Services\Learning\JourneyService::class)->preTest($learner)?->id);

        // The department's own Pre-test wins over the all-departments one.
        $own = $this->publishedPreTest();
        $this->assertSame($own->id, app(\App\Services\Learning\JourneyService::class)->preTest($learner)?->id);
    }

    public function test_a_lesson_step_opens_once_the_pre_test_is_submitted()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $this->submitPreTest($learner);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/lesson/Step')
                ->where('lesson.id', $lesson->id)
                ->where('lesson.positionInCourse', 1)
                ->where('lesson.courseLessonCount', 1)
                ->where('lesson.department.name', $this->department->name)
                ->where('block.id', $block->id)
                ->where('block.type', 'situation')
                ->where('block.heading', 'Situation (Intro)')
                ->has('steps', 2)
                ->where('steps.0.current', true)
                ->where('steps.0.done', false)
                ->where('steps.1.label', 'Complete')
                ->where('prevUrl', null)
                ->where('nextUrl', route('learn.lessons.step', ['lesson' => $lesson, 'block' => $lesson->visibleBlocks()->get()->last()]))
                ->where('completeUrl', route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $block]))
            );
    }

    public function test_the_pre_test_never_unlocks_another_hotels_or_departments_lesson()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $foreign = $this->publishedLesson([BlockType::Situation], Hotel::factory()->create());
        $foreignBlock = $foreign->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $foreign, 'block' => $foreignBlock]))
            ->assertForbidden();
    }

    public function test_the_shared_journey_prop_follows_the_gate()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $first = $lesson->visibleBlocks()->firstOrFail();
        $this->publishedPreTest();

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Home')
                ->where('journey.preTestSubmitted', false)
                ->where('journey.lessonsUnlocked', false)
                ->where('journey.lessonsTotal', 1)
                ->where('journey.lessonsCompleted', 0)
                ->where('journey.postTestUnlocked', false)
                ->where('journey.certificateAvailable', false)
                ->where('journey.continueUrl', null)
                ->where('continueLesson', null)
            );

        $test = $this->submitPreTest($learner);

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('journey.preTestSubmitted', true)
                ->where('journey.continueUrl', route('learn.lessons.step', ['lesson' => $lesson, 'block' => $first]))
                ->where('continueLesson.id', $lesson->id)
                ->where('test.id', $test->id)
                ->where('test.startUrl', route('learn.tests.start', ['test' => $test]))
                ->where('attemptInProgress', null)
            );
    }

    public function test_the_lessons_list_marks_every_lesson_locked_before_the_pre_test()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $this->publishedPreTest();

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Lessons')
                ->has('courses', 1)
                ->has('courses.0.units', 1)
                ->has('courses.0.units.0.lessons', 1)
                ->where('courses.0.units.0.lessons.0.id', $lesson->id)
                ->where('courses.0.units.0.lessons.0.locked', true)
                ->where('courses.0.units.0.lessons.0.completed', false)
                ->where('courses.0.units.0.lessons.0.stepCount', 2)
                ->where('courses.0.units.0.lessons.0.url', route('learn.lessons.show', ['lesson' => $lesson]))
            );

        $this->submitPreTest($learner);

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertInertia(fn (Assert $page) => $page->where('courses.0.units.0.lessons.0.locked', false));
    }

    public function test_lessons_are_open_when_the_department_has_no_published_pre_test()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $first = $lesson->visibleBlocks()->firstOrFail();

        // A draft Pre-test, or another department's published one, does not
        // count: gate 1 applies only to a Pre-test this learner can sit.
        Test::factory()->pre()->draft()->create(['department_id' => $this->department->id]);
        Test::factory()->pre()->create(['department_id' => Department::factory()->create()->id]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $first]))
            ->assertOk();

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('courses.0.units.0.lessons.0.locked', false)
                ->where('journey.preTestSubmitted', false)
                ->where('journey.lessonsUnlocked', true)
                ->where('journey.continueUrl', route('learn.lessons.step', ['lesson' => $lesson, 'block' => $first]))
            );
    }

    public function test_a_hotel_pre_test_locks_lessons_until_it_is_taken()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $first = $lesson->visibleBlocks()->firstOrFail();
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'hotel_id' => $this->hotel->id,
        ]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $first]))
            ->assertForbidden();

        TestAttempt::factory()->submitted()->create(['user_id' => $learner->id, 'test_id' => $test->id]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $first]))
            ->assertOk();
    }

    public function test_a_manager_with_a_selected_department_can_open_the_learner_area()
    {
        $manager = User::factory()->manager()->forHotel($this->hotel, $this->department)->create();

        $this->actingAs($manager)
            ->get(route('learn.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employee/Home'));
    }
}
