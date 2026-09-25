<?php

namespace App\Contracts;

/**
 * The structured verdict on one spoken answer, judged from its transcript
 * (TEST-07, AIE-01, AIE-04, RESP-05).
 *
 * Criteria are criterion → {score, comment}, never a free-text blob, so they
 * export to long format (AIE-04). Stored on `attempts.ai_feedback` through
 * toArray(), next to the transcript on the same row.
 */
final readonly class SpeakingEvaluation
{
    /** The criteria every provider must return, in display order. */
    public const CRITERIA = ['task_completion', 'accuracy', 'vocabulary', 'politeness'];

    /**
     * @param  array<string, array{score: int, comment: string}>  $criteria  keyed by self::CRITERIA
     */
    public function __construct(
        public array $criteria,
        public string $betterAnswer,
        public string $summary,
        public AiUsageInfo $usage,
    ) {}

    /**
     * The mean of the criterion scores, 0-100, two decimals.
     */
    public function overallScore(): float
    {
        if ($this->criteria === []) {
            return 0.0;
        }

        $scores = array_map(
            static fn (array $criterion): int => $criterion['score'],
            $this->criteria,
        );

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * The `attempts.ai_feedback` shape, the same keys a writing verdict uses
     * so reports read both alike.
     *
     * @return array{criteria: array<string, array{score: int, comment: string}>, better_answer: string, summary: string, kind: string}
     */
    public function toArray(): array
    {
        return [
            'criteria' => $this->criteria,
            'better_answer' => $this->betterAnswer,
            'summary' => $this->summary,
            'kind' => 'speaking',
        ];
    }
}
