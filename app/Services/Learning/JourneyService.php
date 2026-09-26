<?php

namespace App\Services\Learning;

use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee journey's two gates and the figures around them (JOURNEY-01,
 * JOURNEY-04, JOURNEY-05, PROG-02, PROG-05; spec 0003 Part E).
 *
 * Every number here is computed from rows on the way out: submitted test
 * attempts, lesson completions and the learner's own curriculum through the
 * forLearner scopes. Nothing is cached on the user, so a department change
 * or an unpublished lesson is reflected at once.
 *
 * Gate 2 (the Post-test) opens when every lesson is complete. That condition
 * is meant to become admin configuration (JOURNEY-04); it is written once
 * here so the change is one method.
 */
class JourneyService
{
    public function __construct(private readonly LessonNavigator $navigator) {}

    /**
     * The shared `journey` prop (spec 0003 Part E).
     *
     * @return array{preTestSubmitted: bool, lessonsUnlocked: bool, lessonsCompleted: int, lessonsTotal: int, postTestUnlocked: bool, certificateAvailable: bool, continueUrl: string|null}
     */
    public function summary(User $user): array
    {
        // One HTTP request asks for the summary several times (the shared
        // `journey` prop, the page, the coach): compute it once per request.
        // Kept on the request itself, so the next request, a test request or
        // a queued job always reads fresh rows (spec 0003 Part E).
        $request = request();
        $key = 'journey.summary.'.$user->id;

        if ($request->route() !== null && $request->attributes->has($key)) {
            /** @var array{preTestSubmitted: bool, lessonsUnlocked: bool, lessonsCompleted: int, lessonsTotal: int, postTestUnlocked: bool, certificateAvailable: bool, continueUrl: string|null} $cached */
            $cached = $request->attributes->get($key);

            return $cached;
        }

        $summary = $this->computeSummary($user);

        if ($request->route() !== null) {
            $request->attributes->set($key, $summary);
        }

        return $summary;
    }

    /**
     * Drop this request's summary after progress changed within it.
     */
    public function forget(User $user): void
    {
        request()->attributes->remove('journey.summary.'.$user->id);
    }

    /**
     * @return array{preTestSubmitted: bool, lessonsUnlocked: bool, lessonsCompleted: int, lessonsTotal: int, postTestUnlocked: bool, certificateAvailable: bool, continueUrl: string|null}
     */
    private function computeSummary(User $user): array
    {
        $preTestSubmitted = $this->preTestSubmitted($user);
        $lessonsUnlocked = $preTestSubmitted || ! $user->hasPreTestToSit();
        $total = $this->lessonsTotal($user);
        $completed = $this->lessonsCompleted($user);
        $postTestUnlocked = $this->postTestUnlockedFrom($lessonsUnlocked, $completed, $total);

        return [
            'preTestSubmitted' => $preTestSubmitted,
            'lessonsUnlocked' => $lessonsUnlocked,
            'lessonsCompleted' => $completed,
            'lessonsTotal' => $total,
            'postTestUnlocked' => $postTestUnlocked,
            'certificateAvailable' => $this->certificateAvailable($user),
            'continueUrl' => $lessonsUnlocked ? $this->navigator->continueUrl($user) : null,
        ];
    }

    /**
     * Gate 1 as the lessons read it (JOURNEY-01): the Pre-test is submitted,
     * or no published Pre-test exists for this learner (client decision
     * 2026-09-23: the gate applies only when there is a Pre-test to sit).
     */
    public function lessonsUnlocked(User $user): bool
    {
        return $user->lessonsUnlocked();
    }

    /**
     * The Pre-test this learner sits, if one is published for them.
     */
    public function preTest(User $user): ?Test
    {
        // The learner's own hotel's test wins over a shared one (ORG-04).
        return Test::query()->forLearner($user)->ofType(TestType::Pre)
            ->orderByRaw('CASE WHEN hotel_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->first();
    }

    /**
     * The Post-test this learner sits, if one is published for them.
     */
    public function postTest(User $user): ?Test
    {
        return Test::query()->forLearner($user)->ofType(TestType::Post)
            ->orderByRaw('CASE WHEN hotel_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->first();
    }

    /**
     * Gate 1 (JOURNEY-01).
     */
    public function preTestSubmitted(User $user): bool
    {
        return $user->hasSubmittedPreTest();
    }

    /**
     * Has a Post-test they were entitled to sit been submitted?
     */
    public function postTestSubmitted(User $user): bool
    {
        return $user->testAttempts()
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereHas('test', function (Builder $test) use ($user): void {
                /** @var Builder<Test> $test */
                $test->ofType(TestType::Post)->forLearner($user);
            })
            ->exists();
    }

    /**
     * Published lessons in the learner's curriculum.
     */
    public function lessonsTotal(User $user): int
    {
        return Lesson::query()->forLearner($user)->count();
    }

    /**
     * Completed lessons that are still in the learner's curriculum, so a
     * lesson that was unpublished or moved never inflates the count.
     */
    public function lessonsCompleted(User $user): int
    {
        return $user->lessonCompletions()
            ->whereIn('lesson_id', Lesson::query()->forLearner($user)->select('lessons.id'))
            ->count();
    }

    /**
     * Gate 2 (JOURNEY-04): gate 1 is passed (the Pre-test is done, or there
     * is none to sit) and every lesson is complete.
     */
    public function postTestUnlocked(User $user): bool
    {
        return $this->postTestUnlockedFrom(
            $this->lessonsUnlocked($user),
            $this->lessonsCompleted($user),
            $this->lessonsTotal($user),
        );
    }

    /**
     * The certificate condition (JOURNEY-05): a submitted Post-test. Whether
     * it is a completion or a participation certificate is decided when it
     * is issued (CERT-02, CERT-04).
     */
    public function certificateAvailable(User $user): bool
    {
        return $this->postTestSubmitted($user);
    }

    /**
     * Completed lessons over the curriculum, as a whole percentage; 0 with
     * no lessons (the `{{progress}}` reminder variable, DATA-07).
     */
    public function progressPercent(User $user): int
    {
        $total = $this->lessonsTotal($user);

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->lessonsCompleted($user) / $total * 100);
    }

    private function postTestUnlockedFrom(bool $lessonsUnlocked, int $completed, int $total): bool
    {
        return $lessonsUnlocked && $total > 0 && $completed >= $total;
    }
}
