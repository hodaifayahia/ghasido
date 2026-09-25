<?php

namespace App\Services\Reports\Exporters;

use App\Services\Reports\ComparisonBuilder;

/**
 * Pre-test versus Post-test per employee per skill (TEST-10, REP-04).
 */
final class ComparisonExport extends DatasetExport
{
    /** @var list<array<string, mixed>>|null */
    private ?array $rows = null;

    public function key(): string
    {
        return self::DATASET_COMPARISON;
    }

    public function title(): string
    {
        return __('Pre/Post Comparison');
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'Participant code', 'Employee', 'Username', 'Hotel', 'Department', 'Skill',
            'Pre-test correct', 'Pre-test total', 'Pre-test %', 'Post-test correct', 'Post-test total', 'Post-test %', 'Change (points)',
        ];
    }

    /**
     * @return iterable<int, list<mixed>>
     */
    public function rows(): iterable
    {
        foreach ($this->all() as $row) {
            yield [
                $row['participantCode'],
                $row['employee'],
                $row['username'],
                $row['hotel'],
                $row['department'],
                $row['skill'],
                $row['preCorrect'],
                $row['preTotal'],
                $row['prePercent'],
                $row['postCorrect'],
                $row['postTotal'],
                $row['postPercent'],
                $row['delta'],
            ];
        }
    }

    public function count(): int
    {
        return count($this->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function all(): array
    {
        return $this->rows ??= (new ComparisonBuilder($this->filters, $this->population))->rows();
    }
}
