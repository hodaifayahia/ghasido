<?php

namespace App\Services\Reports\Exporters;

use App\Models\LessonCompletion;
use App\Services\Reports\ReportRows;
use Illuminate\Database\Eloquent\Builder;

/**
 * One row per employee per completed lesson (PROG-02, DATA-07, REP-04).
 */
final class LessonProgressExport extends DatasetExport
{
    public function key(): string
    {
        return self::DATASET_LESSONS;
    }

    public function title(): string
    {
        return __('Lesson Progress');
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'Participant code', 'Employee', 'Username', 'Hotel', 'Department', 'Course', 'Lesson', 'Lesson ID', 'Completed at',
        ];
    }

    /**
     * @return iterable<int, list<mixed>>
     */
    public function rows(): iterable
    {
        $query = $this->query()->reorder();

        /** @var LessonCompletion $completion */
        foreach ($query->lazyById(self::CHUNK, 'lesson_completions.id', 'id') as $completion) {
            yield [
                $completion->user?->participant_code,
                $completion->user?->name,
                $completion->user?->username,
                $completion->user?->hotel?->name,
                $completion->user?->department?->name,
                $completion->lesson?->course?->title,
                $completion->lesson?->title,
                $completion->lesson_id,
                $completion->completed_at,
            ];
        }
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * @return Builder<LessonCompletion>
     */
    private function query(): Builder
    {
        return (new ReportRows($this->filters, $this->population, $this->viewer))->lessonsQuery();
    }
}
