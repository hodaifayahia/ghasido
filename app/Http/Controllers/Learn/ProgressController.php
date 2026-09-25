<?php

namespace App\Http\Controllers\Learn;

use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\JourneyService;
use App\Services\Learning\LearnerCoach;
use App\Services\Learning\ProgressService;
use App\Services\Learning\StreakService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My Progress (PROG-02, PROG-05, TEST-04; spec 0003 Part E).
 *
 * Per-course completion plus the two test results, each shown only as far
 * as its test's `results_visibility` allows. Never assume results are shown.
 */
class ProgressController extends Controller
{
    public function __construct(
        private readonly JourneyService $journey,
        private readonly ProgressService $progress,
        private readonly StreakService $streaks,
        private readonly LearnerCoach $coach,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $journey = $this->journey->summary($user);

        return Inertia::render('employee/Progress', [
            'courses' => $this->progress->courseProgress($user),
            'stats' => [
                'lessonsCompleted' => $journey['lessonsCompleted'],
                'lessonsTotal' => $journey['lessonsTotal'],
                'percent' => $this->journey->progressPercent($user),
                'trainingStartedAt' => $user->training_started_at?->toIso8601String(),
                'trainingCompletedAt' => $user->training_completed_at?->toIso8601String(),
                'lastActivityAt' => $user->last_activity_at?->toIso8601String(),
            ],
            'tests' => [
                'pre' => $this->latestResult($user, TestType::Pre),
                'post' => $this->latestResult($user, TestType::Post),
            ],
            'journey' => $journey,
            'streak' => $this->streaks->summary($user),
            'coach' => $this->coach->present($user),
        ]);
    }

    /**
     * The learner's latest submitted sitting of one test type, shaped by the
     * test's own visibility setting (TEST-04).
     *
     * @return array{submittedAt: string|null, percent: int|null, score: float|null, maxScore: float|null, passed: bool|null, visibility: string}|null
     */
    private function latestResult(User $user, TestType $type): ?array
    {
        $attempt = TestAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereHas('test', function (Builder $test) use ($user, $type): void {
                /** @var Builder<Test> $test */
                $test->ofType($type)->forLearner($user);
            })
            ->with('test')
            ->latest('submitted_at')
            ->first();

        if ($attempt === null || $attempt->test === null) {
            return null;
        }

        $visibility = $attempt->test->resultsVisibility();
        $summary = $attempt->scoreSummary();
        $showScore = $visibility->showsScore();

        return [
            'submittedAt' => $attempt->submitted_at?->toIso8601String(),
            'percent' => $showScore ? $summary['percent'] : null,
            'score' => $showScore ? $summary['score'] : null,
            'maxScore' => $showScore ? $summary['max_score'] : null,
            'passed' => $showScore ? $summary['passed'] : null,
            'visibility' => $visibility->value,
        ];
    }
}
