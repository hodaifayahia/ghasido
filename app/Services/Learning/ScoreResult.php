<?php

namespace App\Services\Learning;

/**
 * What ActivityScorer says about one raw answer (TEST-06, DATA-01, spec 0003
 * B.9).
 *
 * `max_score` is the number of items in the version, `score` the number the
 * learner got right, `is_correct` whether every item was right. Partial
 * credit therefore lives in `score`, and `per_item` says which items were
 * right so the practice screen can mark each one (ACC-02: icon + text, not
 * colour alone).
 *
 * Speaking and writing are not auto scored: `score` and `is_correct` are
 * null and every `per_item` entry is null, leaving the row for the queued AI
 * evaluation to fill (WRITE-04, TEST-08).
 */
final readonly class ScoreResult
{
    /**
     * @param  array<string, bool|null>  $perItem  keyed by item id
     */
    public function __construct(
        public ?int $score,
        public int $maxScore,
        public ?bool $isCorrect,
        public array $perItem,
    ) {}

    /**
     * @param  array<string, bool|null>  $perItem
     */
    public static function graded(array $perItem): self
    {
        $correct = count(array_filter($perItem, static fn (?bool $ok): bool => $ok === true));
        $total = count($perItem);

        return new self(
            score: $correct,
            maxScore: $total,
            isCorrect: $total > 0 && $correct === $total,
            perItem: $perItem,
        );
    }

    /**
     * @param  list<string>  $itemIds
     */
    public static function ungraded(array $itemIds): self
    {
        return new self(
            score: null,
            maxScore: count($itemIds),
            isCorrect: null,
            perItem: array_fill_keys($itemIds, null),
        );
    }

    public function isAutoScored(): bool
    {
        return $this->score !== null;
    }

    /**
     * 0–100, or null when not auto scored or empty.
     */
    public function percent(): ?float
    {
        if ($this->score === null || $this->maxScore === 0) {
            return null;
        }

        return round($this->score / $this->maxScore * 100, 2);
    }

    /**
     * The columns an attempts row stores (spec 0003 B.6).
     *
     * @return array{score: int|null, max_score: int, is_correct: bool|null}
     */
    public function toAttemptColumns(): array
    {
        return [
            'score' => $this->score,
            'max_score' => $this->maxScore,
            'is_correct' => $this->isCorrect,
        ];
    }
}
