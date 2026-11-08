<?php

namespace Tests\Feature\Security;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): a lesson whose completion rule is
 * "practice passed with a minimum score" is not completed by clicking
 * through its steps; every auto-scored practice activity must reach the
 * score first (JOURNEY-04, PROG-03).
 */
class LearnerIntegrityLessonCompletionTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @param  array<string, mixed>|null  $condition
     * @return array{0: Lesson, 1: ActivityPlacement}
     */
    private function lessonWithPractice(?array $condition): array
    {
        $lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $lesson->update(['completion_condition' => $condition]);

        $practice = $lesson->visibleBlocks()->firstOrFail();
        $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
        $placement = ActivityPlacement::factory()->in($practice)->create(['activity_id' => $activity->id]);

        return [$lesson, $placement];
    }

    private function completeEveryStep(User $learner, Lesson $lesson): void
    {
        foreach ($lesson->visibleBlocks()->get() as $block) {
            /** @var Block $block */
            $this->actingAs($learner)
                ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $block]))
                ->assertRedirect();
        }
    }

    private function practise(User $learner, Lesson $lesson, ActivityPlacement $placement, bool $correct): void
    {
        $factory = Attempt::factory();

        ($correct ? $factory : $factory->wrong())->create([
            'user_id' => $learner->id,
            'activity_id' => $placement->activity_id,
            'placement_id' => $placement->id,
            'lesson_id' => $lesson->id,
            'block_id' => $placement->placeable_id,
        ]);
    }

    private function completed(User $learner, Lesson $lesson): bool
    {
        return LessonCompletion::query()->where('user_id', $learner->id)->where('lesson_id', $lesson->id)->exists();
    }

    public function test_clicking_through_the_steps_does_not_complete_a_practice_passed_lesson()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        [$lesson, $placement] = $this->lessonWithPractice(['rule' => 'practice_passed', 'min_score' => 80]);

        $this->completeEveryStep($learner, $lesson);
        $this->assertFalse($this->completed($learner, $lesson));

        $this->practise($learner, $lesson, $placement, correct: false);
        $this->completeEveryStep($learner, $lesson);
        $this->assertFalse($this->completed($learner, $lesson));
    }

    public function test_a_passed_practice_completes_the_lesson_on_the_next_step()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        [$lesson, $placement] = $this->lessonWithPractice(['rule' => 'practice_passed', 'min_score' => 80]);

        $this->practise($learner, $lesson, $placement, correct: false);
        $this->practise($learner, $lesson, $placement, correct: true);
        $this->completeEveryStep($learner, $lesson);

        $this->assertTrue($this->completed($learner, $lesson));
    }

    public function test_the_all_steps_rule_still_completes_on_the_last_step()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        [$lesson] = $this->lessonWithPractice(['rule' => 'all_steps', 'min_score' => null]);

        $this->completeEveryStep($learner, $lesson);

        $this->assertTrue($this->completed($learner, $lesson));
    }
}
