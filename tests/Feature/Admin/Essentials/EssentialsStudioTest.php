<?php

namespace Tests\Feature\Admin\Essentials;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\CourseTrack;
use App\Models\Block;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Admin\Lessons\BuildsContentFixtures;
use Tests\TestCase;

/**
 * The English Essentials studio (client request 2026-11-05): a screen of
 * its own for the beginner topics, lessons added from beginner templates,
 * flat ordering, and the same authorization as the lesson builder
 * (ROLE-01, SEC-01, CMS-04).
 */
class EssentialsStudioTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    private function topic(array $attributes = []): Course
    {
        $course = Course::factory()->essentials()->create($attributes + ['title' => 'Numbers']);
        Unit::factory()->create(['course_id' => $course->id]);

        return $course;
    }

    public function test_the_studio_lists_topics_and_the_open_topics_lessons_but_no_department_course()
    {
        $topic = $this->topic();
        $unit = $topic->units()->firstOrFail();
        $lesson = Lesson::factory()->withBlocks([BlockType::ListenRepeat, BlockType::Complete])->create(['unit_id' => $unit->id, 'title' => 'Room numbers']);
        Block::query()->where('lesson_id', $this->lesson->id)->first()?->update(['help_lesson_id' => $lesson->id]);

        $this->actingAs($this->owner)
            ->get(route('essentials-studio', ['topic' => $topic->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/EnglishEssentials')
                ->has('topics', 1)
                ->where('topics.0.title', 'Numbers')
                ->where('topics.0.visible', false)
                ->where('openTopic.id', $topic->id)
                ->has('openTopic.lessons', 1)
                ->where('openTopic.lessons.0.title', 'Room numbers')
                ->where('openTopic.lessons.0.helpLinks', 1)
                ->has('openTopic.lessons.0.steps', 2)
                ->has('templates', 5)
                ->where('stats.helpLinks', 1));
    }

    public function test_a_topic_is_created_hidden_with_its_audience_and_a_unit()
    {
        $this->actingAs($this->owner)
            ->post(route('essentials-studio.topics.store'), [
                'title' => 'Letters & Spelling',
                'description' => 'Spell a guest’s name.',
                'icon' => 'letters',
                'tone' => 'aqua',
                'audience_department_ids' => [$this->department->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $topic = Course::query()->where('title', 'Letters & Spelling')->firstOrFail();

        $this->assertSame(CourseTrack::Essentials, $topic->track);
        $this->assertSame(ContentStatus::Draft, $topic->status);
        $this->assertNull($topic->department_id);
        $this->assertSame([$this->department->id], $topic->audienceDepartments()->pluck('departments.id')->all());
        $this->assertSame(1, $topic->units()->count());
    }

    public function test_a_lesson_from_a_template_gets_the_templates_steps_in_order()
    {
        $topic = $this->topic();

        $this->actingAs($this->owner)
            ->post(route('essentials-studio.lessons.store', $topic), [
                'title' => 'Room numbers',
                'template' => 'word_list',
                'open_editor' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirectContains('/lessons/');

        $lesson = Lesson::query()->where('title', 'Room numbers')->firstOrFail();

        $this->assertSame($topic->id, $lesson->course_id);
        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertSame(
            ['vocabulary', 'listen_repeat', 'practice', 'complete'],
            $lesson->blocks()->get()->map(fn (Block $block): string => $block->type->value)->all(),
        );
    }

    public function test_lessons_are_reordered_into_one_flat_list()
    {
        $topic = $this->topic();
        $first = $topic->units()->firstOrFail();
        $second = Unit::factory()->create(['course_id' => $topic->id, 'position' => 2]);
        $a = Lesson::factory()->create(['unit_id' => $first->id, 'position' => 1]);
        $b = Lesson::factory()->create(['unit_id' => $second->id, 'position' => 1]);

        $this->actingAs($this->owner)
            ->put(route('essentials-studio.lessons.reorder', $topic), ['order' => [$b->id, $a->id, $this->lesson->id]])
            ->assertRedirect();

        $this->assertSame([$first->id, 1], [$b->fresh()?->unit_id, $b->fresh()?->position]);
        $this->assertSame([$first->id, 2], [$a->fresh()?->unit_id, $a->fresh()?->position]);
        // A lesson of another course is never moved by this list.
        $this->assertSame($this->unit->id, $this->lesson->fresh()?->unit_id);
    }

    public function test_a_department_course_cannot_take_an_essentials_lesson()
    {
        $this->actingAs($this->owner)
            ->post(route('essentials-studio.lessons.store', $this->course), ['title' => 'Nope', 'template' => 'blank'])
            ->assertForbidden();
    }

    public function test_a_manager_without_lessons_manage_cannot_write()
    {
        $topic = $this->topic();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('essentials-studio.lessons.store', $topic), ['title' => 'Nope', 'template' => 'blank'])
            ->assertForbidden();
        $this->actingAs($manager)
            ->post(route('essentials-studio.topics.store'), ['title' => 'Nope', 'icon' => 'letters'])
            ->assertForbidden();
    }

    public function test_fill_in_steps_opens_the_lessons_own_steps_page_with_a_way_back()
    {
        $topic = $this->topic();
        $lesson = Lesson::factory()->withBlocks([BlockType::Vocabulary, BlockType::ListenRepeat, BlockType::Complete])
            ->create(['unit_id' => $topic->units()->firstOrFail()->id, 'title' => 'Telling the time']);

        $this->actingAs($this->owner)
            ->get(route('essentials-studio', ['topic' => $topic->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('openTopic.lessons.0.editUrl', route('essentials-studio.lessons.steps', $lesson)));

        $this->actingAs($this->owner)
            ->get(route('essentials-studio.lessons.steps', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/EssentialsLessonSteps')
                ->where('topic.id', $topic->id)
                ->where('backUrl', route('essentials-studio', ['topic' => $topic->id]))
                ->where('editor.id', $lesson->id)
                ->has('lessonBlocks', 3)
                ->has('blockTypes'));
    }

    public function test_the_steps_page_refuses_a_department_lesson()
    {
        $this->actingAs($this->owner)
            ->get(route('essentials-studio.lessons.steps', $this->lesson))
            ->assertNotFound();
    }

    public function test_a_hotels_own_topic_is_not_listed_with_the_shared_catalogue()
    {
        $other = Hotel::factory()->create();
        $this->topic(['title' => 'Shared topic']);
        $this->topic(['title' => 'Other hotel topic', 'hotel_id' => $other->id]);

        $this->actingAs($this->owner)
            ->get(route('essentials-studio'))
            ->assertInertia(fn (Assert $page) => $page->has('topics', 1)->where('topics.0.title', 'Shared topic'));
    }
}
