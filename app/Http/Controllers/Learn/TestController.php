<?php

namespace App\Http\Controllers\Learn;

use App\Enums\ResultsVisibility;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\AnswerTestRequest;
use App\Http\Requests\Learn\FinishTestRequest;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\ActivityPresenter;
use App\Services\Learning\JourneyService;
use App\Services\Learning\TestRunner;
use App\Services\Learning\TestScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Pre-test and Post-test runner (TEST-01..10, TIME-01..05, CTRL-04,
 * JOURNEY-01/04/05; spec 0003 Part E).
 *
 * The sitting is server-timed (TIME-05) and every answer is one `attempts`
 * row stored verbatim with its elapsed time (TEST-06, DATA-01, DATA-08). No
 * Arabic and no correct answer ever leave the server on a test page — the
 * presenter strips both in test mode (CTRL-04, TEST-03).
 */
class TestController extends Controller
{
    public function __construct(
        private readonly TestRunner $runner,
        private readonly TestScorer $scorer,
        private readonly ActivityPresenter $presenter,
        private readonly JourneyService $journey,
    ) {}

    public function start(Request $request, Test $test): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertSittable($user, $test);

        $attempt = $this->runner->startOrResume($user, $test);

        return $this->toQuestion($test, $attempt, 1);
    }

    public function question(Request $request, Test $test, TestAttempt $attempt, int $number): Response|RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $test, $attempt);

        if ($this->finishIfExpired($attempt) || $attempt->status !== TestAttemptStatus::InProgress) {
            return $this->toResult($test, $attempt);
        }

        $questions = $this->runner->questions($test);
        $total = $questions->count();
        $number = max(1, min($number, max($total, 1)));
        $placement = $this->runner->questionAt($test, $number);
        $saved = $this->runner->savedAnswers($attempt, $test);

        return Inertia::render('employee/test/Question', [
            'test' => [
                'id' => $test->id,
                'type' => $test->type->value,
                'title' => $test->title,
                'label' => $test->type === TestType::Post ? __('Post-test') : __('Pre-test'),
            ],
            'attempt' => [
                'id' => $attempt->id,
                'deadlineAt' => $attempt->deadline_at?->toIso8601String(),
                'remainingSeconds' => $this->remaining($attempt),
                'onTimeout' => $test->onTimeout(),
            ],
            'question' => ['number' => $number, 'total' => $total],
            'activity' => $this->presenter->present($placement, $user, ActivityPresenter::MODE_TEST),
            'savedAnswer' => $saved[$number] ?? null,
            'questions' => $questions->values()->map(fn (object $placement, int $index): array => [
                'number' => $index + 1,
                'answered' => array_key_exists($index + 1, $saved),
                'url' => route('learn.tests.question', ['test' => $test->id, 'attempt' => $attempt->id, 'number' => $index + 1]),
            ])->all(),
            'answerUrl' => route('learn.tests.answer', ['test' => $test->id, 'attempt' => $attempt->id, 'number' => $number]),
            'finishUrl' => route('learn.tests.finish', ['test' => $test->id, 'attempt' => $attempt->id]),
        ]);
    }

    public function answer(AnswerTestRequest $request, Test $test, TestAttempt $attempt, int $number): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $test, $attempt);

        if ($this->finishIfExpired($attempt)) {
            return $this->toResult($test, $attempt);
        }

        $placement = $this->runner->questionAt($test, $number);
        $this->runner->saveAnswer($attempt, $placement, $request->answer(), $request->timeTakenMs());

        $total = $this->runner->questions($test)->count();

        return $this->toQuestion($test, $attempt, max(1, min($request->target(), max($total, 1))));
    }

    public function finish(FinishTestRequest $request, Test $test, TestAttempt $attempt): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $test, $attempt);

        if ($attempt->status === TestAttemptStatus::InProgress) {
            $placement = $this->runner->questionAt($test, $request->number());
            $this->runner->saveAnswer($attempt, $placement, $request->answer(), $request->timeTakenMs());
            $this->scorer->finish($attempt);
        }

        return $this->toResult($test, $attempt);
    }

    public function result(Request $request, Test $test, TestAttempt $attempt): Response|RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $test, $attempt);

        if ($attempt->status === TestAttemptStatus::InProgress) {
            return $this->toQuestion($test, $attempt, 1);
        }

        $visibility = $test->resultsVisibility();
        $isPost = $test->type === TestType::Post;

        return Inertia::render('employee/test/Result', [
            'test' => [
                'id' => $test->id,
                'type' => $test->type->value,
                'title' => $test->title,
                'label' => $isPost ? __('Post-test') : __('Pre-test'),
            ],
            'visibility' => $visibility->value,
            'result' => $this->resultPayload($attempt, $visibility),
            'isPost' => $isPost,
            'continueUrl' => route('learn.lessons'),
            'certificateUrl' => route('learn.certificate'),
            'certificateAvailable' => $isPost && $this->journey->certificateAvailable($user),
            'journey' => $this->journey->summary($user),
        ]);
    }

    /**
     * @return array{score: float, maxScore: float, percent: int, breakdown?: list<array{skill: string, score: float, max: float, percent: int}>}|null
     */
    private function resultPayload(TestAttempt $attempt, ResultsVisibility $visibility): ?array
    {
        if ($visibility === ResultsVisibility::Hidden) {
            return null;
        }

        $score = (float) $attempt->score;
        $max = (float) $attempt->max_score;

        $payload = [
            'score' => $score,
            'maxScore' => $max,
            'percent' => $max > 0.0 ? (int) round($score / $max * 100) : 0,
        ];

        if ($visibility !== ResultsVisibility::ScoreBreakdown) {
            return $payload;
        }

        /** @var array<string, array{score?: float, max?: float}> $breakdown */
        $breakdown = is_array($attempt->breakdown) ? $attempt->breakdown : [];
        $payload['breakdown'] = [];

        foreach ($breakdown as $skill => $band) {
            $bandScore = (float) ($band['score'] ?? 0);
            $bandMax = (float) ($band['max'] ?? 0);
            $payload['breakdown'][] = [
                'skill' => $skill,
                'score' => $bandScore,
                'max' => $bandMax,
                'percent' => $bandMax > 0.0 ? (int) round($bandScore / $bandMax * 100) : 0,
            ];
        }

        return $payload;
    }

    private function learner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function assertSittable(User $user, Test $test): void
    {
        abort_unless(Test::query()->forLearner($user)->whereKey($test->id)->exists(), 403);

        if ($test->type === TestType::Post) {
            abort_unless($this->journey->postTestUnlocked($user), 403);
        }
    }

    private function assertOwned(User $user, Test $test, TestAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $user->id && $attempt->test_id === $test->id, 403);
    }

    private function finishIfExpired(TestAttempt $attempt): bool
    {
        if ($this->runner->isExpired($attempt)) {
            $this->scorer->finish($attempt, TestAttemptStatus::Expired);

            return true;
        }

        return false;
    }

    private function remaining(TestAttempt $attempt): ?int
    {
        if ($attempt->deadline_at === null) {
            return null;
        }

        return max(0, $attempt->deadline_at->getTimestamp() - Date::now()->getTimestamp());
    }

    private function toQuestion(Test $test, TestAttempt $attempt, int $number): RedirectResponse
    {
        return to_route('learn.tests.question', ['test' => $test->id, 'attempt' => $attempt->id, 'number' => $number]);
    }

    private function toResult(Test $test, TestAttempt $attempt): RedirectResponse
    {
        return to_route('learn.tests.result', ['test' => $test->id, 'attempt' => $attempt->id]);
    }
}
