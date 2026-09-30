<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\ContentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Create course / unit / lesson, autosave, publish, duplicate, archive, each
 * with its audit row (CMS-01, CMS-05, LESSON-02, BLD-07, BLD-08, SEC-06).
 */
class LessonWritesTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_creating_a_course_lands_as_a_draft_with_a_slug_and_an_audit_row()
    {
        $this->actingAs($this->owner)
            ->post(route('courses.store'), [
                'title' => 'Restaurant Communication',
                'department_id' => $this->department->id,
                'hotel_id' => null,
                'tone' => 'aqua',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $course = Course::query()->where('title', 'Restaurant Communication')->firstOrFail();

        $this->assertSame('restaurant-communication', $course->slug);
        $this->assertSame(ContentStatus::Draft, $course->status);
        $this->assertNull($course->hotel_id);
        $this->assertSame($this->owner->id, $course->created_by);
        $this->assertSame(1, AuditLog::query()->where('action', 'course.created')->count());
    }

    public function test_course_validation_rejects_a_missing_title_and_an_unknown_tone()
    {
        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->post(route('courses.store'), ['department_id' => $this->department->id, 'tone' => 'purple'])
            ->assertRedirect(route('lessons-content'))
            ->assertSessionHasErrors(['title', 'tone']);
    }

    public function test_creating_a_unit_appends_it_to_the_course()
    {
        $this->actingAs($this->owner)
            ->post(route('units.store'), ['course_id' => $this->course->id, 'title' => 'Check-in Process'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unit = Unit::query()->where('title', 'Check-in Process')->firstOrFail();

        $this->assertSame(2, $unit->position);
        $this->assertSame(1, AuditLog::query()->where('action', 'unit.created')->count());
    }

    public function test_creating_a_lesson_gives_it_the_default_block_set_as_a_draft()
    {
        $this->actingAs($this->owner)
            ->post(route('lessons.store'), ['unit_id' => $this->unit->id, 'title' => 'Introducing Yourself'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $lesson = Lesson::query()->where('title', 'Introducing Yourself')->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertSame($this->course->id, $lesson->course_id);
        $this->assertSame(2, $lesson->position);
        $this->assertSame(9, $lesson->blocks()->count());
        $this->assertSame('situation', $lesson->blocks()->first()?->type->value);

        $this->actingAs($this->owner)
            ->post(route('lessons.store'), ['unit_id' => $this->unit->id, 'title' => 'Blank', 'blank' => true]);

        $this->assertSame(0, Lesson::query()->where('title', 'Blank')->firstOrFail()->blocks()->count());
    }

    public function test_a_lesson_needs_no_course_or_unit_and_lands_in_a_general_one()
    {
        // Client request 2026-09-29: the course and unit are optional.
        $empty = Department::factory()->create(['hotel_id' => null, 'is_active' => true]);

        foreach (['First lesson', 'Second lesson'] as $title) {
            $this->actingAs($this->owner)
                ->post(route('lessons.store'), ['department_id' => $empty->id, 'title' => $title])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $courses = Course::query()->where('department_id', $empty->id)->get();
        $this->assertCount(1, $courses, 'The General course is created once and reused.');
        $this->assertSame('General', $courses->first()?->title);
        $this->assertNull($courses->first()?->hotel_id);
        $this->assertSame(1, Unit::query()->where('course_id', $courses->first()?->id)->count());
        $this->assertSame(2, Lesson::query()->where('course_id', $courses->first()?->id)->count());

        // A course without a unit choice uses the course's first unit.
        $this->actingAs($this->owner)
            ->post(route('lessons.store'), ['course_id' => $this->course->id, 'title' => 'In the course'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->unit->id, Lesson::query()->where('title', 'In the course')->firstOrFail()->unit_id);

        // Nothing at all is still refused.
        $this->actingAs($this->owner)
            ->post(route('lessons.store'), ['title' => 'Nowhere'])
            ->assertSessionHasErrors('department_id');
    }

    public function test_a_new_course_and_unit_can_be_typed_when_creating_a_lesson()
    {
        // Client request 2026-09-29: not only "General" or an existing one.
        $this->actingAs($this->owner)
            ->post(route('lessons.store'), [
                'department_id' => $this->department->id,
                'new_course_title' => 'Front Office Basics',
                'new_unit_title' => 'Phone Calls',
                'title' => 'Taking a booking',
            ])
            ->assertSessionHasNoErrors();

        $lesson = Lesson::query()->where('title', 'Taking a booking')->firstOrFail();
        $course = Course::query()->where('title', 'Front Office Basics')->firstOrFail();
        $this->assertSame($course->id, $lesson->course_id);
        $this->assertSame($this->department->id, $course->department_id);
        $this->assertSame('Phone Calls', Unit::query()->findOrFail($lesson->unit_id)->title);

        // A new unit in an existing course.
        $this->actingAs($this->owner)
            ->post(route('lessons.store'), [
                'course_id' => $this->course->id,
                'new_unit_title' => 'Complaints',
                'title' => 'Handling a complaint',
            ])
            ->assertSessionHasNoErrors();

        $unit = Unit::query()->findOrFail(Lesson::query()->where('title', 'Handling a complaint')->firstOrFail()->unit_id);
        $this->assertSame('Complaints', $unit->title);
        $this->assertSame($this->course->id, $unit->course_id);
    }

    public function test_the_editor_autosaves_one_field_at_a_time()
    {
        $this->actingAs($this->owner)
            ->patch(route('lessons.update', $this->lesson), ['title' => 'Greeting Guests Warmly'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->patch(route('lessons.update', $this->lesson), ['objectives' => ['Smile', 'Say hello']])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->patch(route('lessons.update', $this->lesson), [
                'estimated_minutes' => 12,
                'completion_condition' => ['rule' => 'all_steps', 'min_score' => null],
            ])
            ->assertSessionHasNoErrors();

        $lesson = $this->lesson->fresh();

        $this->assertSame('Greeting Guests Warmly', $lesson?->title);
        $this->assertSame(['Smile', 'Say hello'], $lesson?->objectives);
        $this->assertSame(12, $lesson?->estimated_minutes);
        $this->assertSame('all_steps', $lesson?->completion_condition['rule'] ?? null);
        $this->assertSame(3, AuditLog::query()->where('action', 'lesson.updated')->count());
    }

    public function test_the_editor_rejects_an_overlong_title_and_a_bad_status()
    {
        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->patch(route('lessons.update', $this->lesson), ['title' => str_repeat('x', 101), 'status' => 'archived'])
            ->assertSessionHasErrors(['title', 'status']);
    }

    public function test_publish_flips_the_status_and_publishing_again_returns_to_draft()
    {
        $this->actingAs($this->owner)
            ->post(route('lessons.publish', $this->lesson))
            ->assertRedirect();

        $this->assertSame(ContentStatus::Published, $this->lesson->fresh()?->status);
        $this->assertNotNull($this->lesson->fresh()?->published_at);

        $this->actingAs($this->owner)->post(route('lessons.publish', $this->lesson));

        $this->assertSame(ContentStatus::Draft, $this->lesson->fresh()?->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'lesson.published')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'lesson.unpublished')->count());
    }

    public function test_duplicate_copies_the_lesson_and_its_blocks_as_a_draft()
    {
        $this->actingAs($this->owner)
            ->post(route('lessons.duplicate', $this->lesson))
            ->assertRedirect();

        $copy = Lesson::query()->where('title', 'Greeting Guests (copy)')->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $copy->status);
        $this->assertSame('greeting-guests-copy', $copy->slug);
        $this->assertSame(9, $copy->blocks()->count());
        $this->assertSame(2, $copy->position);
    }

    public function test_archive_keeps_every_row()
    {
        $this->actingAs($this->owner)->post(route('lessons.publish', $this->lesson));
        $this->actingAs($this->owner)->post(route('lessons.archive', $this->lesson))->assertRedirect();

        $this->assertSame(ContentStatus::Draft, $this->lesson->fresh()?->status);
        $this->assertSame(9, $this->lesson->blocks()->count());

        $this->actingAs($this->owner)->post(route('courses.archive', $this->course))->assertRedirect();

        $this->assertDatabaseHas('courses', ['id' => $this->course->id, 'status' => 'draft']);
        $this->assertDatabaseHas('lessons', ['id' => $this->lesson->id]);
    }
}
