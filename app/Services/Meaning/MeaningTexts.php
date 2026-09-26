<?php

namespace App\Services\Meaning;

use App\Models\Activity;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\TextTranslation;

/**
 * The English texts a learner can tap Show Meaning on, per piece of content
 * (CTRL-01..03; user request 2026-09-26: translations are made when the
 * content is written, not when a learner taps).
 *
 * Block settings and activity payloads are free-form JSON, so their strings
 * are collected generically: every string value that reads like text, minus
 * the keys that hold ids, media, layout or answer keys.
 */
final class MeaningTexts
{
    /** Keys whose values are never learner-facing English. */
    private const array SKIP_KEYS = [
        'id', 'uuid', 'key', 'type', 'kind', 'layout', 'variant', 'tone', 'accent', 'color', 'colour',
        'icon', 'url', 'href', 'src', 'path', 'slug', 'media', 'media_id', 'image', 'image_id', 'audio',
        'audio_id', 'audio_url', 'audio_slow', 'video', 'video_url', 'thumbnail', 'voice', 'mime',
        'format', 'locale', 'lang', 'status', 'arabic', 'arabic_meaning', 'prompt_arabic', 'meanings',
        'correct', 'correct_answer', 'correct_answers', 'answer_key', 'scoring', 'style', 'size',
    ];

    /** @return list<string> */
    public static function forLesson(Lesson $lesson): array
    {
        $lesson->loadMissing(['blocks.placements.activity', 'blocks.scenarios']);

        $texts = [$lesson->title, $lesson->introduction];

        // Objectives are plain strings or {text: …} rows depending on age.
        array_push($texts, ...self::strings($lesson->objectives ?? []));

        foreach ($lesson->blocks as $block) {
            array_push($texts, ...self::forBlock($block));
        }

        return self::clean($texts);
    }

    /** @return list<string> */
    public static function forBlock(Block $block): array
    {
        $texts = self::strings($block->settings ?? []);

        foreach ($block->placements as $placement) {
            if ($placement->activity instanceof Activity) {
                array_push($texts, ...self::forActivity($placement->activity));
            }
        }

        foreach ($block->scenarios as $scenario) {
            array_push($texts, ...self::forScenario($scenario));
        }

        return self::clean($texts);
    }

    /** @return list<string> */
    public static function forActivity(Activity $activity): array
    {
        return self::clean([$activity->title, $activity->prompt, ...self::strings($activity->payload ?? [])]);
    }

    /** @return list<string> */
    public static function forScenario(AiScenario $scenario): array
    {
        return self::clean([$scenario->title, $scenario->description, $scenario->situation, $scenario->objective, $scenario->quote, $scenario->tip]);
    }

    /** @return list<string> */
    public static function forTest(Test $test): array
    {
        $test->loadMissing('questions.activity');

        $texts = [$test->title, $test->intro, ...self::strings($test->settings ?? [])];

        foreach ($test->questions as $placement) {
            if ($placement->activity instanceof Activity) {
                array_push($texts, ...self::forActivity($placement->activity));
            }
        }

        return self::clean($texts);
    }

    /** @return list<string> */
    public static function forCourse(Course $course): array
    {
        $course->loadMissing('units');

        return self::clean([$course->title, $course->description, ...$course->units->pluck('title')->all()]);
    }

    /**
     * Every string in a JSON structure that reads like text.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data): array
    {
        $found = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SKIP_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                array_push($found, ...self::strings($value));
            } elseif (is_string($value) && self::readsLikeText($value)) {
                $found[] = $value;
            }
        }

        return $found;
    }

    /**
     * Normalised, distinct, translatable texts.
     *
     * @param  array<int, mixed>  $texts
     * @return list<string>
     */
    public static function clean(array $texts): array
    {
        $out = [];

        foreach ($texts as $text) {
            if (! is_string($text)) {
                continue;
            }

            $text = TextTranslation::normalise($text);

            if ($text !== '' && mb_strlen($text) <= TextTranslation::MAX_LENGTH && preg_match('/\p{L}/u', $text) === 1) {
                $out[TextTranslation::hashOf($text)] = $text;
            }
        }

        return array_values($out);
    }

    private static function readsLikeText(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) < 2 || mb_strlen($value) > TextTranslation::MAX_LENGTH) {
            return false;
        }

        // URLs, paths, colours, file names and snake/kebab identifiers.
        if (preg_match('#^(https?:|/|\#|data:)#i', $value) === 1
            || preg_match('/^[\w\-]+\.(png|jpe?g|webp|gif|svg|mp3|mp4|wav|m4a|webm|pdf)$/i', $value) === 1
            || preg_match('/^[a-z0-9]+([_\-][a-z0-9]+)+$/', $value) === 1) {
            return false;
        }

        // Arabic already, or no Latin letter at all.
        return preg_match('/[A-Za-z]/', $value) === 1 && preg_match('/\p{Arabic}/u', $value) !== 1;
    }
}
