<?php

namespace Tests\Feature\Learn;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\EnglishLevel;
use App\Enums\GenerationStatus;
use App\Enums\ResultsVisibility;
use App\Jobs\GenerateLearnerCoaching;
use App\Models\AiInsight;
use App\Models\AiUsage;
use App\Models\LessonCompletion;
use App\Models\PhrasebookItem;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Learning\LearnerCoach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The learner's AI coach (spec 0005 §3.5): written by a queued job from the
 * learner's own figures, metered without spending their points, refreshed
 * only when something changed, and paired with a server-chosen next step.
 */
class LearnerCoachTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    private function activeLearner(): User
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        LessonCompletion::factory()->create(['user_id' => $learner->id, 'lesson_id' => $this->publishedLesson()->id]);

        return $learner;
    }

    public function test_nothing_is_generated_before_the_learner_has_done_anything()
    {
        Queue::fake();
        $learner = $this->learner();

        $this->actingAs($learner)->get(route('learn.progress'))
            ->assertInertia(fn (Assert $page) => $page->where('coach.status', 'empty')->etc());

        Queue::assertNotPushed(GenerateLearnerCoaching::class);
    }

    public function test_the_first_visit_queues_a_summary_and_the_card_waits_for_it()
    {
        Queue::fake();
        $learner = $this->activeLearner();

        $this->actingAs($learner)->get(route('learn.progress'))
            ->assertInertia(fn (Assert $page) => $page->where('coach.status', 'pending')->etc());

        Queue::assertPushed(GenerateLearnerCoaching::class, fn (GenerateLearnerCoaching $job): bool => $job->userId === $learner->id);

        // A second visit while it is being written queues nothing more.
        $this->actingAs($learner)->get(route('learn.progress'));
        Queue::assertPushed(GenerateLearnerCoaching::class, 1);
    }

    public function test_the_job_stores_the_summary_and_meters_it_without_spending_points()
    {
        $this->app->instance(AiProvider::class, new FakeAiProvider);
        $learner = $this->activeLearner();

        // Sync queue: the job runs inside the request.
        $this->actingAs($learner)->get(route('learn.progress'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('coach.status', 'ready')
                ->where('coach.headline', 'Good progress, keep your rhythm going.')
                ->has('coach.strengths', 1)
                ->etc());

        $usage = AiUsage::query()->where('feature', AiFeature::LearnerCoach->value)->sole();
        $this->assertSame($learner->id, $usage->user_id);
        $this->assertSame(0, (int) $usage->points_charged);
    }

    public function test_it_is_rewritten_only_when_the_figures_change_and_the_cooldown_has_passed()
    {
        Queue::fake();
        $learner = $this->activeLearner();
        $coach = app(LearnerCoach::class);
        AiInsight::query()->create([
            'subject_type' => $learner->getMorphClass(),
            'subject_id' => $learner->id,
            'kind' => AiInsight::KIND_LEARNER_COACH,
            'status' => GenerationStatus::Done,
            'payload' => ['headline' => 'Old', 'strengths' => [], 'focus' => [], 'tip' => ''],
            'fingerprint' => LearnerCoach::fingerprint($coach->context($learner)),
            'generated_at' => now()->subDay(),
        ]);

        $coach->present($learner);
        Queue::assertNothingPushed();

        // New activity, but the last summary is only an hour old.
        AiInsight::query()->update(['generated_at' => now()->subHour()]);
        LessonCompletion::factory()->create(['user_id' => $learner->id, 'lesson_id' => $this->publishedLesson()->id]);
        $coach->present($learner);
        Queue::assertNothingPushed();

        $this->travel(LearnerCoach::COOLDOWN_HOURS)->hours();
        $this->assertSame('refreshing', $coach->present($learner)['status']);
        Queue::assertPushed(GenerateLearnerCoaching::class, 1);
    }

    public function test_a_provider_failure_leaves_a_failed_card_not_an_error()
    {
        $this->mock(AiProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('coachLearner')->andThrow(new RuntimeException('provider down'));
        });
        $learner = $this->activeLearner();

        $this->actingAs($learner)->get(route('learn.progress'))->assertOk();

        $insight = AiInsight::for($learner, AiInsight::KIND_LEARNER_COACH);
        $this->assertSame(GenerationStatus::Failed, $insight?->status);
        $this->assertSame('failed', app(LearnerCoach::class)->present($learner)['status']);
    }

    public function test_the_next_step_is_chosen_by_the_server()
    {
        Queue::fake();
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson();
        LessonCompletion::factory()->create(['user_id' => $learner->id, 'lesson_id' => $lesson->id]);
        PhrasebookItem::factory()->create(['user_id' => $learner->id]);

        // Every lesson is done and there is no Post-test: phrases to review.
        $this->assertSame(route('learn.phrasebook.review'), app(LearnerCoach::class)->nextStep($learner)['url']);
    }

    public function test_a_test_result_reaches_the_coach_only_as_far_as_the_learner_may_see_it()
    {
        // TEST-04: what the admin hides from the learner is not in the
        // model's data either, so the coach cannot mention a score, a skill
        // figure or the level derived from them (spec 0005 §5.1).
        Queue::fake();
        $learner = $this->learner();
        $test = Test::factory()->pre()->withResultsVisibility(ResultsVisibility::Hidden)->create(['department_id' => $this->department->id]);
        TestAttempt::factory()->submitted(5, 25)->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
            'breakdown' => ['Listening' => ['score' => 1, 'max' => 5]],
        ]);
        $learner->forceFill(['english_level' => EnglishLevel::Beginner])->save();
        $coach = app(LearnerCoach::class);

        $hidden = $coach->context($learner);
        $this->assertTrue($hidden['pre_test']['taken']);
        $this->assertNull($hidden['pre_test']['percent']);
        $this->assertSame([], $hidden['skills_percent']);
        $this->assertNull($hidden['weakest_skill']);
        $this->assertNull($hidden['english_level']);
        // Taking the test still counts as activity: a summary is queued.
        $this->assertSame('pending', $coach->present($learner)['status']);
        Queue::assertPushed(GenerateLearnerCoaching::class);

        $this->setVisibility($test, ResultsVisibility::Score);
        $scoreOnly = $coach->context($learner);
        $this->assertSame(20, $scoreOnly['pre_test']['percent']);
        $this->assertSame([], $scoreOnly['skills_percent']);
        $this->assertSame('Beginner', $scoreOnly['english_level']);

        $this->setVisibility($test, ResultsVisibility::ScoreBreakdown);
        $full = $coach->context($learner);
        $this->assertSame(['Listening' => 20], $full['skills_percent']);
        $this->assertSame('Listening', $full['weakest_skill']);
    }

    private function setVisibility(Test $test, ResultsVisibility $visibility): void
    {
        $test->forceFill(['settings' => [...($test->settings ?? []), 'results_visibility' => $visibility->value]])->save();
    }

    public function test_the_context_carries_only_the_learners_own_figures()
    {
        $learner = $this->activeLearner();
        $other = $this->learner(['username' => 'amine', 'name' => 'Amine Khelifi']);
        LessonCompletion::factory()->count(3)->create(['user_id' => $other->id]);

        $context = app(LearnerCoach::class)->context($learner);

        $this->assertSame(strtok($learner->name, ' '), $context['first_name']);
        $this->assertSame(1, $context['lessons']['completed']);
        $this->assertStringNotContainsString('Amine', (string) json_encode($context));
    }
}
