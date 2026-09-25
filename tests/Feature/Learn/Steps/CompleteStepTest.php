<?php

namespace Tests\Feature\Learn\Steps;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\LexiconItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The Lesson Completed screen (LESSON-05; spec 0003 Part E, photo_19): the
 * summary lists one ticked row per content block, with the vocabulary and
 * expression counts, and the finish POST records the lesson completion.
 */
class CompleteStepTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_summary_lists_a_ticked_row_per_content_block_with_counts(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([
            BlockType::Situation,
            BlockType::Vocabulary,
            BlockType::Expressions,
            BlockType::ListenRepeat,
            BlockType::Dialogue,
            BlockType::Video,
            BlockType::Complete,
        ]);

        $blocks = $lesson->visibleBlocks()->get();
        /** @var Block $vocab */
        $vocab = $blocks->firstWhere('type', BlockType::Vocabulary);
        /** @var Block $expr */
        $expr = $blocks->firstWhere('type', BlockType::Expressions);
        /** @var Block $complete */
        $complete = $blocks->firstWhere('type', BlockType::Complete);

        $vocab->lexiconItems()->attach([
            LexiconItem::factory()->create()->id => ['position' => 1],
            LexiconItem::factory()->create()->id => ['position' => 2],
        ]);
        $expr->lexiconItems()->attach([
            LexiconItem::factory()->expression()->create()->id => ['position' => 1],
            LexiconItem::factory()->expression()->create()->id => ['position' => 2],
            LexiconItem::factory()->expression()->create()->id => ['position' => 3],
        ]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $complete]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.type', 'complete')
                ->has('block.summary.rows', 6)
                ->where('block.summary.rows.0.type', 'situation')
                ->where('block.summary.rows.1.title', 'Key Vocabulary')
                ->where('block.summary.rows.1.subtitle', '2 words')
                ->where('block.summary.rows.1.done', true)
                ->where('block.summary.rows.2.title', 'Useful Expressions')
                ->where('block.summary.rows.2.subtitle', '3 expressions')
                ->where('block.summary.rows.3.subtitle', 'Completed')
            );
    }

    public function test_completing_the_finish_step_records_the_lesson_completion(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $blocks = $lesson->visibleBlocks()->get();
        $first = $blocks->firstOrFail();
        /** @var Block $complete */
        $complete = $blocks->firstWhere('type', BlockType::Complete);

        $this->actingAs($learner)->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $first]));
        $this->actingAs($learner)->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $complete]));

        $this->assertDatabaseHas('lesson_completions', [
            'user_id' => $learner->id,
            'lesson_id' => $lesson->id,
        ]);
    }
}
