<?php

namespace App\Services\Pronunciation;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\LexiconItem;

/**
 * Which texts of a lesson a learner can be asked to say, and the one they
 * were asked to say on a given step (spec 0006 §4, §5, §7).
 *
 * Speakable: the sentences of Listen & Repeat, and the English of the words
 * and expressions of Vocabulary / Expressions. A drill narrows a sentence to
 * one of its judged words.
 */
class PronunciationTargets
{
    /** The block types a learner can speak on. */
    public const array SPEAKABLE_BLOCKS = [BlockType::ListenRepeat, BlockType::Vocabulary, BlockType::Expressions];

    /**
     * `$item` is the sentence index for Listen & Repeat and the lexicon item
     * id for Vocabulary / Expressions. Null when the block has no such item
     * or the word index is not a judged word of it.
     */
    public function resolve(Lesson $lesson, Block $block, int $item, ?int $word = null): ?PronunciationTarget
    {
        if ($block->lesson_id !== $lesson->id) {
            return null;
        }

        [$sentence, $itemIndex, $lexiconItemId] = match ($block->type) {
            BlockType::ListenRepeat => [$this->sentenceAt($block, $item), $item, null],
            BlockType::Vocabulary, BlockType::Expressions => [$this->lexiconText($block, $item), null, $item],
            default => [null, null, null],
        };

        if ($sentence === null) {
            return null;
        }

        $text = $sentence;

        if ($word !== null) {
            $token = ReferenceText::from($sentence)->tokens[$word] ?? null;

            if ($token === null || ! $token['judged']) {
                return null;
            }

            $text = $token['display'];
        }

        return new PronunciationTarget($lesson, $block, $text, $sentence, $itemIndex, $lexiconItemId, $word);
    }

    /**
     * Every text a learner of this lesson can be asked to say.
     *
     * @return list<string>
     */
    public function speakableTexts(Lesson $lesson): array
    {
        $texts = [];

        foreach ($lesson->blocks()->with('lexiconItems')->get() as $block) {
            if ($block->type === BlockType::ListenRepeat) {
                foreach ($this->sentences($block) as $sentence) {
                    $texts[$sentence] = $sentence;
                }
            }

            if ($block->type === BlockType::Vocabulary || $block->type === BlockType::Expressions) {
                foreach ($block->lexiconItems as $lexicon) {
                    $texts[$lexicon->english_text] = $lexicon->english_text;
                }
            }
        }

        return array_values($texts);
    }

    /**
     * @return list<string>
     */
    private function sentences(Block $block): array
    {
        $items = $block->settings['items'] ?? null;
        $sentences = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $text = is_array($item) ? ($item['text'] ?? null) : null;

            if (is_string($text) && trim($text) !== '') {
                $sentences[] = trim($text);
            }
        }

        return $sentences;
    }

    private function sentenceAt(Block $block, int $index): ?string
    {
        $items = $block->settings['items'] ?? null;
        $text = is_array($items) && is_array($items[$index] ?? null) ? ($items[$index]['text'] ?? null) : null;

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    private function lexiconText(Block $block, int $lexiconItemId): ?string
    {
        /** @var LexiconItem|null $item */
        $item = $block->lexiconItems()->whereKey($lexiconItemId)->first();

        return $item !== null && trim($item->english_text) !== '' ? trim($item->english_text) : null;
    }
}
