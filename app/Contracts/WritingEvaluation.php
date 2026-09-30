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
     * The default rubric; a writing item may name its own (`criteria`).
     *
     * @var array<string, string>
     */
    public const DEFAULT_CRITERIA = [
        'task_completion' => 'Task completion',
        'accuracy' => 'Accuracy',
        'politeness' => 'Politeness',
        'clarity' => 'Clarity',
    ];

    /**
     * @param  array<string, array{score: int, comment: string, label?: string}>  $criteria  keyed by the rubric's criteria (task_completion, accuracy, politeness, clarity by default)
     * @param  list<array{original: string, corrected: string, note: string}>  $corrections  the learner's phrases put right (client report 2026-09-29)
     */
    public function __construct(
        public array $criteria,
        public string $betterAnswer,
        public string $summary,
        public AiUsageInfo $usage,
        public array $corrections = [],
    ) {}

    /**
     * The rubric of one writing item: its own `criteria` list of `{key,
     * label}`, or the default four.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string> key => label
     */
    public static function rubricFor(array $item): array
    {
        $rubric = [];

        foreach (is_array($item['criteria'] ?? null) ? $item['criteria'] : [] as $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $label = is_string($criterion['label'] ?? null) ? trim($criterion['label']) : '';
            $key = is_string($criterion['key'] ?? null) && trim($criterion['key']) !== ''
                ? trim($criterion['key'])
                : trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');

            if ($key !== '' && $label !== '') {
                $rubric[$key] = $label;
            }
        }

        return $rubric === [] ? self::DEFAULT_CRITERIA : $rubric;
    }

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
     * @return array{criteria: array<string, array{score: int, comment: string, label?: string}>, better_answer: string, summary: string, corrections: list<array{original: string, corrected: string, note: string}>}
     */
    public function toArray(): array
    {
        return [
            'criteria' => $this->criteria,
            'better_answer' => $this->betterAnswer,
            'summary' => $this->summary,
            'corrections' => $this->corrections,
        ];
    }
}
