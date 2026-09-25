<?php

namespace App\Services\Reports;

use App\Enums\Permission;
use App\Models\Attempt;
use App\Models\LessonCompletion;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The rows of each results tab, paginated (REP-02, REP-04, DATA-01..05,
 * ROLE-04; spec 0003 Part D).
 *
 * Every tab reads the same population and the same date range. Event
 * tabs (answers, role-play logs, lesson progress, comparison) are bounded
 * by the range; the Employee Results tab is state as of now. A role-play
 * transcript is shaped into a row only for a viewer holding
 * `transcripts.view`: a manager's payload never carries it, so nothing the
 * UI merely hides could leak (ROLE-04, PRIV-04).
 */
final class ReportRows
{
    public const DATE_FORMAT = 'd M Y';

    public const DATETIME_FORMAT = 'd M Y H:i';

    public function __construct(
        private readonly ReportFilters $filters,
        private readonly ReportPopulation $population,
        private readonly User $viewer,
    ) {}

    /**
     * @return array{results: array{tab: string, rows: list<array<string, mixed>>}, pagination: array<string, mixed>}
     */
    public function build(): array
    {
        $result = match ($this->filters->tab) {
            ReportFilters::TAB_ANSWERS => $this->answers(),
            ReportFilters::TAB_ROLEPLAY => $this->roleplay(),
            ReportFilters::TAB_LESSONS => $this->lessons(),
            ReportFilters::TAB_COMPARISON => $this->comparison(),
            ReportFilters::TAB_DOWNLOADS => ['rows' => [], 'pagination' => $this->pagination(null)],
            default => $this->employees(),
        };

        return [
            'results' => ['tab' => $this->filters->tab, 'rows' => $result['rows']],
            'pagination' => $result['pagination'],
        ];
    }

    public function canViewTranscripts(): bool
    {
        return $this->viewer->can(Permission::TranscriptsView->value);
    }

    // ------------------------------------------------------------ employees

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function employees(): array
    {
        $page = $this->population->searched()
            ->with(['department:id,name'])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate($this->filters->perPage, ['users.*'], 'page', $this->filters->page);

        $from = (int) ($page->firstItem() ?? 0);
        $rows = [];

        /** @var User $user */
        foreach ($page->items() as $index => $user) {
            $rows[] = self::employeeRow($user, $from + $index);
        }

        return ['rows' => $rows, 'pagination' => $this->pagination($page)];
    }

    /**
     * One Employee Results row, in the shape resources/js/types/reports.ts
     * calls ReportEmployeeRow.
     *
     * @return array<string, mixed>
     */
    public static function employeeRow(User $user, int $rank): array
    {
        $figures = ReportPopulation::figures($user);

        return [
            'id' => $user->id,
            'rank' => $rank,
            'initials' => self::initials($user->name),
            'name' => $user->name,
            'department' => $user->department->name ?? '—',
            'preScore' => $figures['preScore'],
            'postScore' => $figures['postScore'],
            'lessonsCompleted' => $figures['lessonsCompleted'],
            'lessonsTotal' => $figures['lessonsTotal'],
            'scenariosCompleted' => $figures['scenariosCompleted'],
            'scenariosTotal' => $figures['scenariosTotal'],
            'lastActivity' => self::date($user->last_activity_at),
            'status' => ReportPopulation::statusOf($user),
            'statusLabel' => ReportPopulation::statusLabel($user),
        ];
    }

    // -------------------------------------------------------------- answers

    /**
     * One row per answer: the research row (TEST-06, DATA-01, DATA-02,
     * DATA-03, DATA-08, DATA-11).
     *
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function answers(): array
    {
        $page = $this->answersQuery()
            ->orderByDesc('attempts.submitted_at')
            ->orderByDesc('attempts.id')
            ->paginate($this->filters->perPage, ['attempts.*'], 'page', $this->filters->page);

        $rows = [];
        $withTranscript = $this->canViewTranscripts();

        /** @var Attempt $attempt */
        foreach ($page->items() as $attempt) {
            $rows[] = self::answerRow($attempt, $withTranscript);
        }

