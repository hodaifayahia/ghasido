<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\BlockType;
use App\Enums\CourseTrack;
use App\Models\Block;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * English Essentials in the builder (client request 2026-10-18): topics
 * are courses created from the department list's "English Essentials"
 * entry, with an audience of departments (none = all) and an icon, built
 * with the same lesson editor; a department lesson step may link to one
 * Essentials lesson.
 */
class EnglishEssentialsAdminTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    private function topic(): Course
    {
        $course = Course::factory()->essentials()->create(['title' => 'Numbers']);
        $unit = Unit::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->withBlocks([BlockType::Situation])->create(['unit_id' => $unit->id, 'title' => 'Room numbers']);

        return $course;
    }

    public function test_the_essentials_entry_creates_a_topic_without_a_department()
    {
        $this->actingAs($this->owner)
            ->post(route('courses.store'), [
                'title' => 'Letters & Spelling',
                'department_id' => 'essentials',
                'hotel_id' => null,
                'tone' => 'brand',
                'icon' => 'letters',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $course = Course::query()->where('title', 'Letters & Spelling')->firstOrFail();

        $this->assertSame(CourseTrack::Essentials, $course->track);
        $this->assertNull($course->department_id);
        $this->assertNull($course->level);
        $this->assertSame('letters', $course->icon?->value);
        $this->assertSame(0, $course->audienceDepartments()->count());
    }

    public function test_the_audience_can_be_narrowed_and_reopened_to_every_department()
    {
        $topic = $this->topic();
        $other = Department::factory()->create();

        $this->actingAs($this->owner)
            ->patch(route('courses.update', $topic), ['audience_department_ids' => [$this->department->id, $other->id], 'icon' => 'numbers'])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->department->id, $other->id], $topic->audienceDepartments()->pluck('departments.id')->all());
        $this->assertSame('numbers', $topic->fresh()?->icon?->value);

        $this->actingAs($this->owner)
            ->patch(route('courses.update', $topic), ['audience_department_ids' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $topic->audienceDepartments()->count());
    }

    public function test_the_builder_lists_essentials_topics_under_their_own_entry()
    {
        $topic = $this->topic();

        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['hotel' => 'shared', 'department' => 'essentials']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', 'essentials')
                ->where('filters.courses.0.value', (string) $topic->id)
                ->has('filters.courses', 1)
                ->where('essentials.topic.id', $topic->id)
                ->where('essentials.topic.audience', [])
            );

        // The department's own list never shows the topic.
        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['hotel' => 'shared', 'department' => $this->department->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.courses.0.value', (string) $this->course->id)
                ->has('filters.courses', 1)
                ->where('essentials.topic', null)
            );
    }

    public function test_a_department_step_can_link_to_an_essentials_lesson_only()
    {
        $topic = $this->topic();
        $essentials = $topic->lessons()->firstOrFail();
        $block = Block::factory()->create(['lesson_id' => $this->lesson->id, 'type' => BlockType::Text]);

        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $block), ['help_lesson_id' => $essentials->id, 'help_prompt' => 'Spell your name?'])
            ->assertSessionHasNoErrors();

        $this->assertSame($essentials->id, $block->fresh()?->help_lesson_id);
        $this->assertSame('Spell your name?', $block->fresh()?->help_prompt);

        // A department lesson is not an Essentials lesson.
        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $block), ['help_lesson_id' => $this->lesson->id])
            ->assertSessionHasErrors('help_lesson_id');

        // No help link inside English Essentials itself.
        $inner = $essentials->blocks()->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $inner), ['help_lesson_id' => $essentials->id])
            ->assertSessionHasErrors('help_lesson_id');

        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $block), ['help_lesson_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($block->fresh()?->help_lesson_id);
    }

    public function test_choosing_english_essentials_in_the_builder_keeps_the_builder_open_on_the_topics()
    {
        $topic = $this->topic();

        // Client report 2026-11-05: choosing the entry fell back to the
        // lesson directory, which lists the Reception lessons.
        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['hotel' => 'shared', 'department' => 'essentials', 'builder' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('builderOpen', true)
                ->where('filters.department', 'essentials')
                ->where('courses.0.id', $topic->id)
                ->has('courses', 1));
    }
}
