<?php

namespace App\Services\Reports\Exporters;

use App\Enums\Permission;
use App\Models\AiScenario;
use App\Models\RoleplayAttempt;
use App\Services\Reports\ReportRows;
use Illuminate\Database\Eloquent\Builder;

/**
 * One row per role-play attempt (RP-11, DATA-05, AIE-04, REP-08).
 *
 * The criteria scores flatten into the five default criterion columns
 * plus a JSON column for a scenario's own extra criteria. The transcript
 * column exists only for a viewer holding `transcripts.view`: a manager's
 * sheet has neither the column nor the data (ROLE-04, PRIV-04).
 */
final class RoleplayExport extends DatasetExport
{
    public function key(): string
    {
        return self::DATASET_ROLEPLAY;
    }

    public function title(): string
    {
        return __('AI Role-play Logs');
    }

    public function includesTranscripts(): bool
    {
        return $this->viewer->can(Permission::TranscriptsView->value);
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        $headers = [
            'Attempt ID', 'Participant code', 'Employee', 'Username', 'Hotel', 'Department', 'Scenario', 'Attempt no',
            'Status', 'Overall score', 'Score overridden', 'Original overall score', 'Override reason', 'Grading level',
        ];

        foreach (self::criteriaKeys() as $key) {
            $headers[] = ucfirst(str_replace('_', ' ', $key));
        }

        $headers = [
            ...$headers,
            'Other criteria (JSON)', 'Summary', 'Summary text', 'Turns', 'Duration (ms)', 'Started at', 'Ended at',
        ];

        if ($this->includesTranscripts()) {
            $headers[] = 'Feedback (JSON)';
            $headers[] = 'Transcript (JSON)';
        }

        return $headers;
    }

    /**
     * @return iterable<int, list<mixed>>
     */
    public function rows(): iterable
    {
        $withTranscript = $this->includesTranscripts();
        $query = $this->query()->reorder();

        /** @var RoleplayAttempt $attempt */
        foreach ($query->lazyById(self::CHUNK, 'roleplay_attempts.id', 'id') as $attempt) {
            $scores = $attempt->criteria_scores ?? [];
            $feedback = $attempt->feedback ?? [];

            $cells = [
                $attempt->id,
                $attempt->user?->participant_code,
                $attempt->user?->name,
                $attempt->user?->username,
                $attempt->user?->hotel?->name,
                $attempt->user?->department?->name,
                $attempt->scenario?->title,
                $attempt->attempt_no,
                $attempt->status->value,
                $attempt->overall_score,
                // An admin override keeps the AI's value beside it (AIE-05).
                $attempt->isScoreOverridden(),
                $attempt->original_overall_score,
                $attempt->score_override_reason,
                // The learner's level the AI judged against (spec 0005 §5.2).
                $attempt->graded_level?->value,
            ];

            foreach (self::criteriaKeys() as $key) {
                $cells[] = $scores[$key] ?? null;
            }

            $others = array_diff_key($scores, array_flip(self::criteriaKeys()));

            $cells = [
                ...$cells,
                $others === [] ? null : $others,
                $feedback['summary_label'] ?? null,
                $feedback['summary_text'] ?? null,
                $attempt->turnsCount(),
                $attempt->duration_ms,
                $attempt->started_at,
                $attempt->ended_at,
            ];

            if ($withTranscript) {
                $cells[] = $feedback === [] ? null : $feedback;
                $cells[] = $attempt->transcript ?? [];
            }

            yield $cells;
        }
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * @return list<string>
     */
    private static function criteriaKeys(): array
    {
        return array_map(
            static fn (array $criterion): string => $criterion['key'],
            AiScenario::defaultFeedbackCriteria(),
        );
    }

    /**
     * @return Builder<RoleplayAttempt>
     */
    private function query(): Builder
    {
        return (new ReportRows($this->filters, $this->population, $this->viewer))->roleplayQuery();
    }
}
