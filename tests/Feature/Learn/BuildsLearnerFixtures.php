<?php

namespace Tests\Feature\Learn;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\TestType;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;

/**
 * The fixtures every learner feature test starts from: one hotel, one
 * department, an employee past the first-login screen, and published
 * content in their reach (spec 0003 Part E).
 */
trait BuildsLearnerFixtures
{
    protected Hotel $hotel;

    protected Department $department;

    protected function setUpLearnerFixtures(): void
    {
        $this->hotel = Hotel::factory()->create();
        $this->department = Department::factory()->create();

        // The employee pages are built by the learn-shell lane right after
        // this one; Inertia's page-exists check must not fail these tests,
        // and neither may a stale Vite manifest when a full page renders.
        config()->set('inertia.testing.ensure_pages_exist', false);
        $this->withoutVite();
    }

    /**
     * An active employee of the fixture hotel and department, past the
     * first-login screen, signing in as `samira`.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function learner(array $attributes = []): User
    {
        return User::factory()
            ->employee()
            ->firstLoginDone()
            ->forHotel($this->hotel, $this->department)
            ->create($attributes + ['username' => 'samira']);
    }

    /**
     * A published lesson on a published course of the fixture department,
     * with the given block types (the nine defaults when null).
     *
     * @param  list<BlockType>|null  $blocks
     */
    protected function publishedLesson(?array $blocks = null, ?Hotel $hotel = null, ?Department $department = null): Lesson
    {
        $course = Course::factory()
            ->forDepartment(($department ?? $this->department)->id)
            ->when($hotel !== null, fn ($factory) => $factory->forHotel($hotel?->id ?? 0))
            ->published()
            ->create();

        $unit = Unit::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()
            ->published()
            ->withBlocks($blocks)
            ->create(['unit_id' => $unit->id]);
    }

    /**
     * Put the learner through Gate 1 (JOURNEY-01).
     */
    /**
     * A published Pre-test for the fixture department, so gate 1 applies
     * (JOURNEY-01: with no Pre-test to sit, lessons are open).
     */
    protected function publishedPreTest(): Test
    {
        return Test::query()
            ->where('department_id', $this->department->id)
            ->where('type', TestType::Pre->value)
            ->where('status', ContentStatus::Published->value)
            ->whereNull('hotel_id')
            ->first()
            ?? Test::factory()->pre()->create(['department_id' => $this->department->id]);
    }

    protected function submitPreTest(User $learner): Test
    {
        $test = $this->publishedPreTest();

        TestAttempt::factory()->submitted()->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
        ]);

        return $test;
    }
}
