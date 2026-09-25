<?php

namespace Tests\Feature\Learn;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Block;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Walking a lesson step by step (LESSON-01..04, PROG-01, PROG-03, PROG-04,
 * DATA-06, DATA-07, PRAC-04, TEST-06, DATA-01, DATA-11; spec 0003 Part E).
 */
class LessonStepFlowTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_completing_steps_writes_completions_in_order_and_the_lesson_completion_at_the_end()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Text, BlockType::Complete]);
        [$first, $second, $third] = $lesson->visibleBlocks()->get()->all();

        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $first]))
            ->assertRedirect(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $second]));

        $this->assertDatabaseHas('block_completions', ['user_id' => $learner->id, 'lesson_id' => $lesson->id, 'block_id' => $first->id]);
        $this->assertDatabaseCount('lesson_completions', 0);
        $this->assertNotNull($learner->fresh()?->training_started_at);
        $this->assertNotNull($learner->fresh()?->last_activity_at);
        $this->assertNull($learner->fresh()?->training_completed_at);

        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $second]))
            ->assertRedirect(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $third]));

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $third]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('steps.0.done', true)
                ->where('steps.1.done', true)
                ->where('steps.2.done', false)
                ->where('steps.2.current', true)
                ->where('block.type', 'complete')
                ->where('block.summary.lessonsCompleted', 0)
                ->where('block.summary.lessonsTotal', 1)
                ->where('block.summary.nextLesson', null)
            );

        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $third]))
            ->assertRedirect(route('learn.home'));

        $this->assertDatabaseCount('block_completions', 3);
        $this->assertDatabaseHas('lesson_completions', ['user_id' => $learner->id, 'lesson_id' => $lesson->id]);
        $this->assertNotNull($learner->fresh()?->training_completed_at);

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('journey.lessonsCompleted', 1)
                ->where('journey.postTestUnlocked', true)
                ->where('journey.continueUrl', null)
            );
    }

    public function test_completing_a_step_twice_leaves_one_row()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $first = $lesson->visibleBlocks()->firstOrFail();

        $url = route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $first]);

        $this->actingAs($learner)->post($url);
        $this->actingAs($learner)->post($url);

        $this->assertDatabaseCount('block_completions', 1);
    }

    public function test_opening_a_lesson_lands_on_the_first_uncompleted_step()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Text, BlockType::Complete]);
        [$first, $second] = $lesson->visibleBlocks()->get()->all();

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', ['lesson' => $lesson]))
            ->assertRedirect(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $first]));

        $this->actingAs($learner)->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $first]));

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', ['lesson' => $lesson]))
            ->assertRedirect(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $second]));
    }

    public function test_a_hidden_block_and_a_block_of_another_lesson_are_not_steps()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $hidden = Block::factory()->ofType(BlockType::Text)->hidden()->at(9)->create(['lesson_id' => $lesson->id]);
        $other = $this->publishedLesson([BlockType::Situation]);
        $foreignBlock = $other->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $hidden]))
            ->assertNotFound();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $foreignBlock]))
            ->assertNotFound();
    }

    public function test_the_step_page_resolves_media_ids_and_pairs_sentences_with_audio_slots()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation]);
        $image = MediaAsset::factory()->seed('listen-how-can-i-help')->create();

        $block = Block::factory()->ofType(BlockType::ListenRepeat)->at(2)->create([
            'lesson_id' => $lesson->id,
            'settings' => [
                'subtitle' => 'Listen to the sentence, then repeat it.',
                'items' => [['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟', 'image' => $image->id]],
            ],
        ]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.type', 'listen_repeat')
                ->where('block.heading', 'Listen & Repeat')
                ->where('block.settings.items.0.image.id', $image->id)
                ->where('block.settings.items.0.image.url', $image->url())
                ->where('block.settings.items.0.image.alt', $image->alt_text)
                ->where('block.settings.items.0.text', 'How can I help you?')
                ->where('block.settings.items.0.text_audio.normal', null)
                ->where('block.settings.items.0.text_audio.slow', null)
                ->where('block.lexicon', [])
                ->where('block.activities', [])
                ->where('block.scenarios', [])
                ->where('block.summary', null)
            );
    }

    public function test_a_practice_answer_is_recorded_with_the_raw_answer_and_the_version_it_answered()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Practice, BlockType::Complete]);
        $practice = $lesson->visibleBlocks()->get()->firstOrFail(fn (Block $block): bool => $block->type === BlockType::Practice);
        $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
        $placement = ActivityPlacement::factory()->in($practice)->create(['activity_id' => $activity->id]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $practice]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('block.activities', 1)
                ->where('block.activities.0.id', $placement->id)
                ->where('block.activities.0.type', 'multiple_choice')
                ->where('block.activities.0.attemptsUsed', 0)
                ->where('block.activities.0.url', route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $practice, 'placement' => $placement]))
            );

        $activityUrl = route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $practice, 'placement' => $placement]);

        $this->actingAs($learner)
            ->get($activityUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/lesson/Activity')
                ->where('activity.id', $placement->id)
                ->where('activity.mode', 'practice')
                ->where('activity.items.0.correct', 'A')
                ->where('result', null)
                ->where('backUrl', route('learn.lessons.step', ['lesson' => $lesson, 'block' => $practice]))
            );

        $response = $this->actingAs($learner)->post(
            route('learn.lessons.activity.answer', ['lesson' => $lesson, 'block' => $practice, 'placement' => $placement]),
            ['answers' => ['i1' => 'A'], 'started_at' => now()->subSeconds(12)->toIso8601String()],
        );

        $response->assertRedirect($activityUrl);

        $this->assertDatabaseHas('attempts', [
            'user_id' => $learner->id,
            'activity_id' => $activity->id,
            'activity_version_id' => $activity->currentVersion()->firstOrFail()->id,
            'placement_id' => $placement->id,
            'lesson_id' => $lesson->id,
            'block_id' => $practice->id,
            'attempt_no' => 1,
            'is_correct' => true,
            'test_attempt_id' => null,
        ]);

        $attempt = $learner->attempts()->firstOrFail();
        $this->assertSame(['i1' => 'A'], $attempt->raw_answer);
        $this->assertNotNull($attempt->time_taken_ms);
        $this->assertGreaterThanOrEqual(11_000, $attempt->time_taken_ms);

        $this->actingAs($learner)
            ->get($activityUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.isCorrect', true)
                ->where('result.score', 1)
                ->where('result.maxScore', 1)
                ->where('result.perItem.i1', true)
                ->where('result.correct.i1', 'A')
                ->where('activity.attemptsUsed', 1)
            );

        // A second, wrong answer is its own row with the next attempt number.
        $this->actingAs($learner)->post(
            route('learn.lessons.activity.answer', ['lesson' => $lesson, 'block' => $practice, 'placement' => $placement]),
            ['answers' => ['i1' => 'B']],
        );

        $this->assertDatabaseCount('attempts', 2);
        $this->assertDatabaseHas('attempts', ['attempt_no' => 2, 'is_correct' => false]);
    }
}
