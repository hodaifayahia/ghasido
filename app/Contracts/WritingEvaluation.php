<?php

namespace App\Contracts;

/**
 * The structured verdict on one written answer (WRITE-03, AIE-01, AIE-04).
 *
 * Stored on `attempts.ai_feedback` through toArray() next to the verbatim
 * answer it judged (TEST-08, DATA-03; spec 0003 B.9).
 */
final readonly class WritingEvaluation
{
    /**
     * @param  array<string, array{score: int, comment: string}>  $criteria  keyed task_completion, accuracy, politeness, clarity
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
     * The `attempts.ai_feedback` shape (spec 0003 B.9).
     *
     * @return array{criteria: array<string, array{score: int, comment: string}>, better_answer: string, summary: string}
     */
    public function toArray(): array
    {
        return [
            'criteria' => $this->criteria,
            'better_answer' => $this->betterAnswer,
            'summary' => $this->summary,
        ];
    }
}
