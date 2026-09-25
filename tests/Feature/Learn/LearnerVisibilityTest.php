<?php

namespace Tests\Feature\Learn;

use App\Enums\ContentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use App\Models\User;
use App\Services\Learning\JourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Publish → learner visibility (ORG-04, CMS-04, CMS-05, JOURNEY-03, ROLE-02;
 * spec 0004): publishing a lesson makes its draft unit and course visible
 * too, the lesson reaches the employees of its department only, a hotel's
 * own lesson stays inside that hotel, and a draft never shows.
 */
class LearnerVisibilityTest extends TestCase
{
    use BuildsLearnerFixtures;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * A draft course, a draft unit and a draft lesson with blocks — what the
     * AI generator (or a new admin course) leaves behind.
     */
    private function draftLesson(?Department $department = null, ?Hotel $hotel = null): Lesson
    {
        $course = Course::factory()
            ->forDepartment(($department ?? $this->department)->id)
            ->when($hotel !== null, fn ($factory) => $factory->forHotel($hotel?->id ?? 0))
            ->create(['status' => ContentStatus::Draft]);

        $unit = Unit::factory()->draft()->create(['course_id' => $course->id]);

        return Lesson::factory()->withBlocks()->create([
            'unit_id' => $unit->id,
            'status' => ContentStatus::Draft,
        ]);
    }

    /**
     * @return list<int>
     */
    private function visibleLessonIds(User $learner): array
    {
        $ids = [];

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids): void {
                $page->component('employee/Lessons');

                foreach ($page->toArray()['props']['courses'] as $course) {
                    foreach ($course['units'] as $unit) {
                        foreach ($unit['lessons'] as $lesson) {
                            $ids[] = $lesson['id'];
                        }
                    }
                }
            });

        return $ids;
    }

    public function test_publishing_a_lesson_publishes_its_draft_unit_and_course()
    {
        $lesson = $this->draftLesson();

        $this->actingAs($this->admin)
            ->post(route('lessons.publish', $lesson))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertSame(ContentStatus::Published, $lesson->status);
        $this->assertSame(ContentStatus::Published, $lesson->unit()->firstOrFail()->status);
        $course = $lesson->course()->firstOrFail();
        $this->assertSame(ContentStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'course.published')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'unit.published')->count());
    }

    public function test_a_published_lesson_reaches_its_department_only()
    {
        $lesson = $this->draftLesson();
        $this->actingAs($this->admin)->post(route('lessons.publish', $lesson));

        $learner = $this->learner();
        $this->submitPreTest($learner);

        $this->assertContains($lesson->id, $this->visibleLessonIds($learner));
        $this->assertSame(1, app(JourneyService::class)->lessonsTotal($learner));

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', $lesson))
            ->assertRedirect();

        // Another department of the same hotel: not listed, and 403 by URL.
        $otherDepartment = Department::factory()->create();
        $outsider = User::factory()->employee()->firstLoginDone()->forHotel($this->hotel, $otherDepartment)->create(['username' => 'karim']);

        $this->assertNotContains($lesson->id, $this->visibleLessonIds($outsider));

        $this->actingAs($outsider)
            ->get(route('learn.lessons.show', $lesson))
            ->assertForbidden();
    }

    public function test_a_hotel_scoped_lesson_is_hidden_from_other_hotels()
    {
        $otherHotel = Hotel::factory()->create();
        $lesson = $this->draftLesson(hotel: $otherHotel);
        $this->actingAs($this->admin)->post(route('lessons.publish', $lesson));

        $learner = $this->learner();
        $this->submitPreTest($learner);

        $this->assertNotContains($lesson->id, $this->visibleLessonIds($learner));

        $this->actingAs($learner)
            ->get(route('learn.lessons.show', $lesson))
            ->assertForbidden();

        // An employee of that hotel and department does see it.
        $insider = User::factory()->employee()->firstLoginDone()->forHotel($otherHotel, $this->department)->create(['username' => 'lina']);
        $this->submitPreTest($insider);

        $this->assertContains($lesson->id, $this->visibleLessonIds($insider));
    }

    public function test_draft_lessons_and_lessons_in_draft_units_never_show()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $draft = $this->draftLesson();

        // Published lesson inside a draft unit of a published course.
        $course = Course::factory()->forDepartment($this->department->id)->published()->create();
        $hiddenUnit = Unit::factory()->draft()->create(['course_id' => $course->id]);
        $inDraftUnit = Lesson::factory()->published()->withBlocks()->create(['unit_id' => $hiddenUnit->id]);

        $visible = $this->visibleLessonIds($learner);
        $this->assertNotContains($draft->id, $visible);
        $this->assertNotContains($inDraftUnit->id, $visible);
        $this->assertSame(0, app(JourneyService::class)->lessonsTotal($learner));

        $this->actingAs($learner)->get(route('learn.lessons.show', $draft))->assertForbidden();
        $this->actingAs($learner)->get(route('learn.lessons.show', $inDraftUnit))->assertForbidden();
    }
}
