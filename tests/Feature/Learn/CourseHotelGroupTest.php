<?php

namespace Tests\Feature\Learn;

use App\Enums\CourseHotelScope;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Hotel groups for shared courses (client request 2026-11-05; ORG-04,
 * CMS-04, ROLE-02): a shared course can be shown to only a chosen group of
 * hotels, or to every hotel except a chosen group, and the server enforces
 * it on My Lessons and on the lesson's own URL.
 */
class CourseHotelGroupTest extends TestCase
{
    use BuildsLearnerFixtures;
    use RefreshDatabase;

    private User $admin;

    private Hotel $otherHotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        $this->admin = User::factory()->superAdmin()->create();
        $this->otherHotel = Hotel::factory()->create();
    }

    /**
     * @return list<int>
     */
    private function visibleCourseIds(User $learner): array
    {
        $ids = [];

        $this->actingAs($learner)
            ->get(route('learn.lessons'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids): void {
                foreach ($page->toArray()['props']['courses'] as $course) {
                    $ids[] = $course['id'];
                }
            });

        return $ids;
    }

    private function setGroup(Course $course, CourseHotelScope $scope, array $hotelIds): void
    {
        $this->actingAs($this->admin)
            ->patch(route('courses.update', $course), ['hotel_scope' => $scope->value, 'hotel_ids' => $hotelIds])
            ->assertSessionHasNoErrors();
    }

    public function test_a_shared_course_reaches_every_hotel_by_default()
    {
        $lesson = $this->publishedLesson();
        $learner = $this->learner();

        $this->assertContains($lesson->course_id, $this->visibleCourseIds($learner));
    }

    public function test_only_these_hotels_hides_the_course_from_every_other_hotel()
    {
        $lesson = $this->publishedLesson();
        $course = $lesson->course()->firstOrFail();
        $learner = $this->learner();

        $this->setGroup($course, CourseHotelScope::Only, [$this->otherHotel->id]);

        $this->assertNotContains($course->id, $this->visibleCourseIds($learner));
        $this->actingAs($learner)->get(route('learn.lessons.show', $lesson))->assertForbidden();

        $this->setGroup($course, CourseHotelScope::Only, [$this->otherHotel->id, $this->hotel->id]);

        $this->assertContains($course->id, $this->visibleCourseIds($learner));
    }

    public function test_all_hotels_except_these_leaves_out_the_chosen_hotels()
    {
        $lesson = $this->publishedLesson();
        $course = $lesson->course()->firstOrFail();
        $learner = $this->learner();

        $this->setGroup($course, CourseHotelScope::Except, [$this->hotel->id]);

        $this->assertNotContains($course->id, $this->visibleCourseIds($learner));
        $this->actingAs($learner)->get(route('learn.lessons.show', $lesson))->assertForbidden();

        $this->setGroup($course, CourseHotelScope::Except, [$this->otherHotel->id]);

        $this->assertContains($course->id, $this->visibleCourseIds($learner));
        $this->assertSame(CourseHotelScope::Except, $course->fresh()?->hotel_scope);
    }

    public function test_a_manager_cannot_change_a_courses_hotel_group()
    {
        $lesson = $this->publishedLesson();
        $manager = User::factory()->manager()->forHotel($this->hotel, $this->department)->create();

        $this->actingAs($manager)
            ->patch(route('courses.update', $lesson->course_id), ['hotel_scope' => 'except', 'hotel_ids' => [$this->otherHotel->id]])
            ->assertForbidden();

        $this->assertSame(CourseHotelScope::All, $lesson->course()->firstOrFail()->hotel_scope);
    }
}
