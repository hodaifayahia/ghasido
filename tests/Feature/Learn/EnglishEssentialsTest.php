<?php

namespace Tests\Feature\Learn;

use App\Enums\BlockType;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * English Essentials (client request 2026-10-18): beginner topics beside
 * the department training, for every department or chosen ones, opened
 * from My Lessons or from a lesson step's help link, with the way back to
 * that step. Their progress is saved (PROG-01) but never counts toward the
 * journey, the Post-test or the certificate (JOURNEY-04, JOURNEY-05).
 */
class EnglishEssentialsTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @param  list<BlockType>|null  $blocks
     * @param  list<Department>  $audience
     */
    private function essentialsLesson(?array $blocks = null, array $audience = [], ?Hotel $hotel = null): Lesson
    {
        $course = Course::factory()
            ->essentials()
            ->when($hotel !== null, fn ($factory) => $factory->forHotel($hotel?->id ?? 0))
            ->published()
            ->create(['title' => 'Letters & Spelling']);

        $course->audienceDepartments()->sync(array_map(fn (Department $department): int => $department->id, $audience));

        $unit = Unit::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()
            ->published()
            ->withBlocks($blocks ?? [BlockType::Situation, BlockType::Text, BlockType::Complete])
            ->create(['unit_id' => $unit->id]);
    }

    public function test_my_lessons_shows_the_essentials_card_beside_the_department_training()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $this->publishedLesson();
        $this->essentialsLesson();

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Lessons')
                ->has('courses', 1)
                ->where('essentials.topics', 1)
                ->where('essentials.lessonsTotal', 1)
                ->where('essentials.lessonsCompleted', 0)
                ->where('essentials.url', route('learn.essentials.index'))
            );

        $this->actingAs($learner)
            ->get(route('learn.essentials.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Essentials')
                ->has('topics', 1)
                ->where('topics.0.title', 'Letters & Spelling')
            );
    }

    public function test_the_card_is_hidden_when_nothing_is_published()
    {
        $learner = $this->learner();
        $this->publishedLesson();

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertInertia(fn (Assert $page) => $page->where('essentials', null));
    }

    public function test_a_topic_for_chosen_departments_reaches_only_them()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $other = Department::factory()->create();
        $lesson = $this->essentialsLesson(audience: [$other]);

        $this->actingAs($learner)
            ->get(route('learn.essentials.index'))
            ->assertInertia(fn (Assert $page) => $page->has('topics', 0));

        $this->actingAs($learner)
            ->get(route('learn.essentials.show', ['course' => $lesson->course_id]))
            ->assertForbidden();

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', $lesson))
            ->assertForbidden();

        $lesson->course?->audienceDepartments()->attach($this->department->id);

        $this->actingAs($learner)
            ->get(route('learn.essentials.show', ['course' => $lesson->course_id]))
            ->assertOk();
    }

    public function test_another_hotels_topic_is_forbidden_by_direct_url()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->essentialsLesson(hotel: Hotel::factory()->create());
        $block = $lesson->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->get(route('learn.essentials.show', ['course' => $lesson->course_id]))
            ->assertForbidden();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertForbidden();

        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $block]))
            ->assertForbidden();
    }

    public function test_essentials_lessons_keep_the_pre_test_gate()
    {
        $learner = $this->learner();
        $this->publishedPreTest();
        $lesson = $this->essentialsLesson();

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', $lesson))
            ->assertForbidden();
    }

    public function test_essentials_progress_is_saved_but_never_counts_toward_the_journey()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $lesson = $this->essentialsLesson([BlockType::Situation, BlockType::Complete]);

        foreach ($lesson->visibleBlocks()->get() as $block) {
            $this->actingAs($learner)
                ->post(route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $block]));
        }

        $this->assertDatabaseHas('lesson_completions', ['user_id' => $learner->id, 'lesson_id' => $lesson->id]);
        $this->assertNull($learner->fresh()?->training_completed_at);

        $this->actingAs($learner)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('journey.lessonsTotal', 1)
                ->where('journey.lessonsCompleted', 0)
                ->where('journey.postTestUnlocked', false)
            );

        $this->actingAs($learner)
            ->get(route('learn.essentials.index'))
            ->assertInertia(fn (Assert $page) => $page->where('topics.0.lessonsCompleted', 1));
    }

    public function test_the_help_link_opens_the_essentials_lesson_and_leads_back_to_the_same_step()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Text, BlockType::Complete]);
        $step = $lesson->visibleBlocks()->get()[1];
        $essentials = $this->essentialsLesson([BlockType::Situation, BlockType::Text]);
        [$first, $last] = $essentials->visibleBlocks()->get()->all();

        $step->forceFill(['help_lesson_id' => $essentials->id, 'help_prompt' => 'Want to learn spelling first?'])->save();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $step]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('help.prompt', 'Want to learn spelling first?')
                ->where('help.url', route('learn.lessons.step.essentials', ['lesson' => $lesson, 'block' => $step]))
                ->where('returnTo', null)
            );

        $this->actingAs($learner)
            ->get(route('learn.lessons.step.essentials', ['lesson' => $lesson, 'block' => $step]))
            ->assertRedirect(route('learn.lessons.step', ['lesson' => $essentials, 'block' => $first]));

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $essentials, 'block' => $first]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.essentials', true)
                ->where('help', null)
                ->where('returnTo.title', $lesson->title)
                ->where('returnTo.url', route('learn.essentials.return'))
            );

        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $essentials, 'block' => $first]));

        // The last step leads back through the route that ends the trip...
        $this->actingAs($learner)
            ->post(route('learn.lessons.step.complete', ['lesson' => $essentials, 'block' => $last]))
            ->assertRedirect(route('learn.essentials.return'));

        // ...to the very step the learner left.
        $this->actingAs($learner)
            ->get(route('learn.essentials.return'))
            ->assertRedirect(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $step]));

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $essentials, 'block' => $first]))
            ->assertInertia(fn (Assert $page) => $page->where('returnTo', null));
    }

    public function test_opening_an_essentials_lesson_from_my_lessons_forgets_an_old_trip()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $step = $lesson->visibleBlocks()->firstOrFail();
        $essentials = $this->essentialsLesson();

        $step->forceFill(['help_lesson_id' => $essentials->id])->save();

        $this->actingAs($learner)->get(route('learn.lessons.step.essentials', ['lesson' => $lesson, 'block' => $step]));
        $this->actingAs($learner)->get(route('learn.lessons.show', $essentials));

        $first = $essentials->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $essentials, 'block' => $first]))
            ->assertInertia(fn (Assert $page) => $page->where('returnTo', null));
    }

    public function test_a_help_link_to_an_essentials_lesson_out_of_reach_is_not_offered()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Complete]);
        $step = $lesson->visibleBlocks()->firstOrFail();
        $essentials = $this->essentialsLesson(audience: [Department::factory()->create()]);

        $step->forceFill(['help_lesson_id' => $essentials->id])->save();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $step]))
            ->assertInertia(fn (Assert $page) => $page->where('help', null));

        $this->actingAs($learner)
            ->get(route('learn.lessons.step.essentials', ['lesson' => $lesson, 'block' => $step]))
            ->assertForbidden();
    }
}
