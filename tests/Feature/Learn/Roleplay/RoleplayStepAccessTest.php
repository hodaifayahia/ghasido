<?php

namespace Tests\Feature\Learn\Roleplay;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Hotel;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * A role-play is reached through its lesson step, and the step is checked
 * like the lesson player checks it (JOURNEY-01, ROLE-02; spec 0005 §1.3).
 */
class RoleplayStepAccessTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected Lesson $lesson;

    protected Block $block;

    protected AiScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->setUpLearnerFixtures();
        $this->lesson = $this->publishedLesson([BlockType::AiRoleplay, BlockType::Complete]);
        $this->block = $this->lesson->visibleBlocks()->firstOrFail();
        $this->scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
            'input_mode' => 'text',
        ]);
        $this->block->scenarios()->attach($this->scenario->id, ['position' => 1]);
    }

    /**
     * @return array<string, mixed>
     */
    private function params(?Lesson $lesson = null, ?Block $block = null, ?AiScenario $scenario = null): array
    {
        return [
            'lesson' => $lesson ?? $this->lesson,
            'block' => $block ?? $this->block,
            'scenario' => $scenario ?? $this->scenario,
        ];
    }

    public function test_the_linked_step_opens_and_starts()
    {
        $learner = $this->learner();

        $this->actingAs($learner)->get(route('learn.roleplay.ready', $this->params()))->assertOk();
        $this->actingAs($learner)->post(route('learn.roleplay.start', $this->params()))->assertRedirect();

        $this->assertDatabaseCount('roleplay_attempts', 1);
    }

    public function test_the_pre_test_gate_holds_for_role_play_too()
    {
        $this->publishedPreTest();
        $learner = $this->learner();

        $this->actingAs($learner)->get(route('learn.roleplay.ready', $this->params()))->assertForbidden();
        $this->actingAs($learner)->post(route('learn.roleplay.start', $this->params()))->assertForbidden();
        $this->actingAs($learner)->postJson(route('learn.roleplay.voice.start', $this->params()))->assertForbidden();

        $this->assertDatabaseCount('roleplay_attempts', 0);
    }

    public function test_a_block_of_another_lesson_is_a_404()
    {
        $other = $this->publishedLesson([BlockType::AiRoleplay, BlockType::Complete]);
        $foreignBlock = $other->visibleBlocks()->firstOrFail();
        $foreignBlock->scenarios()->attach($this->scenario->id, ['position' => 1]);

        $this->actingAs($this->learner())
            ->post(route('learn.roleplay.start', $this->params(block: $foreignBlock)))
            ->assertNotFound();

        $this->assertDatabaseCount('roleplay_attempts', 0);
    }

    public function test_a_scenario_the_step_does_not_offer_is_a_404()
    {
        $unlinked = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
            'input_mode' => 'text',
        ]);

        $this->actingAs($this->learner())
            ->post(route('learn.roleplay.start', $this->params(scenario: $unlinked)))
            ->assertNotFound();

        $this->assertDatabaseCount('roleplay_attempts', 0);
    }

    public function test_another_hotels_lesson_is_refused()
    {
        $foreignLesson = $this->publishedLesson(
            [BlockType::AiRoleplay, BlockType::Complete],
            hotel: Hotel::factory()->create(),
        );
        $foreignBlock = $foreignLesson->visibleBlocks()->firstOrFail();
        $foreignBlock->scenarios()->attach($this->scenario->id, ['position' => 1]);

        $this->actingAs($this->learner())
            ->post(route('learn.roleplay.start', $this->params(lesson: $foreignLesson, block: $foreignBlock)))
            ->assertForbidden();

        $this->assertDatabaseCount('roleplay_attempts', 0);
    }
}
