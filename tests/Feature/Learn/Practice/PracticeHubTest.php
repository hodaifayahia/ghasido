<?php

namespace Tests\Feature\Learn\Practice;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The practice hub payload (PRAC-01..03; spec 0003 Part E, photo_7): one card
 * per placement, each with its tone and a small first-item preview.
 */
class PracticeHubTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_hub_ships_a_card_per_activity_with_a_tone_and_a_preview(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $practice = $lesson->visibleBlocks()->firstOrFail();

        $listen = Activity::factory()->ofType(ActivityType::ListenChoose)->create();
        $writing = Activity::factory()->ofType(ActivityType::Writing)->create();
        ActivityPlacement::factory()->in($practice)->at(1)->create(['activity_id' => $listen->id]);
        ActivityPlacement::factory()->in($practice)->at(2)->create(['activity_id' => $writing->id]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $practice]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.type', 'practice')
                ->has('block.activities', 2)
                ->where('block.activities.0.type', 'listen_choose')
                ->where('block.activities.0.tone', 'brand')
                ->has('block.activities.0.preview.images')
                ->where('block.activities.0.done', false)
                ->where('block.activities.1.type', 'writing')
                ->where('block.activities.1.tone', 'aqua')
            );
    }

    /**
     * Super Admins can preview the employee surface from the CMS (CMS-03,
     * ROLE-03), while the learner role remains the only hotel-user role that
     * can enter it.
     */
    public function test_a_super_admin_can_preview_the_practice_hub(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $practice = $lesson->visibleBlocks()->firstOrFail();

        ActivityPlacement::factory()->in($practice)->create([
            'activity_id' => Activity::factory()->ofType(ActivityType::ListenChoose)->create()->id,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $practice]))
            ->assertOk();
    }

    public function test_a_manager_without_a_training_department_is_sent_to_the_department_chooser(): void
    {
        $manager = User::factory()->manager()->create();
        $lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $practice = $lesson->visibleBlocks()->firstOrFail();

        $this->actingAs($manager)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $practice]))
            ->assertRedirect(route('learn.training-department.edit'));
    }
}
