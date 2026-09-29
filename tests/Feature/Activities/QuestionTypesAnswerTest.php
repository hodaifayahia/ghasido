<?php

namespace Tests\Feature\Activities;

use App\Contracts\AiProvider;
use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Jobs\AssessSpokenPronunciation;
use App\Jobs\EvaluateWrittenAnswer;
use App\Jobs\TranscribeAndEvaluateSpokenAnswer;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\PronunciationAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\VoiceRecording;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * A learner answers the client's question types in a lesson and in a test
 * (client report 2026-09-29): the raw answer is stored verbatim on the
 * version answered (TEST-06, DATA-01, DATA-11), a test page never carries
 * the answers (TEST-03), a spoken sentence gets a pronunciation score and
 * a written reply a structured AI verdict with corrections, both queued
 * and polled (AIE-01, AIE-04, PERF-04). Fake providers, sync queue.
 */
class QuestionTypesAnswerTest extends TestCase
{
    use BuildsLearnerFixtures, QuestionPayloads, RefreshDatabase;

    private User $learner;

    private Lesson $lesson;

    private Block $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        config([
            'services.ai.provider' => 'fake',
            'services.stt.provider' => 'fake',
            'services.tts.provider' => 'fake',
            'services.stt.fake_words' => 'good morning welcome to our hotel',
            'services.stt.fake_hinted_words' => null,
        ]);
        Storage::fake(MediaAsset::DISK_LOCAL);
        Storage::fake(MediaAsset::DISK_PUBLIC);

