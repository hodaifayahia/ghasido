<?php

namespace App\Services\Audio;

use App\Models\Block;
use App\Models\Lesson;
use App\Models\LexiconItem;

/**
 * Finds every English string that can be played from lesson content
 * (CTRL-05, TTS-01, TTS-02). Keeping this in one service makes the per-block
 * Generate Audio button and the all-lessons generation action use the same
 * inventory.
 */
final class PlayableTextCollector
{
    /**
     * @return list<string>
     */
    public function forLesson(Lesson $lesson): array
    {
        $lesson->loadMissing(['blocks.lexiconItems', 'blocks.placements.activity']);

        $texts = [];

        foreach ($lesson->blocks as $block) {
            foreach ($this->forBlock($block) as $text) {
                $texts[$this->key($text)] = $text;
            }
        }

        return array_values($texts);
    }

    /**
     * @return array{lessons: int, texts: list<string>}
     */
    public function forAllLessons(): array
    {
        $lessons = Lesson::query()
            ->with(['blocks.lexiconItems', 'blocks.placements.activity'])
            ->orderBy('id')
            ->get();
        $texts = [];

        foreach ($lessons as $lesson) {
            foreach ($this->forLesson($lesson) as $text) {
                $texts[$this->key($text)] = $text;
            }
        }

        // Include reusable words and expressions even when an admin has not
        // attached the item to a lesson block yet. The bulk action promises
        // all existing content, not only content currently visible in a
        // lesson (CMS-06, TTS-01..02).
        LexiconItem::query()->orderBy('id')->each(function (LexiconItem $item) use (&$texts): void {
            foreach ($item->playableTexts() as $text) {
                $texts[$this->key($text)] = $text;
            }
        });

        return [
            'lessons' => $lessons->count(),
            'texts' => array_values($texts),
        ];
    }

    /**
     * @return list<string>
     */
    public function forBlock(Block $block): array
    {
        $texts = [];
        $this->collectPayload($block->settings ?? [], $texts);

        foreach ($block->lexiconItems as $item) {
            foreach ($item->playableTexts() as $text) {
                $texts[$this->key($text)] = $text;
            }
        }

        foreach ($block->placements as $placement) {
            $activity = $placement->activity;

            if ($activity !== null) {
                $this->collectPayload($activity->payload ?? [], $texts);
            }
        }

        return array_values($texts);
    }

    /**
     * The lesson payload uses `text`, `audio_text`, and `guest_audio_text` for
     * English playback. Other strings such as `body`, `tip`, `arabic`, and
     * `situation` are instructional/display copy and are not synthesized.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, string>  $texts
     */
    private function collectPayload(array $payload, array &$texts): void
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $this->collectPayload($value, $texts);

                continue;
            }

            if (! is_string($value) || ! is_string($key) || ! $this->isPlayableKey($key)) {
                continue;
            }

            $normalised = trim($value);

            if ($normalised !== '') {
                $texts[$this->key($value)] = $value;
            }
        }
    }

    private function isPlayableKey(string $key): bool
    {
        return $key === 'text'
            || $key === 'audio_text'
            || $key === 'guest_audio_text'
            || str_ends_with($key, '_text');
    }

    private function key(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }
}
