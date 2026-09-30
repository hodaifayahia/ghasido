<?php

namespace Tests\Feature\Learn\Practice;

use App\Enums\BlockType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * End to end, client report 2026-09-29: a question the admin writes into a
 * lesson's Quiz or Practice block reaches the learner's step, and the
 * learner's actual answer is stored against the question's version
 * (PRAC-01, PRAC-04, TEST-06, DATA-01, DATA-11).
 */
class QuizBlockTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @return array<string, mixed>
     */
    private function question(int $blockId): array
    {
        return [
            'type' => 'multiple_choice',
            'title' => 'Question 1',
            'prompt' => 'Choose the best answer.',
            'attempts_allowed' => 1,
            'block_id' => $blockId,
            'payload' => ['items' => [[
                'id' => 'i1',
                'question' => 'A guest arrives with a large suitcase. What do you say?',
                'image' => null,
                'layout' => 'side',
                'options' => [
                    ['id' => 'A', 'text' => 'Good evening! Can I help you with your luggage?'],
                    ['id' => 'B', 'text' => 'Please wait outside.'],
                ],
                'correct' => 'A',
            ]]],
        ];
    }

    public function test_a_question_written_in_a_quiz_or_practice_block_is_rendered_and_answered_by_the_learner(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $learner = $this->learner();
        $this->submitPreTest($learner);

        foreach ([BlockType::Quiz, BlockType::Practice] as $type) {
            $lesson = $this->publishedLesson([$type, BlockType::Complete]);
            $block = $lesson->visibleBlocks()->firstOrFail();

            $this->actingAs($admin)
                ->post(route('activities.store'), $this->question($block->id))
                ->assertSessionHasNoErrors();

            $placement = $block->placements()->firstOrFail();

            $this->actingAs($learner)
                ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('employee/lesson/Step')
                    ->where('block.type', $type->value)
                    ->where('block.activities.0.id', $placement->id)
                    ->where('block.activities.0.type', 'multiple_choice')
                    ->where('block.activities.0.label', 'Question 1')
                );

            $activityUrl = route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]);

            $this->actingAs($learner)
                ->get($activityUrl)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('employee/lesson/Activity')
                    ->where('activity.type', 'multiple_choice')
                    ->where('activity.items.0.question', 'A guest arrives with a large suitcase. What do you say?')
                    ->where('activity.items.0.options.1.text', 'Please wait outside.')
                );

            $this->actingAs($learner)
                ->post(route('learn.lessons.activity.answer', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]), [
                    'answers' => ['i1' => 'B'],
                    'started_at' => now()->subSeconds(5)->toIso8601String(),
                ])
                ->assertRedirect($activityUrl);

            $attempt = $learner->attempts()->latest('id')->firstOrFail();

            $this->assertSame(['i1' => 'B'], $attempt->raw_answer);
            $this->assertFalse($attempt->is_correct);
            $this->assertSame($placement->activity()->firstOrFail()->currentVersion()->firstOrFail()->id, $attempt->activity_version_id);
        }
    }
}
