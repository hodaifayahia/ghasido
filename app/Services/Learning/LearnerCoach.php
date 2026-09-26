<?php

namespace App\Services\Learning;

use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Jobs\GenerateLearnerCoaching;
use App\Models\AiInsight;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * The learner's AI coach (spec 0005 §3.5).
 *
 * Builds the learner's own figures (never another learner's, never Arabic,
 * no personal data beyond a first name), keeps one stored summary per
 * learner in `ai_insights`, and asks a queued job to rewrite it when the
 * figures have changed and the last one is old enough. Pages only read the
 * stored summary and poll while it is being written (PERF-04). The next
 * step on the card is chosen here, from the journey, never by the model.
 */
final class LearnerCoach
{
    /** A new summary at most this often, however busy the learner is. */
    public const COOLDOWN_HOURS = 6;

    public function __construct(private readonly JourneyService $journey) {}

    /**
     * What the coaching card shows, dispatching a refresh when one is due.
     *
     * @return array{status: string, headline: string|null, strengths: list<string>, focus: list<string>, tip: string|null, generatedAt: string|null, nextStep: array{label: string, description: string, url: string}}
     */
    public function present(User $user): array
    {
        $context = $this->context($user);
        $insight = AiInsight::for($user, AiInsight::KIND_LEARNER_COACH);

        if ($this->hasActivity($context) && AiInsight::needsRefresh($insight, self::fingerprint($context), self::COOLDOWN_HOURS)) {
            $insight = $this->queue($user, $insight, self::fingerprint($context));
        }

        $payload = $insight === null ? [] : ($insight->payload ?? []);

        return [
            'status' => AiInsight::stateOf($insight),
            'headline' => is_string($payload['headline'] ?? null) ? $payload['headline'] : null,
            'strengths' => self::strings($payload['strengths'] ?? []),
            'focus' => self::strings($payload['focus'] ?? []),
            'tip' => is_string($payload['tip'] ?? null) ? $payload['tip'] : null,
            'generatedAt' => $insight?->generated_at?->toIso8601String(),
            'nextStep' => $this->nextStep($user),
        ];
    }

    /**
     * The learner's own figures, as the model will read them.
     *
     * @return array<string, mixed>
     */
    public function context(User $user): array
    {
        $journey = $this->journey->summary($user);
        $pre = $this->latestTest($user, TestType::Pre);
        $post = $this->latestTest($user, TestType::Post);
        $latest = $post ?? $pre;

        // What the admin hides from the learner stays out of the model's data
        // too: a score the coach never receives is a score it cannot mention
        // (TEST-04; spec 0005 §5.1). The skill breakdown and the level (which
        // is derived from the score) follow the same setting.
        $skills = self::showsBreakdown($latest) ? $this->skillPercents($latest) : [];
        asort($skills);

        $practice = $user->attempts()
            ->whereNull('test_attempt_id')
            ->whereNotNull('is_correct')
            ->where('submitted_at', '>=', Date::now()->subDays(30));
        $practiceTotal = (clone $practice)->count();

        return [
            'first_name' => (string) strtok(trim($user->name), ' '),
            'english_level' => self::showsScore($latest) ? $user->english_level?->label('en') : null,
            'lessons' => [
                'completed' => $journey['lessonsCompleted'],
                'total' => $journey['lessonsTotal'],
            ],
            'pre_test' => self::testFigures($pre),
            'post_test' => self::testFigures($post),
            'skills_percent' => $skills,
            'weakest_skill' => $skills === [] ? null : array_key_first($skills),
            'strongest_skill' => $skills === [] ? null : array_key_last($skills),
            'practice_last_30_days' => [
                'answers' => $practiceTotal,
                'correct_percent' => $practiceTotal === 0 ? null : (int) round((clone $practice)->where('is_correct', true)->count() / $practiceTotal * 100),
            ],
            'recent_roleplays' => $this->recentRoleplays($user),
            'phrasebook' => [
                'saved' => $user->phrasebookItems()->count(),
                'needs_practice' => $user->phrasebookItems()->where('lapse_count', '>', 0)->where('review_box', '<=', 1)->count(),
            ],
        ];
    }

    /**
     * The one step the card offers, from the journey (JOURNEY-02).
     *
     * @return array{label: string, description: string, url: string}
     */
    public function nextStep(User $user): array
    {
        $journey = $this->journey->summary($user);

        if ($journey['postTestUnlocked'] && ! $this->journey->postTestSubmitted($user) && $this->journey->postTest($user) !== null) {
            return ['label' => __('Take the Post-test'), 'description' => __('You finished every lesson. Show how far you have come.'), 'url' => route('learn.post-test')];
        }

        if ($journey['continueUrl'] !== null && $journey['lessonsCompleted'] < $journey['lessonsTotal']) {
            return ['label' => __('Continue my lesson'), 'description' => __('Pick up exactly where you stopped.'), 'url' => $journey['continueUrl']];
        }

        $due = $user->phrasebookItems()->dueForReview()->count();

        if ($due > 0) {
            return ['label' => __('Review my phrases'), 'description' => trans_choice(':count phrase is ready to review.|:count phrases are ready to review.', $due), 'url' => route('learn.phrasebook.review')];
        }

        if ($journey['certificateAvailable']) {
            return ['label' => __('View my certificate'), 'description' => __('Your training is complete.'), 'url' => route('learn.certificate')];
        }

        return ['label' => __('Open my lessons'), 'description' => __('Choose a lesson to practise again.'), 'url' => route('learn.lessons')];
    }

