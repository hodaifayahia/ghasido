<?php

namespace App\Contracts;

/**
 * An AI-drafted role-play scenario (GEN-01, GEN-03, RP-01, RP-04).
 *
 * A draft, never a published scenario: the admin reviews it in the CMS and
 * publishes it explicitly (GEN-03, GEN-04). The prompt is assembled from the
 * admin's title/department/difficulty and a fixed instruction, never a raw
 * client prompt (RP-04). Stored as `ai_scenarios.ai_draft` through toArray().
 */
final readonly class ScenarioDraft
{
    /**
     * @param  list<string>  $goals
     * @param  list<string>  $usefulPhrases
     */
    public function __construct(
        public string $description,
        public string $situation,
        public string $aiRole,
        public string $employeeRole,
        public string $objective,
        public array $goals,
        public array $usefulPhrases,
        public ?string $quote,
        public ?string $tip,
        public AiUsageInfo $usage,
    ) {}

    /**
     * The `ai_scenarios.ai_draft` shape, keyed like the columns it may fill.
     *
     * @return array{description: string, situation: string, ai_role: string, employee_role: string, objective: string, goals: list<string>, useful_phrases: list<string>, quote: string|null, tip: string|null}
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'situation' => $this->situation,
            'ai_role' => $this->aiRole,
            'employee_role' => $this->employeeRole,
            'objective' => $this->objective,
            'goals' => $this->goals,
            'useful_phrases' => $this->usefulPhrases,
            'quote' => $this->quote,
            'tip' => $this->tip,
        ];
    }
}
