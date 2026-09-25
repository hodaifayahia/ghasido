<?php

namespace App\Contracts;

/**
 * The AI's short briefing on a hotel's (or the portfolio's) training figures
 * for its managers (spec 0005 §4.1). Words only, from aggregate figures:
 * the at-risk list beside it is computed by rules, never by the model.
 */
final readonly class DashboardBriefingDraft
{
    /**
     * @param  list<string>  $highlights  what is going well
     * @param  list<string>  $concerns  what needs attention
     * @param  list<string>  $actions  concrete next steps for the manager
     */
    public function __construct(
        public string $headline,
        public array $highlights,
        public array $concerns,
        public array $actions,
        public AiUsageInfo $usage,
    ) {}

    /**
     * @return array{headline: string, highlights: list<string>, concerns: list<string>, actions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'highlights' => $this->highlights,
            'concerns' => $this->concerns,
            'actions' => $this->actions,
        ];
    }
}
