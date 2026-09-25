<?php

namespace Tests\Feature\Learn\Steps;

use App\Enums\BlockType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The Dialogue step (LESSON-06, CTRL-01..03; spec 0003 Part E, photo_5): the
 * conversation ships line by line, each with its speaker and playable clip.
 */
class DialogueStepTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_step_ships_its_dialogue_lines(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::Dialogue, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.type', 'dialogue')
                ->has('block.settings.lines', 2)
                ->where('block.settings.lines.0.speaker', 'staff')
                ->has('block.settings.lines.0.text')
                ->where('block.settings.lines.1.speaker', 'guest')
            );
    }
}
