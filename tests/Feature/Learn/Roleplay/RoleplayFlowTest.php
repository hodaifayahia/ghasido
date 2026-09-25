<?php

namespace Tests\Feature\Learn\Roleplay;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\RoleplayStatus;
use App\Jobs\GenerateRoleplayReply;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * AI role-play invariants (RP-05, RP-06, RP-07, RP-11, RP-13, AIL-03, DATA-05;
 * spec 0003 Part E, Part C).
 */
class RoleplayFlowTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected Lesson $lesson;

    protected Block $block;

    protected AiScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        $this->lesson = $this->publishedLesson([BlockType::AiRoleplay, BlockType::Complete]);
        $this->block = $this->lesson->visibleBlocks()->firstOrFail();
        $this->scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
            'slug' => 'check-in',
            'attempts_allowed' => 3,
            'min_turns' => 2,
            'input_mode' => 'text',
        ]);
    }

    private function startUrl(): string
    {
        return route('learn.roleplay.start', ['lesson' => $this->lesson, 'block' => $this->block, 'scenario' => $this->scenario]);
    }

    public function test_starting_opens_an_attempt_and_queues_the_opening_line()
    {
        Queue::fake();
        $learner = $this->learner();

        $this->actingAs($learner)->post($this->startUrl())->assertRedirect();

        $attempt = RoleplayAttempt::query()->where('user_id', $learner->id)->firstOrFail();
        $this->assertSame(RoleplayStatus::InProgress, $attempt->status);
        $this->assertSame(1, $attempt->attempt_no);
        $this->assertFalse($attempt->is_preview);

        Queue::assertPushed(GenerateRoleplayReply::class);
    }

    public function test_attempts_are_capped_at_the_scenario_limit()
    {
        $learner = $this->learner();

        RoleplayAttempt::factory()->count(3)->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario->id,
            'is_preview' => false,
            'status' => RoleplayStatus::Completed,
        ]);

        $this->actingAs($learner)->post($this->startUrl())->assertRedirect();

        $this->assertDatabaseCount('roleplay_attempts', 3);
    }

    public function test_a_message_appends_the_turn_and_queues_the_reply()
    {
        Queue::fake();
        $learner = $this->learner();
        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario->id,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [],
        ]);

        $this->actingAs($learner)
            ->post(route('learn.roleplay.message', ['attempt' => $attempt]), ['text' => 'Good afternoon!'])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertCount(1, $attempt->transcript);
        $this->assertSame('employee', $attempt->transcript[0]['role']);
        $this->assertSame('Good afternoon!', $attempt->transcript[0]['text']);

        Queue::assertPushed(GenerateRoleplayReply::class);
    }

    public function test_ending_evaluates_and_stores_the_scores_with_the_transcript_intact()
    {
        $learner = $this->learner();
        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario->id,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [
                ['role' => 'guest', 'text' => 'Hello!', 'at' => now()->toIso8601String()],
                ['role' => 'employee', 'text' => 'Good afternoon!', 'at' => now()->toIso8601String()],
            ],
        ]);

        $this->actingAs($learner)
            ->post(route('learn.roleplay.end', ['attempt' => $attempt]))
            ->assertRedirect(route('learn.roleplay.feedback', ['attempt' => $attempt]));

        $attempt->refresh();
        $this->assertSame(RoleplayStatus::Completed, $attempt->status);
        $this->assertNotNull($attempt->overall_score);
        $this->assertNotEmpty($attempt->criteria_scores);
        $this->assertNotNull($attempt->feedback);
        $this->assertCount(2, $attempt->transcript);
    }

    public function test_a_preview_attempt_never_counts_against_the_limit()
    {
        $learner = $this->learner();
        RoleplayAttempt::factory()->count(3)->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario->id,
            'is_preview' => true,
            'status' => RoleplayStatus::Completed,
        ]);

        Queue::fake();
        $this->actingAs($learner)->post($this->startUrl())->assertRedirect();

        $this->assertSame(1, RoleplayAttempt::query()->counted()->where('user_id', $learner->id)->count());
    }

    public function test_another_learner_cannot_open_someone_elses_attempt()
    {
        $learner = $this->learner();
        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario->id,
            'status' => RoleplayStatus::InProgress,
        ]);

        $intruder = User::factory()->employee()->firstLoginDone()->forHotel($this->hotel, $this->department)->create(['username' => 'intruder']);

        $this->actingAs($intruder)
            ->get(route('learn.roleplay.attempt', ['attempt' => $attempt]))
            ->assertForbidden();
    }

    public function test_the_ai_daily_limit_is_shown_as_a_flash_error()
    {
        config()->set('guesvia.ai.limits.per_employee_daily_turns', 0);

        $learner = $this->learner();

        $this->actingAs($learner)
            ->from(route('learn.lessons.step', ['lesson' => $this->lesson, 'block' => $this->block]))
            ->post($this->startUrl())
            ->assertRedirect();

        $this->assertDatabaseCount('roleplay_attempts', 0);
    }
}