        return ['rows' => $rows, 'pagination' => $this->pagination($page)];
    }

    /**
     * Submitted answers of the population within the range, honouring the
     * activity-type filter (tests, lessons, or role-play which has none).
     *
     * @return Builder<Attempt>
     */
    public function answersQuery(bool $searched = true): Builder
    {
        $query = Attempt::query()
            ->with([
                'user:id,name,username,email,participant_code,hotel_id,department_id',
                'user.department:id,name',
                'user.hotel:id,name',
                'activity:id,type,skill_label,title,prompt',
                'activityVersion:id,activity_id,version,payload',
                'testAttempt:id,test_id,attempt_no',
                'testAttempt.test:id,type,title',
                'lesson:id,title',
                'responseMedia:id,disk,path,mime',
            ])
            ->whereIn('attempts.user_id', $this->population->ids())
            ->whereNotNull('attempts.submitted_at');

        $this->filters->withinRange($query, 'attempts.submitted_at');

        if (! $this->filters->includesTests() && ! $this->filters->includesLessons()) {
            $query->whereRaw('1 = 0');
        } elseif (! $this->filters->includesTests()) {
            $query->practice();
        } elseif (! $this->filters->includesLessons()) {
            $query->inTests();
        }

        if ($searched && $this->filters->search !== '') {
            $like = '%'.$this->filters->search.'%';

            $query->where(function (Builder $where) use ($like): void {
                $where
                    ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $like))
                    ->orWhereHas('activity', function (Builder $activity) use ($like): void {
                        $activity->where('skill_label', 'like', $like)->orWhere('title', 'like', $like);
                    })
                    ->orWhereHas('lesson', fn (Builder $lesson) => $lesson->where('title', 'like', $like))
                    ->orWhereHas('testAttempt.test', fn (Builder $test) => $test->where('title', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * A spoken answer's transcript and recording reach only a viewer holding
     * `transcripts.view`; a Hotel Admin sees the label and the score, and the
     * recording link is left out of the payload (ROLE-04, PRIV-04, REP-08).
     *
     * @return array<string, mixed>
     */
    public static function answerRow(Attempt $attempt, bool $withTranscript): array
    {
        $activity = $attempt->activity;
        $type = $activity?->type;
        [$item, $raw] = AnswerFormatter::itemAndAnswer($attempt);

        $media = $attempt->responseMedia;

        return [
            'id' => $attempt->id,
            'employee' => $attempt->user->name ?? '—',
            'department' => $attempt->user->department->name ?? '—',
            'context' => self::answerContext($attempt),
            'skill' => $activity->skill_label ?? $type?->label() ?? '—',
            'question' => $type === null ? ($activity->prompt ?? '') : AnswerFormatter::question($type, $item, $activity->prompt ?? ''),
            'answer' => $type === null ? '' : AnswerFormatter::answer($type, $item, $raw, $attempt->transcript, $withTranscript),
            'isCorrect' => $attempt->is_correct,
            'score' => $attempt->score === null ? null : (float) $attempt->score,
            'maxScore' => $attempt->max_score === null ? null : (float) $attempt->max_score,
            'overridden' => $attempt->isScoreOverridden(),
            // The adjust dialog (AIE-05; spec 0005 §2.5): the machine's value
            // beside an override, why it changed, and whether the AI grades
            // this answer (and is grading it right now).
            'originalScore' => $attempt->original_score === null ? null : (float) $attempt->original_score,
            'overrideReason' => $attempt->score_override_reason,
            'aiGraded' => ScoreOverrides::isAiGraded($type),
            'aiStatus' => $attempt->ai_status?->value,
            'timeTakenMs' => $attempt->time_taken_ms,
            'version' => $attempt->activityVersion->version ?? 0,
            'submittedAt' => self::dateTime($attempt->submitted_at),
            // A spoken answer plays through the authorized serve route,
            // never a guessable file URL (TEST-07, DATA-02, PRIV-04).
            'audioUrl' => $media === null || ! $withTranscript ? null : route('media.show', $media),
        ];
    }

    /**
     * Where the answer was given: the test sitting or the lesson.
     */
    public static function answerContext(Attempt $attempt): string
    {
        $test = $attempt->testAttempt?->test;

        if ($test !== null) {
            return $test->title;
        }

        return $attempt->lesson->title ?? __('Practice');
    }

    // ------------------------------------------------------------- roleplay

    /**
     * One row per counted role-play attempt (RP-11, DATA-05, RP-13).
     *
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function roleplay(): array
    {
        $page = $this->roleplayQuery()
            ->orderByDesc('roleplay_attempts.started_at')
            ->orderByDesc('roleplay_attempts.id')
            ->paginate($this->filters->perPage, ['roleplay_attempts.*'], 'page', $this->filters->page);

        $rows = [];
        $withTranscript = $this->canViewTranscripts();

        /** @var RoleplayAttempt $attempt */
        foreach ($page->items() as $attempt) {
            $rows[] = self::roleplayRow($attempt, $withTranscript);
        }

        return ['rows' => $rows, 'pagination' => $this->pagination($page)];
    }

    /**
     * @return Builder<RoleplayAttempt>
     */
    public function roleplayQuery(bool $searched = true): Builder
    {
        $query = RoleplayAttempt::query()
            ->counted()
            ->with([
                'user:id,name,username,email,participant_code,hotel_id,department_id',
                'user.department:id,name',
                'user.hotel:id,name',
                'scenario:id,title,feedback_criteria',
            ])
            ->whereIn('roleplay_attempts.user_id', $this->population->ids());

        $this->filters->withinRange($query, 'roleplay_attempts.started_at');

        if (! $this->filters->includesRoleplay()) {
            $query->whereRaw('1 = 0');
        }

        if ($searched && $this->filters->search !== '') {
            $like = '%'.$this->filters->search.'%';

            $query->where(function (Builder $where) use ($like): void {
                $where
                    ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $like))
                    ->orWhereHas('scenario', fn (Builder $scenario) => $scenario->where('title', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public static function roleplayRow(RoleplayAttempt $attempt, bool $withTranscript): array
    {
        $scenario = $attempt->scenario;
        $criteria = [];

        foreach ($scenario?->criteria() ?? [] as $criterion) {
            $criteria[] = [
                'key' => $criterion['key'],
                'label' => $criterion['label'],
                'score' => $attempt->criteria_scores[$criterion['key']] ?? null,
            ];
        }

        $feedback = $attempt->feedback ?? [];

        return [
            'id' => $attempt->id,
            'employee' => $attempt->user->name ?? '—',
            'department' => $attempt->user->department->name ?? '—',
            'scenario' => $scenario->title ?? '—',
            'attemptNo' => $attempt->attempt_no,
            'status' => $attempt->status->value,
            'statusLabel' => self::roleplayStatusLabel($attempt),
            'overallScore' => $attempt->overall_score,
            'overridden' => $attempt->isScoreOverridden(),
            'originalScore' => $attempt->original_overall_score,
            'overrideReason' => $attempt->score_override_reason,
            'aiStatus' => $attempt->ai_status?->value,
            'criteria' => $criteria,
            'summary' => is_string($feedback['summary_label'] ?? null) ? $feedback['summary_label'] : null,
            'turns' => $attempt->turnsCount(),
            'durationMs' => $attempt->duration_ms,
            'startedAt' => self::dateTime($attempt->started_at),
            // Present only for a viewer who may read transcripts (ROLE-04).
            'transcript' => $withTranscript ? ($attempt->transcript ?? []) : null,
            'feedback' => $withTranscript ? $feedback : null,
        ];
    }

    private static function roleplayStatusLabel(RoleplayAttempt $attempt): string
    {
        return match ($attempt->status->value) {
            'completed' => __('Completed'),
            'evaluating' => __('Evaluating'),
            'abandoned' => __('Abandoned'),
            default => __('In progress'),
        };
    }

    // -------------------------------------------------------------- lessons

    /**
     * One row per employee per completed lesson (PROG-02, DATA-07).
     *
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function lessons(): array
    {
        $page = $this->lessonsQuery()
            ->orderByDesc('lesson_completions.completed_at')
            ->orderByDesc('lesson_completions.id')
            ->paginate($this->filters->perPage, ['lesson_completions.*'], 'page', $this->filters->page);

        $rows = [];

        /** @var LessonCompletion $completion */
        foreach ($page->items() as $completion) {
            $rows[] = self::lessonRow($completion);
        }

        return ['rows' => $rows, 'pagination' => $this->pagination($page)];
    }

    /**
     * @return Builder<LessonCompletion>
     */
    public function lessonsQuery(bool $searched = true): Builder
    {
        $query = LessonCompletion::query()
            ->with([
                'user:id,name,username,email,participant_code,hotel_id,department_id',
                'user.department:id,name',
                'user.hotel:id,name',
                'lesson:id,title,course_id',
                'lesson.course:id,title',
            ])
            ->whereIn('lesson_completions.user_id', $this->population->ids());

        $this->filters->withinRange($query, 'lesson_completions.completed_at');

        if (! $this->filters->includesLessons()) {
            $query->whereRaw('1 = 0');
        }

        if ($searched && $this->filters->search !== '') {
            $like = '%'.$this->filters->search.'%';

            $query->where(function (Builder $where) use ($like): void {
                $where
                    ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $like))
                    ->orWhereHas('lesson', fn (Builder $lesson) => $lesson->where('title', 'like', $like))
                    ->orWhereHas('lesson.course', fn (Builder $course) => $course->where('title', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public static function lessonRow(LessonCompletion $completion): array
    {
        return [
            'id' => $completion->id,
            'employee' => $completion->user->name ?? '—',
            'department' => $completion->user->department->name ?? '—',
            'course' => $completion->lesson->course->title ?? '—',
            'lesson' => $completion->lesson->title ?? '—',
            'completedAt' => self::dateTime($completion->completed_at),
        ];
    }

    // ----------------------------------------------------------- comparison

    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function comparison(): array
    {
        /** @var list<array<string, mixed>> $all */
        $all = (new ComparisonBuilder($this->filters, $this->population))->rows();
        $offset = ($this->filters->page - 1) * $this->filters->perPage;
        $slice = array_slice($all, $offset, $this->filters->perPage);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $page */
        $page = new LengthAwarePaginator($slice, count($all), $this->filters->perPage, $this->filters->page);

        return ['rows' => $slice, 'pagination' => $this->pagination($page)];
    }

    // ------------------------------------------------------------ paginator

    /**
     * @template TItem
     *
     * @param  PaginatorContract<int, TItem>|null  $page
     * @return array{from: int, to: int, total: int, currentPage: int, lastPage: int, pages: list<int|string>, perPageOptions: list<int>, currentPerPage: int}
     */
    private function pagination(?PaginatorContract $page): array
    {
        if ($page === null) {
            return [
                'from' => 0,
                'to' => 0,
                'total' => 0,
                'currentPage' => 1,
                'lastPage' => 1,
                'pages' => [1],
                'perPageOptions' => ReportFilters::PER_PAGE_OPTIONS,
                'currentPerPage' => $this->filters->perPage,
            ];
        }

        return [
            'from' => (int) ($page->firstItem() ?? 0),
            'to' => (int) ($page->lastItem() ?? 0),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'pages' => self::pageWindow($page->currentPage(), $page->lastPage()),
            'perPageOptions' => ReportFilters::PER_PAGE_OPTIONS,
            'currentPerPage' => $this->filters->perPage,
        ];
    }

    /**
     * 1 … current-1 current current+1 … last, with the ends always present.
     *
     * @return list<int|string>
     */
    public static function pageWindow(int $current, int $last): array
    {
        if ($last <= 7) {
            return range(1, max(1, $last));
        }

        $pages = [1];

        if ($current > 3) {
            $pages[] = 'ellipsis';
        }

        foreach (range(max(2, $current - 1), min($last - 1, $current + 1)) as $page) {
            $pages[] = $page;
        }

        if ($current < $last - 2) {
            $pages[] = 'ellipsis';
        }

        $pages[] = $last;

        return $pages;
    }

    // ---------------------------------------------------------------- helpers

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $letters .= Str::upper(Str::substr($word, 0, 1));
        }

        return $letters === '' ? '?' : $letters;
    }

    public static function date(?CarbonInterface $date): string
    {
        return $date === null ? '—' : $date->format(self::DATE_FORMAT);
    }

    public static function dateTime(?CarbonInterface $date): string
    {
        return $date === null ? '—' : $date->format(self::DATETIME_FORMAT);
    }
}