        $this->learner = $this->learner();
        $this->submitPreTest($this->learner);
        $this->lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $this->block = $this->lesson->visibleBlocks()->firstOrFail();
    }

    private function place(string $type): ActivityPlacement
    {
        $activity = Activity::factory()->create([
            'type' => ActivityType::from($type),
            'prompt' => 'Answer.',
            'payload' => ['items' => [self::itemFor($type)]],
            'attempts_allowed' => 0,
        ]);

        return $this->block->placements()->create([
            'activity_id' => $activity->id,
            'position' => ((int) $this->block->placements()->max('position')) + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function answer(ActivityPlacement $placement, array $answers): Attempt
    {
        $this->actingAs($this->learner)
            ->post(route('learn.lessons.activity.answer', ['lesson' => $this->lesson, 'block' => $this->block, 'placement' => $placement]), [
                'answers' => $answers,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Attempt::query()->where('placement_id', $placement->id)->latest('id')->firstOrFail();
    }

    /**
     * The practice page's result for one attempt, as the page polls it.
     */
    private function poll(ActivityPlacement $placement, Attempt $attempt, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->learner)
            ->get(route('learn.lessons.activity', ['lesson' => $this->lesson, 'block' => $this->block, 'placement' => $placement, 'attempt' => $attempt->id]));
    }

    private function recording(): MediaAsset
    {
        $asset = MediaAsset::factory()->recording()->create(['uploaded_by' => $this->learner->id]);
        Storage::disk(MediaAsset::DISK_LOCAL)->put($asset->path, 'webm-bytes');
        VoiceRecording::factory()->create([
            'user_id' => $this->learner->id,
            'recordable_type' => $this->block->getMorphClass(),
            'recordable_id' => $this->block->id,
            'media_asset_id' => $asset->id,
        ]);

        return $asset;
    }

    public function test_typed_answers_are_stored_verbatim_and_scored_against_the_accepted_answers()
    {
        $short = $this->place('short_answer');
        $blank = $this->place('fill_blank');
        $matching = $this->place('matching');
        $ordering = $this->place('ordering');

        $attempt = $this->answer($short, ['i1' => 'Room KEY.']);
        $this->assertSame(['i1' => 'Room KEY.'], $attempt->raw_answer);
        $this->assertTrue($attempt->is_correct);
        $this->assertNotNull($attempt->activity_version_id);

        $attempt = $this->answer($blank, ['i1' => ['b1' => 'passport', 'b2' => 'towel']]);
        $this->assertSame(['i1' => ['b1' => 'passport', 'b2' => 'towel']], $attempt->raw_answer);
        $this->assertFalse($attempt->is_correct);

        // The practice page reveals the accepted word of every blank.
        $this->poll($blank, $attempt)->assertInertia(fn (Assert $page) => $page
            ->where('result.isCorrect', false)
            ->where('result.correct.i1', ['b1' => 'passport', 'b2' => 'key'])
        );

        $this->assertTrue($this->answer($matching, ['i1' => ['1' => 'a', '2' => 'b']])->is_correct);
        $this->assertFalse($this->answer($ordering, ['i1' => ['s2', 's1', 's3']])->is_correct);
    }

    public function test_a_test_page_never_carries_the_accepted_answers_pairs_or_order()
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => ['time_limit_seconds' => null, 'results_visibility' => 'hidden'],
        ]);

        foreach (['short_answer', 'fill_blank', 'matching', 'ordering'] as $position => $type) {
            $activity = Activity::factory()->create([
                'type' => ActivityType::from($type),
                'payload' => ['items' => [self::itemFor($type)]],
                'show_meaning_enabled' => false,
            ]);
            $test->questions()->create(['activity_id' => $activity->id, 'position' => $position + 1]);
        }

        $other = $this->learner(['username' => 'amine']);
        $this->actingAs($other)->post(route('learn.tests.start', $test))->assertRedirect();
        $sitting = TestAttempt::query()->where('user_id', $other->id)->firstOrFail();

        foreach ([1, 2, 3, 4] as $number) {
            $this->actingAs($other)
                ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $sitting, 'number' => $number]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->missing('activity.items.0.accepted')
                    ->missing('activity.items.0.blanks.0.accepted')
                    ->missing('activity.items.0.pairs')
                    ->missing('activity.items.0.order')
                );
        }
    }

    public function test_a_sentence_to_say_is_checked_for_pronunciation_and_the_page_polls_the_result()
    {
        $placement = $this->place('speaking');
        $asset = $this->recording();

        Queue::fake();
        $attempt = $this->answer($placement, ['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 3000]]);
        Queue::assertPushed(AssessSpokenPronunciation::class, fn (AssessSpokenPronunciation $job): bool => $job->attemptId === $attempt->id);
        Queue::assertNotPushed(TranscribeAndEvaluateSpokenAnswer::class);
        $this->assertSame(GenerationStatus::Pending, $attempt->ai_status);

        // While the job waits, the page shows the evaluating state.
        $this->poll($placement, $attempt)->assertInertia(fn (Assert $page) => $page
            ->where('result.aiStatus', 'pending')
            ->where('result.feedback', null)
        );

        (new AssessSpokenPronunciation($attempt->id))->handle();

        $attempt->refresh();
        $check = PronunciationAttempt::query()->sole();
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        $this->assertSame('pronunciation', $attempt->ai_feedback['kind'] ?? null);
        $this->assertSame($check->id, $attempt->ai_feedback['pronunciation_attempt_id'] ?? null);
        $this->assertSame('Good morning, welcome to our hotel.', $check->reference_text);
        $this->assertSame($this->block->id, $check->block_id);
        $this->assertSame((float) $check->score, (float) $attempt->score);
        $this->assertSame(100.0, (float) $attempt->max_score);
        // The raw answer (the recording) stays as posted (TEST-06).
        $this->assertSame(['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 3000]], $attempt->raw_answer);

        $this->poll($placement, $attempt)->assertInertia(fn (Assert $page) => $page
            ->where('result.aiStatus', 'done')
            ->where('result.feedback.kind', 'pronunciation')
            ->where('result.feedback.check.text', 'Good morning, welcome to our hotel.')
            ->has('result.feedback.check.words', 6)
        );

        // Another learner cannot read this result through the poll.
        $other = $this->learner(['username' => 'amine']);
        $this->submitPreTest($other);
        $this->poll($placement, $attempt, $other)->assertInertia(fn (Assert $page) => $page->where('result', null));
    }

    public function test_an_open_speaking_question_is_judged_by_the_ai_instead()
    {
        $placement = $this->place('speaking');
        $activity = $placement->activity()->firstOrFail();
        $activity->payload = ['items' => [[...self::itemFor('speaking'), 'expected_text' => null]]];
        $activity->save();
        $asset = $this->recording();

        Queue::fake();
        $this->answer($placement, ['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 3000]]);

        Queue::assertPushed(TranscribeAndEvaluateSpokenAnswer::class);
        Queue::assertNotPushed(AssessSpokenPronunciation::class);
    }

    public function test_a_written_reply_gets_a_score_per_criterion_corrections_and_a_better_answer()
    {
        $placement = $this->place('writing');

        Queue::fake();
        $attempt = $this->answer($placement, ['i1' => ['text' => 'hello guest you can leave at 2 pm it cost 30 euro']]);
        Queue::assertPushed(EvaluateWrittenAnswer::class);

        (new EvaluateWrittenAnswer($attempt->id))->handle(app(AiProvider::class), app(UsageMeter::class));

        $attempt->refresh();
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        // The item's own rubric, stored structured (AIE-04).
        $this->assertSame(['tone', 'content'], array_keys($attempt->ai_feedback['criteria'] ?? []));
        $this->assertSame('Polite tone', $attempt->ai_feedback['criteria']['tone']['label'] ?? null);
        $this->assertNotEmpty($attempt->ai_feedback['corrections'] ?? []);
        $this->assertNotSame('', $attempt->ai_feedback['better_answer'] ?? '');

        $this->poll($placement, $attempt)->assertInertia(fn (Assert $page) => $page
            ->where('result.aiStatus', 'done')
            ->where('result.feedback.kind', 'writing')
            ->where('result.feedback.criteria.0.label', 'Polite tone')
            ->where('result.feedback.corrections.0.original', 'hello guest you can')
            ->whereType('result.feedback.score', 'integer|double')
            ->whereType('result.feedback.betterAnswer', 'string')
        );
    }

    public function test_finishing_a_test_queues_the_pronunciation_check_of_a_spoken_sentence()
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => ['time_limit_seconds' => null, 'results_visibility' => 'hidden'],
        ]);
        $activity = Activity::factory()->create([
            'type' => ActivityType::Speaking,
            'payload' => ['items' => [self::itemFor('speaking')]],
            'show_meaning_enabled' => false,
        ]);
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        $other = $this->learner(['username' => 'amine']);
        $asset = MediaAsset::factory()->recording()->create(['uploaded_by' => $other->id]);
        $this->actingAs($other)->post(route('learn.tests.start', $test))->assertRedirect();
        $sitting = TestAttempt::query()->where('user_id', $other->id)->firstOrFail();

        Queue::fake();
        $this->actingAs($other)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $sitting]), [
            'number' => 1,
            'answer' => ['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 4000]],
        ])->assertRedirect();

        $row = Attempt::query()->where('test_attempt_id', $sitting->id)->firstOrFail();
        Queue::assertPushed(AssessSpokenPronunciation::class, fn (AssessSpokenPronunciation $job): bool => $job->attemptId === $row->id);
        Queue::assertNotPushed(TranscribeAndEvaluateSpokenAnswer::class);
    }
}
