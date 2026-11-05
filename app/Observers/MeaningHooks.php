<?php

namespace App\Observers;

use App\Jobs\TranslateContent;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Block;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\Test;
use App\Models\Unit;
use App\Services\Meaning\InlineMeanings;
use App\Services\Meaning\MeaningTranslations;

/**
 * Queue Show Meaning drafts whenever learner-facing content is written
 * (user request 2026-09-26): the lesson, test or course that owns the saved
 * row gets one TranslateContent pass. Only texts without a translation are
 * sent to the AI, so re-saving costs nothing.
 *
 * Inline Arabic still written on a word, a sentence or a dialogue line (a
 * seeded lesson, the AI lesson generator) fills the per-text store's gap
 * for its English text, so Show Meaning finds it in any page (client
 * request 2026-11-05).
 */
final class MeaningHooks
{
    public static function register(): void
    {
        Lesson::saved(fn (Lesson $lesson) => TranslateContent::afterSave('lesson', $lesson->id));
        // Inline Arabic first, so the AI pass after it has nothing to redo.
        Block::saved(function (Block $block): void {
            if (in_array($block->type, InlineMeanings::BLOCK_TYPES, true) && ($block->wasRecentlyCreated || $block->wasChanged('settings'))) {
                app(InlineMeanings::class)->rememberBlock($block);
            }
        });
        Block::saved(fn (Block $block) => TranslateContent::afterSave('lesson', $block->lesson_id));
        LexiconItem::saved(function (LexiconItem $item): void {
            if ($item->wasRecentlyCreated || $item->wasChanged(['english_text', 'arabic_meaning', 'hotel_example', 'hotel_example_arabic'])) {
                app(InlineMeanings::class)->rememberLexicon($item);
            }

            if (! MeaningTranslations::autoTranslate()) {
                return;
            }

            // A word's English is a Show Meaning text of every lesson using it.
            foreach ($item->blocks()->pluck('blocks.lesson_id')->unique() as $lessonId) {
                TranslateContent::afterSave('lesson', is_numeric($lessonId) ? (int) $lessonId : null);
            }
        });
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
