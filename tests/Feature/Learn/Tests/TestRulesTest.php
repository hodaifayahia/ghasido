<?php

namespace Tests\Feature\Learn\Tests;

use App\Enums\ActivityType;
use App\Enums\ResultsVisibility;
use App\Enums\TestAttemptStatus;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The four per-test rules of the admin Settings tab that the learner side
 * enforces: shuffle_options, single_attempt, show_answers and
 * motivational_message (TEST-03, TEST-04, TEST-06).
 */
class TestRulesTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<ActivityType>  $types
     */
    private function ruledTest(array $settings, array $types = [ActivityType::MultipleChoice]): Test
    {
        $test = Test::factory()->pre()->untimed()->create(['department_id' => $this->department->id]);
        $test->update(['settings' => array_merge($test->settings ?? [], $settings)]);

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

    private function questionPage(User $learner, Test $test, TestAttempt $attempt, int $number = 1): TestResponse
    {
        return $this->actingAs($learner)
            ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $attempt, 'number' => $number]))
            ->assertOk();
    }

    /**
     * @return list<string>
     */
    private function optionIds(TestResponse $response): array
    {
        /** @var list<array{id: string}> $options */
        $options = $response->viewData('page')['props']['activity']['items'][0]['options'];

        return array_column($options, 'id');
    }

    private function finish(User $learner, Test $test, TestAttempt $attempt, string $option = 'A'): void
    {
        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 1, 'answer' => ['i1' => $option],
        ])->assertRedirect();
    }

    // ---------------------------------------------------------- shuffle_options

    public function test_options_keep_their_stored_order_when_shuffling_is_off()
    {
        $test = $this->ruledTest(['shuffle_options' => false]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);

        $this->assertSame(['A', 'B', 'C', 'D'], $this->optionIds($this->questionPage($learner, $test, $attempt)));
    }

    public function test_options_are_shuffled_per_sitting_and_stable_across_refreshes()
    {
        $test = $this->ruledTest(['shuffle_options' => true]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);

        $first = $this->optionIds($this->questionPage($learner, $test, $attempt));
        $again = $this->optionIds($this->questionPage($learner, $test, $attempt));

        $this->assertNotSame(['A', 'B', 'C', 'D'], $first);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D'], $first);
        $this->assertSame($first, $again);
    }

    public function test_a_shuffled_answer_is_stored_and_scored_by_option_id()
    {
        $test = $this->ruledTest(['shuffle_options' => true]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);

        // The learner taps the row that shows option A, wherever it sits.
        $shown = $this->optionIds($this->questionPage($learner, $test, $attempt));
        $this->assertContains('A', $shown);
        $this->finish($learner, $test, $attempt, 'A');

        $row = Attempt::query()->where('test_attempt_id', $attempt->id)->firstOrFail();
        $this->assertSame(['i1' => 'A'], $row->raw_answer);
        $this->assertTrue($row->is_correct);
        $this->assertSame(TestAttemptStatus::Submitted, $attempt->refresh()->status);
    }

    public function test_ordering_items_are_not_touched_by_option_shuffling()
    {
        $test = $this->ruledTest(['shuffle_options' => true], [ActivityType::Ordering]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);

        $this->questionPage($learner, $test, $attempt)
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.items.0.sentences.0.id', 's2')
                ->where('activity.items.0.sentences.1.id', 's1')
                ->where('activity.items.0.sentences.2.id', 's3')
            );
    }

    // ---------------------------------------------------------- single_attempt

    public function test_a_single_attempt_test_cannot_be_started_again_after_submitting()
    {
        $test = $this->ruledTest(['single_attempt' => true]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt);

        $this->actingAs($learner)
            ->post(route('learn.tests.start', $test))
            ->assertRedirect(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertiaFlash('toast.type', 'info');

        $this->assertSame(1, TestAttempt::query()->where('user_id', $learner->id)->count());
    }

    public function test_a_single_attempt_test_still_resumes_an_open_sitting()
    {
        $test = $this->ruledTest(['single_attempt' => true]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);

        $this->actingAs($learner)
            ->post(route('learn.tests.start', $test))
            ->assertRedirect(route('learn.tests.question', ['test' => $test, 'attempt' => $attempt, 'number' => 1]));

        $this->assertSame(1, TestAttempt::query()->where('user_id', $learner->id)->count());
    }

    public function test_a_retake_opens_a_new_sitting_when_single_attempt_is_off()
    {
        $test = $this->ruledTest(['single_attempt' => false]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt);

        $second = $this->start($learner, $test);

        $this->assertNotSame($attempt->id, $second->id);
        $this->assertSame(2, $second->attempt_no);
    }

    // ------------------------------------------------------------ show_answers

    public function test_the_result_lists_each_answer_and_the_correct_one_when_answers_are_shown()
    {
        $test = $this->ruledTest(['show_answers' => true, 'shuffle_options' => true, 'results_visibility' => ResultsVisibility::Score->value]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt, 'B');

        $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/test/Result')
                ->has('review', 1)
                ->where('review.0.number', 1)
                ->where('review.0.yourAnswer', 'The restaurant is over there.')
                ->where('review.0.correctAnswer', 'Good evening! Can I help you with your luggage?')
                ->where('review.0.status', 'incorrect')
            );
    }

    public function test_no_correct_answer_is_sent_when_answers_are_not_shown()
    {
        $test = $this->ruledTest(['show_answers' => false, 'results_visibility' => ResultsVisibility::ScoreBreakdown->value]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt, 'B');

        $response = $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page->where('review', null));

        $json = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('Can I help you with your luggage', $json);
    }

    public function test_answers_stay_hidden_when_results_are_hidden()
    {
        $test = $this->ruledTest(['show_answers' => true, 'results_visibility' => ResultsVisibility::Hidden->value]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt);

        $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', null)
                ->where('review', null)
            );
    }

    public function test_the_question_page_never_carries_the_answer_even_with_answers_shown()
    {
        $test = $this->ruledTest(['show_answers' => true, 'results_visibility' => ResultsVisibility::Score->value]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);

        $response = $this->questionPage($learner, $test, $attempt)
            ->assertInertia(fn (Assert $page) => $page->missing('review'));

        $json = json_encode($response->viewData('page')['props']['activity'], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('"correct"', $json);
    }

    public function test_a_spoken_answer_is_reviewed_without_a_correct_answer()
    {
        $test = $this->ruledTest(['show_answers' => true, 'results_visibility' => ResultsVisibility::Score->value], [ActivityType::Speaking]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt, '');

        $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('review.0.correctAnswer', null)
                ->where('review.0.status', 'unanswered')
            );
    }

    // ---------------------------------------------------- motivational_message

    public function test_a_motivational_message_ends_the_test_even_with_hidden_results()
    {
        $test = $this->ruledTest(['motivational_message' => true, 'results_visibility' => ResultsVisibility::Hidden->value]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt);

        $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', null)
                ->where('motivation', fn (mixed $message): bool => is_string($message) && str_contains($message, 'Well done'))
            );
    }

    public function test_the_motivational_message_is_translated()
    {
        $test = $this->ruledTest(['motivational_message' => true]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt);

        app()->setLocale('ar');
        $expected = __('Well done for completing the test. This is your starting point — every lesson from here will build your confidence with guests.');
        app()->setLocale('en');

        $this->assertIsString($expected);
        $this->assertNotSame('', $expected);

        $this->actingAs($learner)
            ->withUnencryptedCookie('locale', 'ar')
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page->where('motivation', $expected));
    }

    public function test_no_motivational_message_when_it_is_off()
    {
        $test = $this->ruledTest(['motivational_message' => false]);
        $learner = $this->learner();
        $attempt = $this->start($learner, $test);
        $this->finish($learner, $test, $attempt);

        $this->actingAs($learner)
            ->get(route('learn.tests.result', ['test' => $test, 'attempt' => $attempt]))
            ->assertInertia(fn (Assert $page) => $page->where('motivation', null));
    }
}
