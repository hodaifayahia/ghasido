<?php

namespace App\Observers;

use App\Jobs\TranslateContent;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Block;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\Unit;

/**
 * Queue Show Meaning drafts whenever learner-facing content is written
 * (user request 2026-09-26): the lesson, test or course that owns the saved
 * row gets one TranslateContent pass. Only texts without a translation are
 * sent to the AI, so re-saving costs nothing.
 */
final class MeaningHooks
{
    public static function register(): void
    {
        Lesson::saved(fn (Lesson $lesson) => TranslateContent::afterSave('lesson', $lesson->id));
        Block::saved(fn (Block $block) => TranslateContent::afterSave('lesson', $block->lesson_id));
        Test::saved(fn (Test $test) => TranslateContent::afterSave('test', $test->id));
        Course::saved(fn (Course $course) => TranslateContent::afterSave('course', $course->id));
        Unit::saved(fn (Unit $unit) => TranslateContent::afterSave('course', $unit->course_id));
        ActivityPlacement::saved(fn (ActivityPlacement $placement) => self::placement($placement));
        Activity::saved(function (Activity $activity): void {
            foreach ($activity->placements()->with('placeable')->get() as $placement) {
                self::placement($placement);
            }
        });
    }

    private static function placement(ActivityPlacement $placement): void
    {
        $owner = $placement->placeable;

        if ($owner instanceof Block) {
            TranslateContent::afterSave('lesson', $owner->lesson_id);
        } elseif ($owner instanceof Test) {
            TranslateContent::afterSave('test', $owner->id);
        }
    }
}
