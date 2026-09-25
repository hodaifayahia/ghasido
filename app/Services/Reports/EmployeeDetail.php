<?php

namespace App\Services\Reports;

use App\Enums\TestAttemptStatus;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;

/**
 * The per-employee drill-down behind "View Details" (REP-02, PROG-02,
 * TEST-10, RP-11).
 *
 * The employee is looked up through the population, so a manager can only
 * ever open someone in their own hotel: anyone else resolves to null and
 * the form request has already answered the foreign id with a 403.
 */
final class EmployeeDetail
{
    public function __construct(private readonly ReportPopulation $population) {}

    /**
     * @return array<string, mixed>|null
     */
    public function build(?int $employeeId): ?array
    {
        if ($employeeId === null) {
            return null;
        }

        $user = $this->population->find($employeeId);

        if ($user === null) {
            return null;
        }

        $row = ReportRows::employeeRow($user, 0);

        return [
            ...$row,
            'hotel' => $user->hotel->name ?? '—',
            'username' => $user->username,
            'participantCode' => $user->participant_code,
            'trainingStarted' => ReportRows::date($user->training_started_at),
            'trainingCompleted' => ReportRows::date($user->training_completed_at),
            'lessons' => $this->lessons($user),
            'tests' => $this->tests($user),
            'roleplays' => $this->roleplays($user),
        ];
    }

    /**
     * Every lesson of the employee's curriculum, with its completion date.
     *
     * @return list<array{id: int, title: string, course: string, completedAt: string|null}>
     */
    private function lessons(User $user): array
    {
        $completions = $user->lessonCompletions()->get()->keyBy('lesson_id');

        $lessons = Lesson::query()
            ->forLearner($user)
            ->with('course:id,title,position')
            ->orderBy('lessons.course_id')
            ->orderBy('lessons.position')
            ->orderBy('lessons.id')
            ->get(['lessons.id', 'lessons.title', 'lessons.course_id', 'lessons.position']);

        $rows = [];

        foreach ($lessons as $lesson) {
            $completion = $completions->get($lesson->id);

            $rows[] = [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'course' => $lesson->course->title ?? '—',
                'completedAt' => $completion === null ? null : ReportRows::date($completion->completed_at),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, type: string, title: string, attemptNo: int, percent: int|null, submittedAt: string}>
     */
    private function tests(User $user): array
    {
        $rows = [];

        $attempts = $user->testAttempts()
            ->with('test:id,type,title')
            ->where('status', TestAttemptStatus::Submitted->value)
            ->orderBy('submitted_at')
            ->get();

        /** @var TestAttempt $attempt */
        foreach ($attempts as $attempt) {
            $rows[] = [
                'id' => $attempt->id,
                'type' => $attempt->test->type->value ?? 'pre',
                'title' => $attempt->test->title ?? '—',
                'attemptNo' => $attempt->attempt_no,
                'percent' => $attempt->scoreSummary()['percent'],
                'submittedAt' => ReportRows::dateTime($attempt->submitted_at),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, scenario: string, attemptNo: int, status: string, overallScore: int|null, startedAt: string}>
     */
    private function roleplays(User $user): array
    {
        $rows = [];

        $attempts = $user->roleplayAttempts()
            ->counted()
            ->with('scenario:id,title')
            ->orderByDesc('started_at')
            ->get();

        /** @var RoleplayAttempt $attempt */
        foreach ($attempts as $attempt) {
            $rows[] = [
                'id' => $attempt->id,
                'scenario' => $attempt->scenario->title ?? '—',
                'attemptNo' => $attempt->attempt_no,
                'status' => $attempt->status->value,
                'overallScore' => $attempt->overall_score,
                'startedAt' => ReportRows::dateTime($attempt->started_at),
            ];
        }

        return $rows;
    }
}
