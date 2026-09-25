<?php

namespace Tests\Feature\Learn\Tests;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\JourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * A learner reaches only the published Pre/Post-test of their own
 * department, shared or their hotel's (ROLE-02, ORG-04, JOURNEY-01,
 * JOURNEY-03), and a test page carries no Arabic, no answer key, no model
 * answer and no listening script (CTRL-04, TEST-03, CTRL-05).
 */
class TestsVisibilityTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    private function questionTest(array $attributes = [], ActivityType $type = ActivityType::MultipleChoice): Test
    {
        $test = Test::factory()->pre()->create($attributes + ['department_id' => $this->department->id]);
        $activity = Activity::factory()->ofType($type)->create();
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        return $test;
    }

    public function test_an_employee_of_another_department_gets_403()
    {
        $test = $this->questionTest(['department_id' => Department::factory()->create()->id]);

        $this->actingAs($this->learner())
            ->post(route('learn.tests.start', $test))
            ->assertForbidden();

        $this->assertDatabaseCount('test_attempts', 0);
    }

    public function test_an_employee_of_another_hotel_gets_403_on_a_hotel_test()
    {
        $test = $this->questionTest(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($this->learner())
            ->post(route('learn.tests.start', $test))
            ->assertForbidden();
    }

    public function test_a_draft_test_is_not_sittable()
    {
        $test = $this->questionTest();
        $test->update(['status' => 'draft']);

        $this->actingAs($this->learner())
            ->post(route('learn.tests.start', $test))
            ->assertForbidden();
    }

    public function test_someone_elses_sitting_is_forbidden()
    {
        $test = $this->questionTest();
        $owner = $this->learner();
        $this->actingAs($owner)->post(route('learn.tests.start', $test))->assertRedirect();
        $sitting = TestAttempt::query()->firstOrFail();

        $intruder = User::factory()->employee()->firstLoginDone()->forHotel($this->hotel, $this->department)->create(['username' => 'intruder']);

        $this->actingAs($intruder)
            ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $sitting, 'number' => 1]))
            ->assertForbidden();
    }

    public function test_the_learners_own_hotel_test_wins_over_a_shared_one()
    {
        $shared = $this->questionTest();
        $own = $this->questionTest(['hotel_id' => $this->hotel->id]);
        $learner = $this->learner();

        $this->assertSame($own->id, app(JourneyService::class)->preTest($learner)?->id);
        $this->assertNotSame($shared->id, $own->id);
    }

    public function test_a_listening_question_ships_only_its_stored_audio()
    {
        $test = $this->questionTest([], ActivityType::BestResponse);
        $activity = $test->questions()->firstOrFail()->activity()->firstOrFail();
        $payload = $activity->payload;
        $payload['items'][0]['model_answer'] = 'Of course, right away.';
        $payload['items'][0]['arabic'] = 'بالطبع';
        $activity->update(['payload' => $payload, 'prompt_arabic' => 'مرحبا']);

        $learner = $this->learner();
        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $sitting = TestAttempt::query()->firstOrFail();

        $response = $this->actingAs($learner)
            ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $sitting, 'number' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/test/Question')
                ->where('activity.showMeaningEnabled', false)
                ->where('activity.promptArabic', null)
                ->has('activity.items.0.guest_audio_text_audio')
                ->missing('activity.items.0.guest_audio_text')
            );

        $json = json_encode($response->viewData('page')['props']['activity'], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('"correct"', $json);
        $this->assertStringNotContainsString('model_answer', $json);
        $this->assertStringNotContainsString('arabic', $json);
        $this->assertStringNotContainsString('م', $json);
    }
}
