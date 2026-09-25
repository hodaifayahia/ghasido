<?php

namespace App\Contracts;

/**
 * The coach's words on one pronunciation check (spec 0006 §5): one line of
 * praise, at most two word tips, the next thing to try, and an optional
 * Arabic hint that is only ever shown behind Show Meaning (CTRL-01/02).
 * The coach explains the scores; it never changes them.
 */
final readonly class PronunciationCoaching
{
    /**
     * @param  list<array{word: string, tip: string}>  $tips
     */
    public function __construct(
        public string $headline,
        public array $tips,
        public string $next,
        public ?string $arabic,
        public AiUsageInfo $usage,
    ) {}

    /**
     * The `pronunciation_attempts.feedback` shape.
     *
     * @return array{headline: string, tips: list<array{word: string, tip: string}>, next: string, arabic: string|null}
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'tips' => $this->tips,
            'next' => $this->next,
            'arabic' => $this->arabic,
        ];
    }
}
