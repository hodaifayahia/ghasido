<?php

namespace Tests\Feature\Learn;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Enums\EnglishLevel;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\JourneyService;
use App\Services\Platform\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Learner levels (client decision 2026-09-30): the learner chooses
 * Beginner, Intermediate or Advanced; lessons and tests are tagged with a
 * level; a Pre-test at or above the Super Admin's threshold suggests the
 * next level, and the learner moves up or stays.
 */
class LearnerLevelTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    private function preTest(?EnglishLevel $level = null): Test
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'level' => $level,
            'settings' => ['results_visibility' => 'score', 'on_timeout' => 'submit'],
        ]);

        foreach ([1, 2] as $position) {
            $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
            $test->questions()->create(['activity_id' => $activity->id, 'position' => $position]);
        }

        return $test;
    }

    /** Sit the Pre-test answering $right of its two questions right. */
    private function sit(User $learner, Test $test, int $right): void
    {
        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $attempt = TestAttempt::query()->where('user_id', $learner->id)->latest('id')->firstOrFail();

        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => $right >= 1 ? 'A' : 'B'], 'to' => 2,
        ]);
        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 2, 'answer' => ['i1' => $right >= 2 ? 'A' : 'B'],
        ])->assertRedirect();
    }

    public function test_a_learner_sees_only_their_levels_courses_and_every_level_ones()
    {
        $learner = $this->learner(['english_level' => EnglishLevel::Intermediate]);
        $lesson = $this->publishedLesson([BlockType::Situation]);
        $everyLevel = $lesson->course()->firstOrFail();
        $mine = Course::factory()->forDepartment($this->department->id)->published()->create(['level' => EnglishLevel::Intermediate]);
        $other = Course::factory()->forDepartment($this->department->id)->published()->create(['level' => EnglishLevel::Beginner]);

        $visible = Course::query()->forLearner($learner)->pluck('id')->all();

        $this->assertContains($everyLevel->id, $visible);
        $this->assertContains($mine->id, $visible);
        $this->assertNotContains($other->id, $visible);
    }

    public function test_the_learner_sits_their_own_levels_pre_test_first()
    {
        $learner = $this->learner(['english_level' => EnglishLevel::Advanced]);
        $this->preTest();
        $beginner = $this->preTest(EnglishLevel::Beginner);
        $advanced = $this->preTest(EnglishLevel::Advanced);

        $chosen = app(JourneyService::class)->preTest($learner);

        $this->assertSame($advanced->id, $chosen?->id);
        $this->assertFalse(Test::query()->forLearner($learner)->whereKey($beginner->id)->exists());
    }

    public function test_a_strong_pre_test_suggests_the_next_level_and_the_learner_moves_up()
    {
        $learner = $this->learner(['english_level' => EnglishLevel::Beginner]);
        $this->sit($learner, $this->preTest(), 2);

        $learner->refresh();
        $this->assertSame(EnglishLevel::Beginner, $learner->english_level);
        $this->assertSame(EnglishLevel::Intermediate, $learner->level_suggestion);

        $this->actingAs($learner)->post(route('learn.level.answer'), ['decision' => 'move'])->assertRedirect();

        $learner->refresh();
        $this->assertSame(EnglishLevel::Intermediate, $learner->english_level);
        $this->assertNull($learner->level_suggestion);
    }

    public function test_the_learner_may_stay_at_their_level_to_review()
    {
        $learner = $this->learner(['english_level' => EnglishLevel::Intermediate]);
        $this->sit($learner, $this->preTest(), 2);
        $learner->refresh();
        $this->assertSame(EnglishLevel::Advanced, $learner->level_suggestion);

        $this->actingAs($learner)->post(route('learn.level.answer'), ['decision' => 'stay'])->assertSessionHasNoErrors()->assertRedirect();

        $learner->refresh();
        $this->assertSame(EnglishLevel::Intermediate, $learner->english_level);
        $this->assertNull($learner->level_suggestion);
    }

    public function test_below_the_threshold_or_at_advanced_nothing_is_suggested()
    {
        $low = $this->learner(['english_level' => EnglishLevel::Beginner]);
        $this->sit($low, $this->preTest(), 1);
        $this->assertNull($low->fresh()?->level_suggestion);

        $top = $this->learner(['english_level' => EnglishLevel::Advanced, 'username' => 'top']);
        $this->sit($top, $this->preTest(), 2);
        $this->assertNull($top->fresh()?->level_suggestion);
    }

    public function test_the_super_admin_sets_the_threshold()
    {
        $learner = $this->learner(['english_level' => EnglishLevel::Beginner]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($learner)->get(route('learning-settings.edit'))->assertForbidden();
        $this->actingAs($learner)->patch(route('learning-settings.update'), ['level_up_from' => 10])->assertForbidden();

        $this->actingAs($admin)->get(route('learning-settings.edit'))->assertOk();
        $this->actingAs($admin)->patch(route('learning-settings.update'), ['level_up_from' => 101])->assertSessionHasErrors('level_up_from');
        $this->actingAs($admin)->patch(route('learning-settings.update'), ['level_up_from' => 50])->assertSessionHasNoErrors();
        $this->assertSame(50, app(PlatformSettings::class)->levelUpFrom());

        // Half right is now enough to be offered the next level.
        $this->app->forgetScopedInstances();
        $this->sit($learner, $this->preTest(), 1);
        $this->assertSame(EnglishLevel::Intermediate, $learner->fresh()?->level_suggestion);
    }

    public function test_the_learner_can_change_their_level_later()
    {
        $learner = $this->learner(['english_level' => EnglishLevel::Beginner]);

        $this->actingAs($learner)->put(route('learn.level.update'), ['level' => 'nope'])->assertSessionHasErrors('level');
        $this->actingAs($learner)->put(route('learn.level.update'), ['level' => 'advanced'])->assertRedirect();

        $this->assertSame(EnglishLevel::Advanced, $learner->fresh()?->english_level);
    }

    public function test_home_shows_the_level_track_and_the_modules_timeline()
    {
        $this->withoutVite();
        $learner = $this->learner(['english_level' => EnglishLevel::Intermediate]);
        $lesson = $this->publishedLesson([BlockType::Situation]);
        $course = $lesson->course()->firstOrFail();
        Course::factory()->forDepartment($this->department->id)->published()->create(['level' => EnglishLevel::Beginner]);

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('path.level', 'intermediate')
                ->where('path.levels.0.state', 'done')
                ->where('path.levels.1.state', 'current')
                ->where('path.levels.2.state', 'next')
                ->has('path.modules', 1)
                ->where('path.modules.0.id', $course->id)
                ->where('path.modules.0.state', 'current')
                ->where('path.modulesCompleted', 0));
    }
}
