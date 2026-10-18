<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Unit;

/**
 * One course as My Lessons and the English Essentials topic page show it:
 * its published units and lessons, each with done / locked (JOURNEY-01,
 * LESSON-04, PROG-02).
 */
class CourseOutline
{
    /**
     * The course must be loaded with `units.lessons` (published lessons,
     * `withCount('visibleBlocks')`).
     *
     * @param  list<int>  $completed
     * @return array<string, mixed>
     */
    public function present(Course $course, array $completed, bool $locked): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'tone' => $course->tone,
            'units' => $course->units
                ->filter(fn (Unit $unit): bool => $unit->isPublished())
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'title' => $unit->title,
                    'lessons' => $unit->lessons->map(fn (Lesson $lesson): array => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'position' => $lesson->position,
                        'estimatedMinutes' => $lesson->estimated_minutes,
                        'stepCount' => (int) ($lesson->visible_blocks_count ?? 0),
                        'completed' => in_array($lesson->id, $completed, true),
                        'locked' => $locked,
                        'url' => route('learn.lessons.show', ['lesson' => $lesson]),
                    ])->values()->all(),
                ])->values()->all(),
        ];
    }

    /**
     * The eager load the outline needs.
     *
     * @return array<string, \Closure>
     */
    public static function with(): array
    {
        return [
            'units' => fn ($units) => $units->with(['lessons' => fn ($lessons) => $lessons->published()->withCount('visibleBlocks')]),
        ];
    }
}
