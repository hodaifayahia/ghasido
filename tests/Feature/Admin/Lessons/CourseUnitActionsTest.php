<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\BlockCompletion;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Rename, copy and delete a whole course, unit or lesson from the Course
 * Structure tree (client request 2026-10-05, CMS-01). A copy is a draft
 * whose words and activities are its own; a delete never loses a learner
 * answer (DATA-10, DATA-11). Authorization is server-side (SEC-01).
 */
class CourseUnitActionsTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_course_unit_and_lesson_titles_can_be_renamed()
    {
        $this->actingAs($this->owner)->patch(route('courses.update', $this->course), ['title' => 'Front Desk English'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->patch(route('units.update', $this->unit), ['title' => 'First Contact'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->patch(route('lessons.update', $this->lesson), ['title' => 'Saying Hello'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Front Desk English', $this->course->fresh()?->title);
        $this->assertSame('First Contact', $this->unit->fresh()?->title);
        $this->assertSame('Saying Hello', $this->lesson->fresh()?->title);

        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('courses.0.title', 'Front Desk English')
                ->where('courses.0.canDelete', true)
                ->where('courses.0.units.0.name', 'First Contact')
                ->where('courses.0.units.0.lessons.0.name', 'Saying Hello')
            );
    }

    public function test_a_copied_lesson_owns_its_words_and_activities()
    {
        $block = $this->lesson->blocks()->firstOrFail();
        $word = LexiconItem::factory()->create(['english_text' => 'Welcome']);
        $block->lexiconItems()->attach($word->id, ['position' => 1]);
        $activity = Activity::factory()->create(['prompt' => 'Choose the greeting']);
        ActivityPlacement::factory()->in($block)->create(['activity_id' => $activity->id]);

        $this->actingAs($this->owner)->post(route('lessons.duplicate', $this->lesson))->assertRedirect();

        $copy = Lesson::query()->where('title', 'Greeting Guests (copy)')->firstOrFail();
        $copyBlock = $copy->blocks()->firstOrFail();
        $copyWord = $copyBlock->lexiconItems()->firstOrFail();
        $copyActivity = $copyBlock->placements()->firstOrFail()->activity()->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $copy->status);
        $this->assertNotSame($word->id, $copyWord->id);
        $this->assertSame('Welcome', $copyWord->english_text);
        $this->assertNotSame($activity->id, $copyActivity->id);
        $this->assertSame('Choose the greeting', $copyActivity->prompt);
        $this->assertSame(1, $copyActivity->versions()->count());

        // Editing the copy leaves the original alone.
        $copyWord->update(['english_text' => 'Good evening']);
        $this->assertSame('Welcome', $word->fresh()?->english_text);
    }

    public function test_a_unit_is_copied_with_its_lessons_as_drafts()
    {
        Lesson::factory()->withBlocks()->create(['unit_id' => $this->unit->id, 'title' => 'Checking In', 'position' => 2]);

        $this->actingAs($this->owner)->post(route('units.duplicate', $this->unit))->assertRedirect();

        $copy = Unit::query()->where('title', 'Welcoming Guests (copy)')->firstOrFail();

        $this->assertSame($this->course->id, $copy->course_id);
        $this->assertSame(2, $copy->position);
        $this->assertSame(['Greeting Guests', 'Checking In'], $copy->lessons()->pluck('title')->all());
        $this->assertSame(9, $copy->lessons()->firstOrFail()->blocks()->count());
        $this->assertSame(0, $copy->lessons()->where('status', ContentStatus::Published->value)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'unit.duplicated')->count());
    }

    public function test_a_course_is_copied_with_its_units_and_lessons_as_a_draft()
    {
        $this->actingAs($this->owner)->post(route('courses.duplicate', $this->course))->assertRedirect();

        $copy = Course::query()->where('title', 'Guest Service Basics (copy)')->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $copy->status);
        $this->assertNotSame($this->course->slug, $copy->slug);
        $this->assertSame($this->department->id, $copy->department_id);
        $this->assertSame(['Welcoming Guests'], $copy->units()->pluck('title')->all());
        $this->assertSame(['Greeting Guests'], $copy->lessons()->pluck('title')->all());
        $this->assertSame($copy->id, $copy->lessons()->firstOrFail()->course_id);
    }

    public function test_deleting_an_untouched_unit_deletes_it_with_its_lessons()
    {
        $second = Unit::factory()->create(['course_id' => $this->course->id, 'title' => 'Farewells', 'position' => 2]);
        $next = Lesson::factory()->create(['unit_id' => $second->id, 'title' => 'Saying Goodbye']);

        // The builder stays open on the lesson that is left.
        $this->actingAs($this->owner)
            ->delete(route('units.destroy', $this->unit))
            ->assertRedirect(route('lessons-content', [
                'hotel' => 'shared',
                'department' => $this->department->id,
                'course' => $this->course->id,
                'unit' => $second->id,
                'lesson' => $next->id,
            ]))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertNull($this->unit->fresh());
        $this->assertNull($this->lesson->fresh());
        $this->assertSame(1, $second->fresh()?->position);
        $this->assertSame(1, AuditLog::query()->where('action', 'unit.deleted')->count());
    }

    public function test_deleting_a_unit_with_learner_answers_removes_it_and_keeps_every_answer()
    {
        $block = $this->lesson->blocks()->firstOrFail();
        $completion = BlockCompletion::factory()->create(['lesson_id' => $this->lesson->id, 'block_id' => $block->id]);
        $attempt = Attempt::factory()->create(['lesson_id' => $this->lesson->id, 'block_id' => $block->id]);

        $this->actingAs($this->owner)->delete(route('units.destroy', $this->unit))->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertNotNull($unit?->archived_at);
        $this->assertSame(ContentStatus::Draft, $unit->status);
        $this->assertNotNull($this->lesson->fresh()?->archived_at);
        $this->assertNotNull($completion->fresh());
        $this->assertNotNull($attempt->fresh());

        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page->has('courses.0.units', 0));
    }

    public function test_deleting_a_lesson_from_the_tree_opens_the_next_lesson_of_its_unit()
    {
        $next = Lesson::factory()->create(['unit_id' => $this->unit->id, 'title' => 'Checking In', 'position' => 2]);

        $this->actingAs($this->owner)
            ->delete(route('lessons.destroy', ['lesson' => $this->lesson, 'from_tree' => 1]))
            ->assertRedirect(route('lessons-content', [
                'hotel' => 'shared',
                'department' => $this->department->id,
                'course' => $this->course->id,
                'unit' => $this->unit->id,
                'lesson' => $next->id,
            ]));

        $this->assertNull($this->lesson->fresh());
    }

    public function test_deleting_an_untouched_course_deletes_everything_in_it()
    {
        $this->actingAs($this->owner)->delete(route('courses.destroy', $this->course))->assertRedirect();

        $this->assertNull($this->course->fresh());
        $this->assertNull($this->unit->fresh());
        $this->assertNull($this->lesson->fresh());
        $this->assertSame(1, AuditLog::query()->where('action', 'course.deleted')->count());
    }

    public function test_deleting_a_course_with_a_certificate_removes_it_and_keeps_the_certificate()
    {
        $certificate = Certificate::factory()->create(['course_id' => $this->course->id]);

        $this->actingAs($this->owner)->delete(route('courses.destroy', $this->course))->assertRedirect();

        $course = $this->course->fresh();
        $this->assertNotNull($course?->archived_at);
        $this->assertSame(0, Course::query()->published()->whereKey($this->course->id)->count());
        $this->assertNotNull($certificate->fresh());

        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page->has('courses', 0));
    }

    public function test_a_hotel_admin_may_not_delete_the_shared_catalogue()
    {
        RoleModel::findByName(Role::Manager->value)->givePermissionTo(Permission::LessonsManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manager = $this->manager();

        $this->actingAs($manager)->delete(route('courses.destroy', $this->course))->assertForbidden();
        $this->actingAs($manager)->delete(route('units.destroy', $this->unit))->assertForbidden();
        $this->assertNotNull($this->course->fresh());
        $this->assertNotNull($this->unit->fresh());

        // A copy of the shared course becomes their hotel's own.
        $this->actingAs($manager)->post(route('courses.duplicate', $this->course))->assertRedirect();
        $this->assertSame($this->hotel->id, Course::query()->where('title', 'Guest Service Basics (copy)')->value('hotel_id'));
    }

    public function test_a_manager_without_the_manage_capability_cannot_copy_or_delete()
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('courses.duplicate', $this->course))->assertForbidden();
        $this->actingAs($manager)->post(route('units.duplicate', $this->unit))->assertForbidden();
        $this->actingAs($manager)->delete(route('courses.destroy', $this->course))->assertForbidden();
        $this->actingAs($manager)->delete(route('units.destroy', $this->unit))->assertForbidden();

        $this->assertSame(1, Course::query()->count());
    }
}
