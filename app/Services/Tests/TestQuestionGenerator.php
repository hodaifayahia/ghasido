<?php

namespace App\Services\Tests;

use App\Contracts\TestQuestionsDraft;
use App\Enums\ActivityType;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\TestQuestionSkill;
use App\Enums\TestType;
use App\Jobs\GenerateTestQuestions;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Test;
use App\Models\User;
use App\Services\Content\ActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AI-drafted questions for a Pre-test or Post-test (GEN-01, GEN-03, GEN-04,
 * TEST-02, TEST-05, TSTM-03, DATA-11, PERF-04; spec 0004).
 *
 * request() records what the admin asked for on the test row and queues the
 * job; store() is what the job calls with the provider's draft. Each question
 * becomes an ordinary versioned activity (DATA-11) placed on the test with an
 * `ai_draft` flag: learners never see it until the admin publishes the test
 * (GEN-03). regenerate() replaces the last batch, never touching a question
 * someone has already answered (DATA-10).
 */
class TestQuestionGenerator
{
    public const MAX_QUESTIONS = 20;

    public function __construct(
        private readonly ActivityService $activities,
        private readonly TestAudio $audio,
    ) {}

    /**
     * @param  array{count: int, skills: list<string>, level: string, prompt: string, paired: bool}  $input
     */
    public function request(Test $test, User $actor, array $input): Test
    {
        $this->assertNotRunning($test);

        $pairedFrom = null;
        $avoid = [];

        if ($input['paired']) {
            $source = $this->pairedSource($test);

            if ($source === null) {
                throw ValidationException::withMessages(['paired' => __('This test has no paired Pre-test with questions to mirror.')]);
            }

            $pairedFrom = $source->id;
            $skills = [];

            foreach ($source->questions()->with('activity')->get() as $placement) {
                if ($placement->activity !== null) {
                    $skills[] = TestQuestionSkill::fromActivityType($placement->activity->type)->value;
                    $avoid[] = $this->questionText($placement);
                }
            }

            $skills = array_slice($skills, 0, self::MAX_QUESTIONS);

            if ($test->paired_test_id === null) {
                $test->paired_test_id = $source->id;
            }
        } else {
            $skills = self::distribute($input['skills'], $input['count']);
        }

        $test->forceFill([
            'ai_status' => GenerationStatus::Pending,
            'ai_failed_reason' => null,
            'ai_request' => [
                'skills' => $skills,
                'level' => $input['level'],
                'prompt' => $input['prompt'],
                'paired_from' => $pairedFrom,
                'avoid' => $avoid,
                'actor_id' => $actor->id,
                'placement_ids' => [],
            ],
        ])->save();

        AuditLog::record($test, 'test.ai.requested', ['skills' => $skills, 'paired_from' => $pairedFrom]);

        GenerateTestQuestions::dispatch($test->id);

        return $test;
    }

    /**
     * Replace the last generated batch with a fresh draft (GEN-04).
     */
    public function regenerate(Test $test, User $actor): Test
    {
        $this->assertNotRunning($test);

        $request = $test->ai_request;

        if (! is_array($request) || ! is_array($request['skills'] ?? null) || $request['skills'] === []) {
            throw ValidationException::withMessages(['ai' => __('Nothing to regenerate yet. Generate questions first.')]);
        }

        DB::transaction(function () use ($test, $request, $actor): void {
            $ids = array_map('intval', is_array($request['placement_ids'] ?? null) ? $request['placement_ids'] : []);

            foreach (ActivityPlacement::query()->whereIn('id', $ids)->get() as $placement) {
                // Only an unreleased, unanswered draft of THIS test goes.
                $answered = Attempt::query()->where('activity_id', $placement->activity_id)->exists();

                if ($placement->placeable_type === $test->getMorphClass()
                    && (int) $placement->placeable_id === $test->id
                    && Test::isAiDraft($placement)
                    && ! $answered) {
                    $placement->delete();
                    $test->questions()->where('position', '>', $placement->position)->decrement('position');
                }
            }

            $test->forceFill([
                'ai_status' => GenerationStatus::Pending,
                'ai_failed_reason' => null,
                'ai_request' => [...$request, 'placement_ids' => [], 'actor_id' => $actor->id],
            ])->save();

            AuditLog::record($test, 'test.ai.regenerated');
        });

        GenerateTestQuestions::dispatch($test->id);

        return $test;
    }

    /**
     * Store the provider's draft as versioned, unreleased questions.
     *
     * @return list<int> the new placement ids
     */
    public function store(Test $test, TestQuestionsDraft $draft, User $actor): array
    {
        $ids = DB::transaction(function () use ($test, $draft, $actor): array {
            $ids = [];
            $position = (int) $test->questions()->max('position');

            foreach ($draft->questions as $question) {
                $skill = TestQuestionSkill::tryFrom($question['skill']) ?? TestQuestionSkill::MultipleChoice;
                $position++;

                $activity = $this->activities->create([
                    'type' => $skill->activityType(),
                    'skill_label' => $skill->skillLabel(),
                    'title' => $test->title.' – Question '.$position,
                    'prompt' => $question['question'],
                    'payload' => ['items' => [self::item($skill, $question)]],
                    'scoring' => null,
                    // Tests never show meaning (CTRL-04, TEST-03).
                    'show_meaning_enabled' => false,
                    'status' => ContentStatus::Draft,
                    'department_id' => $test->department_id,
                    'hotel_id' => $test->hotel_id,
                ], $actor);

                $placement = $test->questions()->create([
                    'activity_id' => $activity->id,
                    'position' => $position,
                    'overrides' => [Test::AI_DRAFT_OVERRIDE => true],
                ]);

                $ids[] = $placement->id;
            }

            AuditLog::record($test, 'test.ai.generated', ['placement_ids' => $ids]);

            return $ids;
        });

        // Listening scripts get their stored audio queued at once (CTRL-05).
        foreach ($test->questions()->with('activity')->whereIn('id', $ids)->get() as $placement) {
            if ($placement->activity !== null && $placement->activity->type === ActivityType::BestResponse) {
                $this->audio->generate($placement->activity);
            }
        }

        return $ids;
    }

