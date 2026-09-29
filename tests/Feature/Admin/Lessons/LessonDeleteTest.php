<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Delete" in the Lesson Directory (CMS-01). A lesson nobody touched is
 * deleted with its blocks; a lesson with learner rows only leaves the
 * library and learners, and every answer stays (DATA-10). Authorization is
 * server-side (ROLE-02, SEC-01).
 */
class LessonDeleteTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_a_lesson_without_learner_data_is_deleted_with_its_blocks_and_orphan_activities()
    {
        $second = Lesson::factory()->withBlocks()->create(['unit_id' => $this->unit->id, 'title' => 'Checking In', 'position' => 2]);
        $block = $this->lesson->blocks()->firstOrFail();
        $orphan = Activity::factory()->create();
        ActivityPlacement::factory()->in($block)->create(['activity_id' => $orphan->id]);
        $shared = Activity::factory()->create();
        ActivityPlacement::factory()->in($block)->create(['activity_id' => $shared->id]);
        ActivityPlacement::factory()->in($second->blocks()->firstOrFail())->create(['activity_id' => $shared->id]);

        $this->actingAs($this->owner)
            ->delete(route('lessons.destroy', $this->lesson))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertNull(Lesson::query()->find($this->lesson->id));
        $this->assertSame(0, Block::query()->where('lesson_id', $this->lesson->id)->count());
        $this->assertNull(Activity::query()->find($orphan->id));
        $this->assertNotNull(Activity::query()->find($shared->id));
        $this->assertSame(1, $second->fresh()?->position);
        $this->assertSame(1, AuditLog::query()->where('action', 'lesson.deleted')->count());
    }

    public function test_a_lesson_with_learner_answers_is_removed_from_the_library_but_every_row_is_kept()
    {
        $block = $this->lesson->blocks()->firstOrFail();
        $completion = BlockCompletion::factory()->create(['lesson_id' => $this->lesson->id, 'block_id' => $block->id]);
        $attempt = Attempt::factory()->create(['lesson_id' => $this->lesson->id, 'block_id' => $block->id]);
        $this->lesson->update(['status' => ContentStatus::Published]);
        $blocks = $this->lesson->blocks()->count();

        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lessonDirectory.0.id', $this->lesson->id)
                ->where('lessonDirectory.0.learnerRecords', 2)
                ->where('lessonDirectory.0.canDelete', true)
            );

        $this->actingAs($this->owner)
            ->delete(route('lessons.destroy', $this->lesson))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success');

        $lesson = $this->lesson->fresh();
        $this->assertNotNull($lesson);
        $this->assertNotNull($lesson->archived_at);
        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertSame($blocks, $lesson->blocks()->count());
        $this->assertNotNull(BlockCompletion::query()->find($completion->id));
        $this->assertSame($this->lesson->id, Attempt::query()->findOrFail($attempt->id)->lesson_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'lesson.removed')->count());

        // Gone from the directory and the builder tree, and never published again.
        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('lessonDirectory', 0)
                ->has('courses.0.units.0.lessons', 0)
            );
        $this->actingAs($this->owner)->get(route('lessons.edit', $this->lesson))->assertNotFound();
        $this->assertSame(0, Lesson::query()->published()->whereKey($this->lesson->id)->count());
    }

    public function test_a_manager_without_the_manage_capability_is_refused()
    {
        $this->actingAs($this->manager())
            ->delete(route('lessons.destroy', $this->lesson))
            ->assertForbidden();

        $this->assertNotNull($this->lesson->fresh());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_hotel_admin_may_delete_their_hotels_lesson_but_not_shared_or_another_hotels()
    {
        RoleModel::findByName(Role::Manager->value)->givePermissionTo(Permission::LessonsManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manager = $this->manager();

        $theirs = Hotel::factory()->create();
        $theirLesson = $this->lessonFor($theirs);
        $ownLesson = $this->lessonFor($this->hotel);

        $this->actingAs($manager)->delete(route('lessons.destroy', $theirLesson))->assertForbidden();
        $this->actingAs($manager)->delete(route('lessons.destroy', $this->lesson))->assertForbidden();
        $this->assertNotNull($theirLesson->fresh());
        $this->assertNotNull($this->lesson->fresh());

        $this->actingAs($manager)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lessonDirectory', fn ($rows) => collect($rows)->every(
                    fn (array $row): bool => $row['canDelete'] === ($row['id'] === $ownLesson->id),
                ))
            );

        $this->actingAs($manager)->delete(route('lessons.destroy', $ownLesson))->assertRedirect();
        $this->assertNull($ownLesson->fresh());
    }

    private function lessonFor(Hotel $hotel): Lesson
    {
        $course = Course::factory()->forDepartment($this->department->id)->forHotel($hotel->id)->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()->withBlocks()->create(['unit_id' => $unit->id]);
    }
}
