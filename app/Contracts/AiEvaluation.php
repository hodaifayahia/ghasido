<?php

namespace App\Contracts;

/**
 * The structured verdict on one complete role-play conversation
 * (AIE-01, AIE-04, RP-08; spec 0003 Part C and G.6).
 *
 * Criteria are structured, never a free-text blob, so they export to long
 * format one row per criterion (AIE-04). The two helpers below are the only
 * place the stored JSON shapes are spelled out, so the job, the seeder and
 * the fake provider cannot drift apart.
 */
final readonly class AiEvaluation
{
    /**
     * @param  array<string, array{score: int, comment: string}>  $criteria  keyed by criterion key (pronunciation, grammar, ...)
     * @param  list<string>  $didWell
     * @param  list<array{title: string, text: string}>  $improve
     * @param  array{yours: string, better: string}  $betterExpression
     */
    public function __construct(
        public array $criteria,
        public int $overall,
        public array $didWell,
        public array $improve,
        public array $betterExpression,
        public string $keyPhrase,
        public string $summaryLabel,
        public string $summaryText,
        public AiUsageInfo $usage,
        public string $footnote = '',
    ) {}

    /**
     * `roleplay_attempts.criteria_scores`: `{"pronunciation": 70, ...}`.
     *
     * @return array<string, int>
     */
    public function criteriaScores(): array
    {
        return array_map(
            static fn (array $criterion): int => $criterion['score'],
            $this->criteria,
        );
    }

    /**
     * `roleplay_attempts.feedback`, exactly the spec G.6 shape.
     *
     * @return array{summary_label: string, summary_text: string, did_well: list<string>, improve: list<array{title: string, text: string}>, better_expression: array{yours: string, better: string}, key_phrase: string, footnote: string}
     */
    public function toFeedbackArray(): array
    {
        return [
            'summary_label' => $this->summaryLabel,
            'summary_text' => $this->summaryText,
            'did_well' => $this->didWell,
            'improve' => $this->improve,
            'better_expression' => $this->betterExpression,
            'key_phrase' => $this->keyPhrase,
            'footnote' => $this->footnote,
        ];
    }
}