    /**
     * The Pre-test a Post-test mirrors: its explicit pair, else the newest
     * Pre-test of the same department and hotel scope (TEST-02).
     */
    public function pairedSource(Test $test): ?Test
    {
        if ($test->type !== TestType::Post) {
            return null;
        }

        $paired = $test->pairedTest;

        if ($paired !== null && $paired->type === TestType::Pre && $paired->questions()->exists()) {
            return $paired;
        }

        return Test::query()
            ->ofType(TestType::Pre)
            ->where(fn ($query) => $test->department_id === null ? $query->whereNull('department_id') : $query->where('department_id', $test->department_id))
            ->where(fn ($query) => $test->hotel_id === null ? $query->whereNull('hotel_id') : $query->where('hotel_id', $test->hotel_id))
            ->whereHas('questions')
            ->latest('id')
            ->first();
    }

    /**
     * Spread `$count` questions over the chosen skills, round-robin.
     *
     * @param  list<string>  $skills
     * @return list<string>
     */
    public static function distribute(array $skills, int $count): array
    {
        $skills = array_values(array_filter($skills, static fn (string $skill): bool => TestQuestionSkill::tryFrom($skill) !== null));

        if ($skills === []) {
            $skills = [TestQuestionSkill::MultipleChoice->value];
        }

        $count = max(1, min(self::MAX_QUESTIONS, $count));
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = $skills[$i % count($skills)];
        }

        return $out;
    }

    /**
     * One payload item in the shape its activity type needs (spec 0003 B.9).
     * `model_answer` is an answer key: the presenter strips it on a test page.
     *
     * @param  array{skill: string, question: string, situation: string, options: list<array{id: string, text: string}>, correct: string|null, audio_script: string, sentences: list<string>, model_answer: string}  $question
     * @return array<string, mixed>
     */
    public static function item(TestQuestionSkill $skill, array $question): array
    {
        $situation = $question['situation'] !== '' ? $question['situation'] : null;

        return match ($skill) {
            TestQuestionSkill::MultipleChoice => array_filter([
                'id' => 'i1',
                'situation' => $situation,
                'question' => $question['question'],
                'options' => $question['options'],
                'correct' => $question['correct'],
            ], static fn (mixed $value): bool => $value !== null),
            TestQuestionSkill::Listening => array_filter([
                'id' => 'i1',
                'situation' => $situation,
                'question' => $question['question'],
                'guest_audio_text' => $question['audio_script'],
                'options' => $question['options'],
                'correct' => $question['correct'],
            ], static fn (mixed $value): bool => $value !== null),
            TestQuestionSkill::Speaking => array_filter([
                'id' => 'i1',
                'situation' => $situation,
                'question' => $question['question'],
                'instruction' => 'Record a short and polite response.',
                'max_seconds' => 30,
                'model_answer' => $question['model_answer'] !== '' ? $question['model_answer'] : null,
            ], static fn (mixed $value): bool => $value !== null),
            TestQuestionSkill::Writing => array_filter([
                'id' => 'i1',
                'scenario' => $situation,
                'question' => $question['question'],
                'min_words' => 15,
                'model_answer' => $question['model_answer'] !== '' ? $question['model_answer'] : null,
            ], static fn (mixed $value): bool => $value !== null),
            TestQuestionSkill::Ordering => self::orderingItem($question),
        };
    }

    /**
     * @param  array{question: string, sentences: list<string>}  $question
     * @return array<string, mixed>
     */
    private static function orderingItem(array $question): array
    {
        $sentences = [];

        foreach ($question['sentences'] as $index => $text) {
            $sentences[] = ['id' => 's'.($index + 1), 'text' => $text];
        }

        $order = array_column($sentences, 'id');

        // Shown shuffled by a stable key, never already in order.
        $shown = $sentences;
        usort($shown, static fn (array $a, array $b): int => crc32($a['text']) <=> crc32($b['text']));

        if (array_column($shown, 'id') === $order) {
            $shown = array_reverse($shown);
        }

        return [
            'id' => 'i1',
            'question' => $question['question'],
            'sentences' => $shown,
            'order' => $order,
        ];
    }

    private function questionText(ActivityPlacement $placement): string
    {
        $activity = $placement->activity()->first();

        if ($activity === null) {
            return '';
        }

        $text = $activity->items()[0]['question'] ?? $activity->prompt;

        return is_string($text) ? $text : '';
    }

    private function assertNotRunning(Test $test): void
    {
        // A run stuck for longer than the job's own timeout (worker down) no
        // longer blocks a new request.
        $stale = $test->updated_at !== null && $test->updated_at->lt(now()->subMinutes(15));

        if (! $stale && ($test->ai_status === GenerationStatus::Pending || $test->ai_status === GenerationStatus::Running)) {
            throw ValidationException::withMessages(['ai' => __('Questions are already being generated for this test.')]);
        }
    }
}
