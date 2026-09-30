<?php

namespace Tests\Feature\Learn\Tests;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Enums\TestAttemptStatus;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The Pre/Post-test runner's invariants (TEST-05, TEST-06, TIME-05, CTRL-04,
 * TEST-03, TEST-04, DATA-01, DATA-08, DATA-11, JOURNEY-01; spec 0003 Part E).
 */
class TestRunnerTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function preTest(int $questions = 3, array $settings = []): Test
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => $settings + [
                'time_limit_seconds' => 1200,
                'results_visibility' => 'score',
                'on_timeout' => 'submit',
            ],
        ]);

        for ($position = 1; $position <= $questions; $position++) {
            $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
            $test->questions()->create(['activity_id' => $activity->id, 'position' => $position]);
        }

        return $test;
    }

    public function test_starting_a_sitting_stamps_a_server_deadline()
    {
        $learner = $this->learner();
        $test = $this->preTest();

        $this->actingAs($learner)->post(route('learn.tests.start', $test))->assertRedirect();

        $attempt = TestAttempt::query()->where('user_id', $learner->id)->firstOrFail();

        $this->assertSame(TestAttemptStatus::InProgress, $attempt->status);
        $this->assertNotNull($attempt->started_at);
        $this->assertNotNull($attempt->deadline_at);
        $this->assertTrue($attempt->deadline_at->greaterThan($attempt->started_at));
    }

    public function test_a_test_page_never_carries_arabic_or_the_correct_answer()
    {
        $learner = $this->learner();
        $test = $this->preTest(1);
        $test->questions()->firstOrFail()->activity()->firstOrFail()->update(['prompt_arabic' => 'مرحبا']);

        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $attempt = TestAttempt::query()->firstOrFail();

        $response = $this->actingAs($learner)->get(route('learn.tests.question', [
            'test' => $test, 'attempt' => $attempt, 'number' => 1,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('employee/test/Question')
            ->where('activity.promptArabic', null)
            ->where('activity.showMeaningEnabled', false)
        );

        $page = $response->viewData('page');
        $activityJson = json_encode($page['props']['activity']);
        $this->assertIsString($activityJson);
        $this->assertStringNotContainsString('"correct"', $activityJson);
        $this->assertStringNotContainsString('arabic', $activityJson);
    }

    public function test_answering_stores_one_verbatim_row_per_question_with_history()
    {
        $learner = $this->learner();
        $test = $this->preTest(2);
        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $attempt = TestAttempt::query()->firstOrFail();

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'A'], 'to' => 1, 'time_taken_ms' => 1000,
        ])->assertRedirect();

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'B'], 'to' => 2, 'time_taken_ms' => 2400,
        ])->assertRedirect();

        $rows = Attempt::query()->where('test_attempt_id', $attempt->id)->get();

        $this->assertCount(1, $rows);
        $row = $rows->firstOrFail();
        $this->assertSame(['i1' => 'B'], $row->raw_answer);
        $this->assertCount(2, $row->answer_history ?? []);
        $this->assertSame(2400, $row->time_taken_ms);
        $this->assertNotNull($row->activity_version_id);
    }

    public function test_finishing_scores_the_sitting_and_opens_the_lessons_gate()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $test = $this->preTest(2);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertForbidden();

        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $attempt = TestAttempt::query()->firstOrFail();

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'A'], 'to' => 2,
        ]);
        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 2, 'answer' => ['i1' => 'A'],
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame(TestAttemptStatus::Submitted, $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
        $this->assertNotNull($attempt->score);
        $this->assertNotNull($attempt->max_score);

        // The level is the learner's choice: a sitting never changes it
        // (client decision 2026-09-30).
        $level = $learner->english_level;
        $learner->refresh();
        $this->assertSame($level, $learner->english_level);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk();
    }

    public function test_another_learner_cannot_open_someone_elses_sitting()
    {
        $learner = $this->learner();
        $test = $this->preTest(1);
        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $attempt = TestAttempt::query()->firstOrFail();

        $intruder = User::factory()
            ->employee()
            ->firstLoginDone()
            ->forHotel($this->hotel, $this->department)
            ->create(['username' => 'intruder']);

        $this->actingAs($intruder)
            ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $attempt, 'number' => 1]))
            ->assertForbidden();
    }

    public function test_hidden_results_visibility_reveals_no_score()
    {
        $learner = $this->learner();
        $test = $this->preTest(1, ['results_visibility' => 'hidden']);
        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $attempt = TestAttempt::query()->firstOrFail();

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 1, 'answer' => ['i1' => 'A'],
        ]);

        $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/test/Result')
                ->where('visibility', 'hidden')
                ->where('result', null)
            );
    }
}
