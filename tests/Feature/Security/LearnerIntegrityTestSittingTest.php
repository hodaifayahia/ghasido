<?php

namespace Tests\Feature\Security;

use App\Enums\ActivityType;
use App\Enums\TestAttemptStatus;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\TestRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): a closed sitting takes no more
 * answers, a finish that arrives after the deadline follows the timeout
 * rule instead of counting as an on-time submit (TIME-03, TIME-05,
 * TEST-06), and a test page carries no hint (TEST-03).
 */
class LearnerIntegrityTestSittingTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @param  list<ActivityType>  $types
     */
    private function timedTest(string $onTimeout = 'submit', array $types = [ActivityType::MultipleChoice, ActivityType::MultipleChoice]): Test
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => [
                'time_limit_seconds' => 600,
                'results_visibility' => 'score',
                'on_timeout' => $onTimeout,
            ],
        ]);

        foreach ($types as $index => $type) {
            $activity = Activity::factory()->ofType($type)->create();
            $test->questions()->create(['activity_id' => $activity->id, 'position' => $index + 1]);
        }

        return $test;
    }

    private function start(User $learner, Test $test): TestAttempt
    {
        $this->actingAs($learner)->post(route('learn.tests.start', $test))->assertRedirect();

        return TestAttempt::query()->where('user_id', $learner->id)->latest('id')->firstOrFail();
    }

    public function test_a_submitted_sitting_refuses_a_rewritten_answer()
    {
        $learner = $this->learner();
        $test = $this->timedTest();
        $attempt = $this->start($learner, $test);

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'B'], 'to' => 2,
        ]);
        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 2, 'answer' => ['i1' => 'B'],
        ])->assertRedirect(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]));

        $this->assertSame(TestAttemptStatus::Submitted, $attempt->refresh()->status);

        // After the result: try to rewrite question 1 with the right option.
        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'A'], 'to' => 1,
        ])->assertRedirect(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]));

        $first = $test->questions()->orderBy('position')->firstOrFail();
        $row = Attempt::query()
            ->where('test_attempt_id', $attempt->id)
            ->where('activity_id', $first->activity_id)
            ->firstOrFail();

        $this->assertSame(['i1' => 'B'], $row->raw_answer);
        $this->assertCount(1, $row->answer_history ?? []);
    }

    public function test_an_expired_sitting_refuses_a_new_answer()
    {
        $learner = $this->learner();
        $test = $this->timedTest('unanswered');
        $attempt = $this->start($learner, $test);
        $attempt->update(['status' => TestAttemptStatus::Expired, 'submitted_at' => now()]);

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'A'], 'to' => 1,
        ])->assertRedirect(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]));

        $this->assertSame(0, Attempt::query()->where('test_attempt_id', $attempt->id)->count());
    }

    public function test_the_runner_itself_refuses_an_answer_on_a_closed_sitting()
    {
        $learner = $this->learner();
        $test = $this->timedTest();
        $attempt = $this->start($learner, $test);
        $attempt->update(['status' => TestAttemptStatus::Submitted, 'submitted_at' => now()]);

        try {
            app(TestRunner::class)->saveAnswer($attempt, $test->questions()->firstOrFail(), ['i1' => 'A'], 100);
            $this->fail('A closed sitting accepted an answer.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(0, Attempt::query()->where('test_attempt_id', $attempt->id)->count());
    }

    public function test_a_late_finish_follows_the_unanswered_timeout_rule()
    {
        $learner = $this->learner();
        $test = $this->timedTest('unanswered');
        $attempt = $this->start($learner, $test);

        $this->travel(11)->minutes();

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 1, 'answer' => ['i1' => 'A'],
        ])->assertRedirect(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]));

        $this->assertSame(TestAttemptStatus::Expired, $attempt->refresh()->status);
        // The answer sent after the deadline is not stored.
        $this->assertSame(0, Attempt::query()->where('test_attempt_id', $attempt->id)->whereNotNull('raw_answer')->count());
    }

    public function test_a_late_finish_under_the_submit_rule_scores_only_what_was_answered_in_time()
    {
        $learner = $this->learner();
        $test = $this->timedTest('submit');
        $attempt = $this->start($learner, $test);

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => 'A'], 'to' => 2,
        ]);

        $this->travel(11)->minutes();

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 2, 'answer' => ['i1' => 'A'],
        ])->assertRedirect(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]));

        $attempt->refresh();
        $this->assertSame(TestAttemptStatus::Submitted, $attempt->status);

        $second = $test->questions()->orderBy('position')->skip(1)->firstOrFail();
        $late = Attempt::query()
            ->where('test_attempt_id', $attempt->id)
            ->where('activity_id', $second->activity_id)
            ->first();

        $this->assertTrue($late === null || $late->raw_answer === null);
        $this->assertSame('1.00', $attempt->score);
    }

    public function test_a_test_page_carries_no_hint()
    {
        $learner = $this->learner();
        $test = $this->timedTest('submit', [ActivityType::WatchRespond]);
        $attempt = $this->start($learner, $test);

        $response = $this->actingAs($learner)
            ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $attempt, 'number' => 1]))
            ->assertOk();

        $json = json_encode($response->viewData('page')['props']['activity']);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('"hint"', $json);
        $this->assertStringNotContainsString('Choose the best response.', $json);
    }
}
