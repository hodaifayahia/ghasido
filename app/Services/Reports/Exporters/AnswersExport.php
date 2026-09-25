<?php

namespace App\Services\Reports\Exporters;

use App\Models\Attempt;
use App\Services\Reports\AnswerFormatter;
use App\Services\Reports\ReportRows;
use Illuminate\Database\Eloquent\Builder;

/**
 * Long format, one row per answer (REP-06, TEST-06, TEST-08, TEST-09,
 * DATA-01..03, DATA-08, DATA-11, AIE-04).
 *
 * The AI evaluation is structured data, so its criteria flatten into
 * columns: the four writing criteria the spec fixes each get a score and
 * a comment column, and anything else lands in the JSON column beside
 * them. The raw answer is exported exactly as stored, next to a readable
 * rendering of it.
 */
class AnswersExport extends DatasetExport
{
    /** @var list<string> The writing criteria of spec 0003 B.9. */
    public const CRITERIA = ['task_completion', 'accuracy', 'politeness', 'clarity'];

    public function key(): string
    {
        return self::DATASET_ANSWERS;
    }

    public function title(): string
    {
        return __('Detailed Answers');
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        $headers = [
            ...$this->identityHeaders(),
            'Hotel', 'Department', 'Context', 'Test or lesson', 'Test sitting ID', 'Activity ID', 'Question version',
            'Activity type', 'Skill', 'Question', 'Raw answer (JSON)', 'Answer', 'Correct', 'Score', 'Max score',
            'Score overridden', 'Original score', 'Transcript',
        ];

        foreach (self::CRITERIA as $criterion) {
            $headers[] = 'AI '.$criterion.' score';
            $headers[] = 'AI '.$criterion.' comment';
        }

        return [
            ...$headers,
            'AI criteria (JSON)', 'AI better answer', 'AI summary',
            'Time taken (ms)', 'Started at', 'Submitted at', 'Recording URL',
        ];
    }

    /**
     * @return iterable<int, list<mixed>>
     */
    public function rows(): iterable
    {
        $query = $this->rowsQuery()->reorder();

        /** @var Attempt $attempt */
        foreach ($query->lazyById(self::CHUNK, 'attempts.id', 'id') as $attempt) {
            yield $this->row($attempt);
        }
    }

    public function count(): int
    {
        return $this->rowsQuery()->count();
    }

    /**
     * @return list<mixed>
     */
    protected function row(Attempt $attempt): array
    {
        $activity = $attempt->activity;
        $type = $activity?->type;
        [$item, $raw] = AnswerFormatter::itemAndAnswer($attempt);
        $feedback = $attempt->ai_feedback ?? [];
        $criteria = is_array($feedback['criteria'] ?? null) ? $feedback['criteria'] : [];
        $media = $attempt->responseMedia;

        $cells = [
            ...$this->identityCells($attempt),
            $attempt->user?->hotel?->name,
            $attempt->user?->department?->name,
            $attempt->isTestAnswer() ? 'test' : 'lesson',
            ReportRows::answerContext($attempt),
            $attempt->test_attempt_id,
            $attempt->activity_id,
            $attempt->activityVersion?->version,
            $type?->value,
            $activity?->skill_label,
            $type === null ? ($activity->prompt ?? '') : AnswerFormatter::question($type, $item, $activity->prompt ?? ''),
            $attempt->raw_answer,
            $type === null ? '' : AnswerFormatter::answer($type, $item, $raw, $attempt->transcript),
            $attempt->is_correct,
            self::decimal($attempt->score),
            self::decimal($attempt->max_score),
            $attempt->isScoreOverridden(),
            self::decimal($attempt->original_score),
            $attempt->transcript,
        ];

        foreach (self::CRITERIA as $criterion) {
            $entry = is_array($criteria[$criterion] ?? null) ? $criteria[$criterion] : [];
            $cells[] = $entry['score'] ?? null;
            $cells[] = $entry['comment'] ?? null;
        }

        $others = array_diff_key($criteria, array_flip(self::CRITERIA));

        return [
            ...$cells,
            $others === [] ? null : $others,
            $feedback['better_answer'] ?? null,
            $feedback['summary'] ?? null,
            $attempt->time_taken_ms,
            $attempt->started_at,
            $attempt->submitted_at,
            $media === null ? null : route('media.show', $media),
        ];
    }

    /**
     * @return list<string>
     */
    protected function identityHeaders(): array
    {
        return ['Answer ID', 'Participant code', 'Employee', 'Username', 'Email'];
    }

    /**
     * @return list<mixed>
     */
    protected function identityCells(Attempt $attempt): array
    {
        return [
            $attempt->id,
            $attempt->user?->participant_code,
            $attempt->user?->name,
            $attempt->user?->username,
            $attempt->user?->email,
        ];
    }

    /**
     * @return Builder<Attempt>
     */
    private function rowsQuery(): Builder
    {
        return (new ReportRows($this->filters, $this->population, $this->viewer))->answersQuery();
    }
}
