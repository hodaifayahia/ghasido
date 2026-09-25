<?php

namespace App\Contracts;

/**
 * A whole AI-drafted lesson (GEN-01, GEN-03, LESSON-01, LESSON-02).
 *
 * A draft the admin reviews and publishes, never auto-published (GEN-03).
 * The shape mirrors the default block order (spec 0004): a situation (with
 * its objectives and a picture prompt), a vocabulary set, useful
 * expressions, listen-and-repeat sentences, a dialogue and a practice set.
 * App\Services\Content\LessonGenerator turns each list into a Block of the
 * matching type.
 *
 * Arabic travels only in the fields Show Meaning reveals (CTRL-01, CTRL-02).
 * Every English item later gets its own stored audio; the draft carries only
 * text so generation stays fast and cheap (CTRL-05).
 */
final readonly class LessonDraft
{
    /**
     * @param  list<array{english: string, arabic: string, explanation: string, example: string}>  $vocabulary
     * @param  list<array{english: string, arabic: string, explanation: string, example: string}>  $expressions
     * @param  list<array{speaker: string, text: string, arabic: string}>  $dialogue
     * @param  list<array{prompt: string, options: list<string>, answer_index: int, explanation: string}>  $practice
     * @param  list<string>  $objectives
     * @param  list<array{text: string, arabic: string}>  $listenRepeat
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public string $situationText,
        public string $situationQuote,
        public array $vocabulary,
        public array $expressions,
        public array $dialogue,
        public array $practice,
        public AiUsageInfo $usage,
        public array $objectives = [],
        public string $imagePrompt = '',
        public array $listenRepeat = [],
    ) {}

    /**
     * The `lessons.ai_draft` shape, so the builder and the CMS preview read
     * the same structure.
     *
     * @return array{title: string, subtitle: string, situation_text: string, situation_quote: string, vocabulary: list<array{english: string, arabic: string, explanation: string, example: string}>, expressions: list<array{english: string, arabic: string, explanation: string, example: string}>, dialogue: list<array{speaker: string, text: string, arabic: string}>, practice: list<array{prompt: string, options: list<string>, answer_index: int, explanation: string}>, objectives: list<string>, image_prompt: string, listen_repeat: list<array{text: string, arabic: string}>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'situation_text' => $this->situationText,
            'situation_quote' => $this->situationQuote,
            'vocabulary' => $this->vocabulary,
            'expressions' => $this->expressions,
            'dialogue' => $this->dialogue,
            'practice' => $this->practice,
            'objectives' => $this->objectives,
            'image_prompt' => $this->imagePrompt,
            'listen_repeat' => $this->listenRepeat,
        ];
    }
}
