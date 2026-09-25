<?php

namespace Tests\Feature\Learn;

use App\Enums\TestAttemptStatus;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The sidebar's Post-test entry and its intro on Home (JOURNEY-04; spec
 * 0005 §3.1). It used to announce "coming soon"; it now resumes, opens or
 * explains, and gate 2 still holds where the sitting starts.
 */
class PostTestEntryTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected Lesson $lesson;

    protected Test $postTest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        $this->lesson = $this->publishedLesson();
        $this->postTest = Test::factory()->post()->create(['department_id' => $this->department->id]);
    }

    private function finishLessons(User $learner): void
    {
        LessonCompletion::factory()->create(['user_id' => $learner->id, 'lesson_id' => $this->lesson->id]);
    }

    public function test_a_locked_post_test_lands_on_home_with_what_is_left()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $this->actingAs($learner)
            ->get(route('learn.post-test'))
            ->assertRedirect(route('learn.home'));

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('test.type', 'pre')
                ->where('journey.postTestUnlocked', false)
                ->etc());
    }

    public function test_once_every_lesson_is_done_home_offers_the_post_test_intro()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $this->finishLessons($learner);

        $this->actingAs($learner)
            ->get(route('learn.post-test'))
            ->assertRedirect(route('learn.home'));

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('test.type', 'post')
                ->where('test.id', $this->postTest->id)
                ->where('attemptInProgress', null)
                ->etc());
    }

    public function test_an_open_post_test_sitting_is_resumed()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $this->finishLessons($learner);
        $sitting = TestAttempt::factory()->create([
            'user_id' => $learner->id,
            'test_id' => $this->postTest->id,
            'status' => TestAttemptStatus::InProgress,
            'submitted_at' => null,
        ]);

        $this->actingAs($learner)
            ->get(route('learn.post-test'))
            ->assertRedirect(route('learn.tests.question', ['test' => $this->postTest, 'attempt' => $sitting, 'number' => 1]));
    }

    public function test_a_taken_post_test_points_to_the_results()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $this->finishLessons($learner);
        TestAttempt::factory()->submitted()->create(['user_id' => $learner->id, 'test_id' => $this->postTest->id]);

        $this->actingAs($learner)
            ->get(route('learn.post-test'))
            ->assertRedirect(route('learn.progress'));

        // Home goes back to its "continue" card instead of the intro.
        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page->where('test.type', 'pre')->etc());
    }

    public function test_gate_two_still_holds_at_the_start_route()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $this->actingAs($learner)
            ->post(route('learn.tests.start', $this->postTest))
            ->assertForbidden();
    }
}
