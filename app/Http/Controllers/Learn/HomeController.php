<?php

namespace App\Http\Controllers\Learn;

use App\Enums\MediaLibrary;
use App\Enums\TestAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\JourneyService;
use App\Services\Learning\LessonNavigator;
use App\Services\Learning\PayloadResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee home (JOURNEY-01, JOURNEY-02, PROG-05; spec 0003 Part E).
 *
 * Before the Pre-test is submitted the page is the Pre-test intro card
 * (photo_20); afterwards the same card shows "Continue where you left off".
 * Both come from the same props: `test` for the intro, `journey` (shared)
 * plus `continueLesson` for the resume card.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly JourneyService $journey,
        private readonly LessonNavigator $navigator,
        private readonly PayloadResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $test = $this->journey->preTest($user);
        $target = $this->navigator->continueTarget($user);

        return Inertia::render('employee/Home', [
            'test' => $test === null ? null : $this->intro($test),
            'attemptInProgress' => $test === null ? null : $this->attemptInProgress($user, $test),
            'continueLesson' => $target === null ? null : [
                'id' => $target['lesson']->id,
                'title' => $target['lesson']->title,
                'stepLabel' => $target['block']->type->stepLabel(),
                'url' => $this->navigator->stepUrl($target['lesson'], $target['block']),
            ],
            'journey' => $this->journey->summary($user),
            // The client's receptionist photo from the seed manifest
            // (spec 0003 F `home-receptionist`, photo_20); null until the
            // asset lane has shipped it, never a placeholder.
            'photo' => $this->resolver->media(
                MediaAsset::query()->inLibrary(MediaLibrary::Seed)->where('label', 'home-receptionist')->first(),
            ),
        ]);
    }

    /**
     * The Pre-test intro card (spec 0003 G.4): the stored `intro` json plus
     * what the runner needs to start it.
     *
     * @return array<string, mixed>
     */
    private function intro(Test $test): array
    {
        return [
            'id' => $test->id,
            'type' => $test->type->value,
            'title' => $test->title,
            'intro' => $test->intro ?? [],
            'timeLimitSeconds' => $test->timeLimitSeconds(),
            'questionCount' => $test->questions()->count(),
            'startUrl' => route('learn.tests.start', ['test' => $test]),
        ];
    }

    /**
     * An open sitting to resume, pointed at its first unanswered question,
     * so a dropped connection never restarts a test (AUTH-09, TIME-05).
     *
     * @return array{id: int, url: string, remainingSeconds: int|null}|null
     */
    private function attemptInProgress(User $user, Test $test): ?array
    {
        $attempt = TestAttempt::query()
            ->where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', TestAttemptStatus::InProgress->value)
            ->latest('id')
            ->first();

        if ($attempt === null) {
            return null;
        }

        $questionCount = max(1, $test->questions()->count());
        $answered = $attempt->answers()->count();
        $number = min($questionCount, $answered + 1);

        return [
            'id' => $attempt->id,
            'url' => route('learn.tests.question', ['test' => $test, 'attempt' => $attempt, 'number' => $number]),
            'remainingSeconds' => $attempt->remainingSeconds(),
        ];
    }
}
