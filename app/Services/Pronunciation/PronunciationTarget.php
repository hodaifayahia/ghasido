<?php

namespace App\Services\Pronunciation;

use App\Models\Block;
use App\Models\Lesson;

/**
 * What a learner was asked to say, resolved on the server from the step
 * they are on (spec 0006 §7): never text sent by the browser.
 */
final readonly class PronunciationTarget
{
    public function __construct(
        public Lesson $lesson,
        public Block $block,
        /** The words to judge: the sentence, or one word of it in a drill. */
        public string $text,
        /** The whole sentence the word came from (equal to $text for a sentence). */
        public string $sentence,
        public ?int $itemIndex,
        public ?int $lexiconItemId,
        public ?int $wordIndex,
    ) {}

    public function isWordDrill(): bool
    {
        return $this->wordIndex !== null;
    }
}
