<?php

namespace Tests\Feature\Learn\Practice;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The pair-map practice activity (PRAC-01, PRAC-04, DATA-01; spec 0003 B.9,
 * photo_11): its page exposes prompts and targets, and its complete raw map
 * is persisted and scored as one attempt.
 */
class ListenMatchTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_page_presents_a_pair_map_and_records_every_match(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $practice = $lesson->visibleBlocks()->firstOrFail();
        $activity = Activity::factory()->ofType(ActivityType::ListenMatch)->create();
        $placement = ActivityPlacement::factory()->in($practice)->create([
            'activity_id' => $activity->id,
        ]);
        $activityUrl = route('learn.lessons.activity', [
            'lesson' => $lesson,
            'block' => $practice,
            'placement' => $placement,
        ]);

        $this->actingAs($learner)
            ->get($activityUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/lesson/Activity')
                ->where('activity.type', 'listen_match')
                ->where('activity.items.0.prompts.0.id', '1')
                ->where('activity.items.0.targets.0.id', 'a')
                ->where('activity.items.0.pairs.1', 'a')
                ->where('result', null)
            );

        $answer = ['i1' => ['1' => 'a', '2' => 'b', '3' => 'c']];

        $this->actingAs($learner)
            ->post(route('learn.lessons.activity.answer', [
                'lesson' => $lesson,
                'block' => $practice,
                'placement' => $placement,
            ]), [
                'answers' => $answer,
                'started_at' => now()->subSeconds(8)->toIso8601String(),
            ])
            ->assertRedirect($activityUrl);

        $attempt = $learner->attempts()->firstOrFail();

        $this->assertSame($answer, $attempt->raw_answer);
        $this->assertTrue($attempt->is_correct);
        $this->assertSame('1.00', $attempt->score);
        $this->assertSame('1.00', $attempt->max_score);
        $this->assertNotNull($attempt->time_taken_ms);

        $this->actingAs($learner)
            ->get($activityUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.isCorrect', true)
                ->where('result.perItem.i1', true)
                ->where('result.correct.i1.1', 'a')
                ->where('activity.attemptsUsed', 1)
            );
    }
}
