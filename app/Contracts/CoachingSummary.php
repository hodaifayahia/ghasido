<?php

namespace App\Contracts;

/**
 * The AI's coaching summary for one learner (spec 0005 §3.5). Words only:
 * the next step it sits beside is chosen by the server, never by the model.
 */
final readonly class CoachingSummary
{
    /**
     * @param  list<string>  $strengths  one or two things going well
     * @param  list<string>  $focus  one or two things to work on
     */
    public function __construct(
        public string $headline,
        public array $strengths,
        public array $focus,
        public string $tip,
        public AiUsageInfo $usage,
    ) {}

    /**
     * @return array{headline: string, strengths: list<string>, focus: list<string>, tip: string}
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'strengths' => $this->strengths,
            'focus' => $this->focus,
            'tip' => $this->tip,
        ];
    }
}
