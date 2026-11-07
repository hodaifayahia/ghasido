<?php

namespace Tests\Feature\Admin\Lessons;

use App\Models\BlockCompletion;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Moving lessons in Course Structure (client request 2026-11-07): up and
 * down inside a unit, into another unit or another course; the lesson
 * keeps its steps and learner records (DATA-10); the server checks both
 * courses (SEC-01).
 */
class MoveLessonTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_a_lesson_moves_up_inside_its_unit()
    {
        $second = Lesson::factory()->create(['unit_id' => $this->unit->id, 'position' => 2, 'title' => 'Checking In']);

        $this->actingAs($this->owner)
            ->patch(route('lessons.move', $second), ['unit_id' => $this->unit->id, 'position' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $second->fresh()?->position);
        $this->assertSame(2, $this->lesson->fresh()?->position);
    }

    public function test_a_lesson_moves_to_another_course_with_its_steps_and_records()
    {
        $other = Course::factory()->forDepartment($this->department->id)->create(['title' => 'Phone English']);
        $target = Unit::factory()->create(['course_id' => $other->id]);
        Lesson::factory()->create(['unit_id' => $target->id, 'position' => 1]);
        $steps = $this->lesson->blocks()->count();
        $block = $this->lesson->blocks()->firstOrFail();
        BlockCompletion::factory()->create(['lesson_id' => $this->lesson->id, 'block_id' => $block->id]);

        $this->actingAs($this->owner)
            ->patch(route('lessons.move', $this->lesson), ['unit_id' => $target->id])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success');

        $lesson = $this->lesson->fresh();
        $this->assertNotNull($lesson);
        $this->assertSame($target->id, $lesson->unit_id);
        $this->assertSame($other->id, $lesson->course_id);
        $this->assertSame(2, $lesson->position);
        $this->assertSame($steps, $lesson->blocks()->count());
        $this->assertSame(1, BlockCompletion::query()->where('lesson_id', $lesson->id)->count());
    }

    public function test_the_move_targets_list_the_editable_courses_with_their_units()
    {
        $this->actingAs($this->owner)
            ->getJson(route('lessons.move-targets', $this->lesson))
            ->assertOk()
            ->assertJsonPath('currentUnitId', $this->unit->id)
            ->assertJsonPath('courses.0.units.0.id', $this->unit->id);
    }

    public function test_a_hotel_admin_cannot_move_a_lesson_into_another_hotels_course()
    {
        $manager = $this->manager();
        $other = Course::factory()->forDepartment($this->department->id)->forHotel(Hotel::factory()->create()->id)->create();
        $target = Unit::factory()->create(['course_id' => $other->id]);

        $this->actingAs($manager)
            ->patch(route('lessons.move', $this->lesson), ['unit_id' => $target->id])
            ->assertForbidden();

        $this->assertSame($this->unit->id, $this->lesson->fresh()?->unit_id);
    }
}