    /**
     * A stable hash of the figures that matter, so a summary is rewritten
     * only when something the learner did has changed.
     *
     * @param  array<string, mixed>  $context
     */
    public static function fingerprint(array $context): string
    {
        return hash('sha256', (string) json_encode($context));
    }

    // ------------------------------------------------------------ internals

    /**
     * @param  array<string, mixed>  $context
     */
    private function hasActivity(array $context): bool
    {
        /** @var array{completed: int} $lessons */
        $lessons = $context['lessons'];
        /** @var array{taken: bool} $pre */
        $pre = $context['pre_test'];
        /** @var array{answers: int} $practice */
        $practice = $context['practice_last_30_days'];

        return $lessons['completed'] > 0
            || $pre['taken']
            || $practice['answers'] > 0
            || $context['recent_roleplays'] !== [];
    }

    private function queue(User $user, ?AiInsight $insight, string $fingerprint): AiInsight
    {
        $insight ??= new AiInsight([
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'kind' => AiInsight::KIND_LEARNER_COACH,
            'hotel_id' => $user->hotel_id,
        ]);

        $insight->forceFill([
            'status' => GenerationStatus::Pending,
            'fingerprint' => $fingerprint,
            'failed_reason' => null,
        ])->save();

        // rescue(): a queue outage leaves the card on its last summary (or
        // its pending state), never an error page for the learner.
        rescue(function () use ($user): void {
            GenerateLearnerCoaching::dispatch($user->id);
        });

        return $insight->refresh();
    }

    private function latestTest(User $user, TestType $type): ?TestAttempt
    {
        return $user->testAttempts()
            ->where('status', TestAttemptStatus::Submitted->value)
            ->whereHas('test', fn ($test) => $test->where('type', $type->value))
            ->with('test')
            ->latest('submitted_at')
            ->first();
    }

    /**
     * A sitting as the model may see it: that it was taken, and its
     * percentage only when the test shows the learner their score (TEST-04).
     *
     * @return array{taken: bool, percent: int|null}
     */
    private static function testFigures(?TestAttempt $attempt): array
    {
        return [
            'taken' => $attempt !== null,
            'percent' => self::showsScore($attempt) ? self::percent($attempt) : null,
        ];
    }

    /**
     * Does this sitting's test show the learner their score (TEST-04)? The
     * default, and an unknown test, is hidden.
     */
    private static function showsScore(?TestAttempt $attempt): bool
    {
        return $attempt?->test?->resultsVisibility()->showsScore() ?? false;
    }

    private static function showsBreakdown(?TestAttempt $attempt): bool
    {
        return $attempt?->test?->resultsVisibility()->showsBreakdown() ?? false;
    }

    private static function percent(?TestAttempt $attempt): ?int
    {
        if ($attempt === null || $attempt->score === null || $attempt->max_score === null || (float) $attempt->max_score <= 0) {
            return null;
        }

        return (int) round((float) $attempt->score / (float) $attempt->max_score * 100);
    }

    /**
     * Per-skill percentages from a sitting's breakdown, whichever shape it
     * was stored in (score/max from TestScorer, correct/total from older rows).
     *
     * @return array<string, int>
     */
    private function skillPercents(?TestAttempt $attempt): array
    {
        $percents = [];

        foreach ($attempt->breakdown ?? [] as $skill => $band) {
            if (! is_array($band)) {
                continue;
            }

            $got = $band['score'] ?? $band['correct'] ?? null;
            $of = $band['max'] ?? $band['total'] ?? null;

            if (is_numeric($got) && is_numeric($of) && (float) $of > 0) {
                $percents[(string) $skill] = (int) round((float) $got / (float) $of * 100);
            }
        }

        return $percents;
    }

    /**
     * @return list<array{scenario: string, overall: int|null, criteria: array<string, int>, improve: list<string>}>
     */
    private function recentRoleplays(User $user): array
    {
        return array_values($user->roleplayAttempts()
            ->where('is_preview', false)
            ->where('status', RoleplayStatus::Completed->value)
            ->with('scenario:id,title')
            ->latest('ended_at')
            ->limit(3)
            ->get()
            ->map(function (RoleplayAttempt $attempt): array {
                $improve = [];

                foreach ((array) ($attempt->feedback['improve'] ?? []) as $entry) {
                    if (is_array($entry) && is_string($entry['title'] ?? null) && $entry['title'] !== '') {
                        $improve[] = $entry['title'];
                    }
                }

                return [
                    'scenario' => $attempt->scenario->title ?? '',
                    'overall' => $attempt->overall_score,
                    'criteria' => $attempt->criteria_scores ?? [],
                    'improve' => $improve,
                ];
            })
            ->all());
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''))
            : [];
    }
}
