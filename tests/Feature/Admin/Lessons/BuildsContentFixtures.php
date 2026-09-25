<?php

namespace Tests\Feature\Admin\Lessons;

use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use App\Models\User;

/**
 * One shared course with a unit and a fully blocked lesson, plus the two
 * actors every CMS test needs: the platform owner and a manager of a hotel.
 */
trait BuildsContentFixtures
{
    protected User $owner;

    protected Hotel $hotel;

    protected Department $department;

    protected Course $course;

    protected Unit $unit;

    protected Lesson $lesson;

    protected function buildContent(): void
    {
        $this->withoutVite();

        $this->owner = User::factory()->superAdmin()->create();
        $this->hotel = Hotel::factory()->create(['name' => 'La Gazelle d\'Or']);
        $this->department = Department::factory()->create(['name' => 'Reception', 'hotel_id' => null, 'position' => 1]);

        $this->course = Course::factory()
            ->published()
            ->forDepartment($this->department->id)
            ->create(['title' => 'Guest Service Basics', 'tone' => 'brand']);

        $this->unit = Unit::factory()->create(['course_id' => $this->course->id, 'title' => 'Welcoming Guests', 'position' => 1]);

        $this->lesson = Lesson::factory()
            ->withBlocks()
            ->create(['unit_id' => $this->unit->id, 'title' => 'Greeting Guests', 'position' => 1]);
    }

    protected function manager(?Hotel $hotel = null): User
    {
        return User::factory()
            ->manager()
            ->forHotel($hotel ?? $this->hotel, $this->department)
            ->create();
    }
}
