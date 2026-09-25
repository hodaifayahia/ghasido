<?php

namespace Tests\Feature\Learn;

use App\Contracts\SpeechToTextProvider;
use App\Contracts\WordTranscript;
use App\Enums\Accent;
use App\Enums\AiFeature;
use App\Enums\AudioSpeed;
use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Enums\LexiconKind;
use App\Jobs\CheckPronunciation;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\PronunciationAttempt;
use App\Models\PronunciationGuide;
use App\Models\User;
use App\Services\Pronunciation\PronunciationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * The pronunciation check of a Listen & Repeat step (spec 0006 §5, §7):
 * recording → free listen → verdict per word → hinted listen when it can
 * help → score v1 → coach. The fake STT "hears" `services.stt.fake_words`;
 * jobs run inline on the sync queue.
 */
class PronunciationCheckTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();

        Storage::fake(MediaAsset::DISK_LOCAL);
        Storage::fake(MediaAsset::DISK_PUBLIC);

        config([
            'services.tts.voice' => 'flux-hannah-en',
            'services.stt.fake_words' => null,
            'services.stt.fake_hinted_words' => null,
        ]);
    }

    /**
     * A published lesson whose one step is a Listen & Repeat of these
     * sentences.
     *
     * @param  list<string>  $sentences
     * @return array{0: Lesson, 1: Block}
     */
    private function listenRepeat(array $sentences = ['Would you like a very quiet room?'], ?Hotel $hotel = null): array
    {
        $lesson = $this->publishedLesson([BlockType::ListenRepeat], $hotel);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $block->forceFill([
            'settings' => ['items' => array_map(fn (string $text): array => ['text' => $text, 'arabic' => null, 'image' => null], $sentences)],
        ])->save();

        return [$lesson, $block];
    }

    private function check(User $learner, Lesson $lesson, Block $block, int $item = 0, ?int $word = null): TestResponse
    {
        $payload = [
            'audio' => UploadedFile::fake()->create('repeat.webm', 40, 'audio/webm'),
            'item' => $item,
            'duration_ms' => 3200,
        ];

        if ($word !== null) {
            $payload['word'] = $word;
        }

        return $this->actingAs($learner)->postJson(
            route('learn.pronunciation.store', ['lesson' => $lesson, 'block' => $block]),
            $payload,
        );
    }

    /**
     * @return array<string, string>
     */
    private function statuses(TestResponse $response): array
    {
        $statuses = [];

        foreach ((array) $response->json('words') as $word) {
            $statuses[$word['text']] = $word['status'];
        }

        return $statuses;
    }

    public function test_a_clear_sentence_is_checked_word_by_word_and_stored()
    {
        config(['services.stt.fake_words' => 'would you like a very quiet room']);
        $learner = $this->learner();
        [$lesson, $block] = $this->listenRepeat();

        $response = $this->check($learner, $lesson, $block);

        $response->assertAccepted()
            ->assertJson([
                'status' => 'done',
                'settled' => true,
                'level' => 'excellent',
                'levelLabel' => 'Excellent',
                'feedbackStatus' => 'skipped',
                'isDrill' => false,
                'attemptNo' => 1,
            ]);
        $this->assertSame(['correct'], array_values(array_unique($this->statuses($response))));

        $attempt = PronunciationAttempt::query()->sole();
        $this->assertSame('Would you like a very quiet room?', $attempt->reference_text);
        $this->assertSame($learner->id, $attempt->user_id);
        $this->assertSame($this->hotel->id, $attempt->hotel_id);
        $this->assertSame($block->id, $attempt->block_id);
        $this->assertSame(0, $attempt->item_index);
        $this->assertSame('v1', $attempt->scoring_version);
        $this->assertNotNull($attempt->voice_recording_id);
        $this->assertArrayHasKey('free', $attempt->stt_raw ?? []);
        $this->assertArrayNotHasKey('hinted', $attempt->stt_raw ?? [], 'a perfect first listen needs no second one');

        // The recording is private and tied to the step (PRIV-04, DATA-02).
        $this->assertDatabaseHas('voice_recordings', ['user_id' => $learner->id, 'recordable_id' => $block->id]);

        // Metered for cost, never charged to the learner (AIL-04).
        $this->assertDatabaseHas('ai_usages', [
            'user_id' => $learner->id,
            'feature' => AiFeature::PronunciationCheck->value,
            'points_charged' => 0,
        ]);
    }

    public function test_a_mispronounced_word_is_named_listened_again_and_coached()
    {
        config(['services.stt.fake_words' => 'would you like a furry quiet room']);
        [$lesson, $block] = $this->listenRepeat();

        $response = $this->check($this->learner(), $lesson, $block)->assertAccepted();

        $very = collect((array) $response->json('words'))->firstWhere('text', 'very');
        $this->assertSame('mispronounced', $very['status']);
        $this->assertSame('furry', $very['heard']);
        $this->assertSame('v → f', $very['sound']);

        $attempt = PronunciationAttempt::query()->sole();
        // A word a hint could still find: the second listen ran with keyterms.
        $this->assertSame(['would', 'you', 'like', 'very', 'quiet', 'room'], $attempt->stt_raw['hinted']['keyterms'] ?? null);

        // The coach ran (fake provider) and named the weak word.
        $this->assertSame(PronunciationAttempt::FEEDBACK_DONE, $attempt->feedback_status);
        $this->assertSame('very', $attempt->feedback['tips'][0]['word'] ?? null);
        $this->assertDatabaseHas('ai_usages', ['feature' => AiFeature::PronunciationCoach->value, 'points_charged' => 0]);

        // The sentence's guide was drafted for the lesson's accent.
        $guide = PronunciationGuide::query()->sole();
        $this->assertSame(GenerationStatus::Done, $guide->status);
        $this->assertSame(Accent::American, $guide->accent);

        // The weak word's own clips are queued for the drill.
        $this->assertDatabaseHas('audio_clips', ['text_hash' => AudioClip::hashFor('very'), 'speed' => AudioSpeed::Slow->value]);
    }

    public function test_a_word_only_heard_with_hints_is_almost()
    {
        config([
            'services.stt.fake_words' => 'you are staying for tree:0.77 nights',
            'services.stt.fake_hinted_words' => 'you are staying for three:0.5 nights',
        ]);
        [$lesson, $block] = $this->listenRepeat(['You are staying for three nights.']);

        $response = $this->check($this->learner(), $lesson, $block)->assertAccepted();

        $this->assertSame('almost', $this->statuses($response)['three']);
    }

    public function test_a_word_of_the_sentence_can_be_drilled_on_its_own()
    {
        config(['services.stt.fake_words' => 'very']);
        $learner = $this->learner();
        [$lesson, $block] = $this->listenRepeat();

        $response = $this->check($learner, $lesson, $block, word: 4)->assertAccepted();

        $response->assertJson(['isDrill' => true, 'wordIndex' => 4, 'text' => 'very', 'level' => 'excellent']);

        $attempt = PronunciationAttempt::query()->sole();
        $this->assertSame('very', $attempt->reference_text);
        $this->assertSame('Would you like a very quiet room?', $attempt->sentence_text);

        // The drill reuses the sentence's guide: no guide for "very" alone.
        $this->assertSame(1, PronunciationGuide::query()->count());
        $this->assertSame(AudioClip::hashFor('Would you like a very quiet room?'), PronunciationGuide::query()->sole()->text_hash);
    }

    public function test_attempts_are_numbered_per_sentence_and_per_word()
    {
        config(['services.stt.fake_words' => 'how can i help you']);
        $learner = $this->learner();
        [$lesson, $block] = $this->listenRepeat(['How can I help you?', 'Good morning.']);

        $this->check($learner, $lesson, $block)->assertJson(['attemptNo' => 1]);
        $this->check($learner, $lesson, $block)->assertJson(['attemptNo' => 2]);
        $this->check($learner, $lesson, $block, item: 1)->assertJson(['attemptNo' => 1]);
        $this->check($learner, $lesson, $block, word: 3)->assertJson(['attemptNo' => 1]);
    }

    public function test_a_word_of_a_vocabulary_step_can_be_checked()
    {
        config(['services.stt.fake_words' => 'reservation']);
        $lesson = $this->publishedLesson([BlockType::Vocabulary]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $item = LexiconItem::factory()->create(['english_text' => 'reservation', 'kind' => LexiconKind::Word]);
        $block->lexiconItems()->attach($item->id, ['position' => 1]);

        $this->check($this->learner(), $lesson, $block, item: $item->id)
            ->assertAccepted()
            ->assertJson(['text' => 'reservation', 'level' => 'excellent']);

        $this->assertSame($item->id, PronunciationAttempt::query()->sole()->lexicon_item_id);
    }

    public function test_nothing_heard_gets_a_fixed_message_and_no_coaching_call()
    {
        config(['services.stt.fake_words' => 'um']);
        [$lesson, $block] = $this->listenRepeat();

        $response = $this->check($this->learner(), $lesson, $block)->assertAccepted();

        $response->assertJson([
            'level' => 'not_heard',
            'score' => 0,
            'feedbackStatus' => 'skipped',
            'feedback' => ['headline' => 'We could not hear you.'],
        ]);
        $this->assertDatabaseMissing('ai_usages', ['feature' => AiFeature::PronunciationCoach->value]);
    }

    // ------------------------------------------------------------- security

    public function test_another_hotels_step_is_forbidden()
    {
        [$lesson, $block] = $this->listenRepeat(hotel: Hotel::factory()->create());

        $this->check($this->learner(), $lesson, $block)->assertForbidden();

        $this->assertDatabaseCount('pronunciation_attempts', 0);
        $this->assertDatabaseCount('voice_recordings', 0);
    }

    public function test_the_pre_test_gate_applies()
    {
        $this->publishedPreTest();
        [$lesson, $block] = $this->listenRepeat();

        $this->check($this->learner(), $lesson, $block)->assertForbidden();
        $this->assertDatabaseCount('pronunciation_attempts', 0);
    }

    public function test_a_block_of_another_lesson_is_not_found()
    {
        [$lesson] = $this->listenRepeat();
        [, $otherBlock] = $this->listenRepeat();

        $this->check($this->learner(), $lesson, $otherBlock)->assertNotFound();
    }

    public function test_an_item_or_word_the_step_does_not_have_is_refused()
    {
        [$lesson, $block] = $this->listenRepeat();
        $learner = $this->learner();

        $this->check($learner, $lesson, $block, item: 5)->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->check($learner, $lesson, $block, word: 40)->assertUnprocessable()->assertJsonValidationErrors('item');

        $situation = $this->publishedLesson([BlockType::Situation]);
        $this->check($learner, $situation, $situation->visibleBlocks()->firstOrFail())->assertUnprocessable();

        $this->assertDatabaseCount('pronunciation_attempts', 0);
    }

    public function test_only_the_learner_who_recorded_it_can_read_a_check()
    {
        config(['services.stt.fake_words' => 'would you like a very quiet room']);
        $owner = $this->learner();
        [$lesson, $block] = $this->listenRepeat();
        $id = $this->check($owner, $lesson, $block)->json('id');

        $this->actingAs($owner)->getJson(route('learn.pronunciation.show', $id))->assertOk()->assertJson(['id' => $id]);

        $coworker = $this->learner(['username' => 'amine']);
        $this->actingAs($coworker)->getJson(route('learn.pronunciation.show', $id))->assertForbidden();
    }

    public function test_the_daily_cap_stops_further_checks()
    {
        config(['guesvia.pronunciation.daily_checks_per_employee' => 1, 'services.stt.fake_words' => 'hello']);
        $learner = $this->learner();
        [$lesson, $block] = $this->listenRepeat(['Hello.']);

        $this->check($learner, $lesson, $block)->assertAccepted();
        $this->check($learner, $lesson, $block)->assertStatus(429);

        $this->assertDatabaseCount('pronunciation_attempts', 1);
    }

    // -------------------------------------------------------------- failure

    public function test_a_failing_recogniser_leaves_a_failed_state_with_a_plain_message()
    {
        Queue::fake();
        [$lesson, $block] = $this->listenRepeat();

        $response = $this->check($this->learner(), $lesson, $block)->assertAccepted();
        $response->assertJson(['status' => 'pending', 'settled' => false]);
        Queue::assertPushed(CheckPronunciation::class);

        $this->app->instance(SpeechToTextProvider::class, new class implements SpeechToTextProvider
        {
            public function transcribe(string $absolutePath, string $mime): string
            {
                throw new RuntimeException('Deepgram speech-to-text failed (HTTP 401): Invalid credentials.');
            }

            public function transcribeWords(string $absolutePath, string $mime, array $keyterms = []): WordTranscript
            {
                throw new RuntimeException('Deepgram speech-to-text failed (HTTP 401): Invalid credentials.');
            }
        });

        $job = new CheckPronunciation((int) $response->json('id'));

        try {
            app()->call([$job, 'handle']);
            $this->fail('The provider error was swallowed.');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $attempt = PronunciationAttempt::query()->sole();
        $this->assertSame(GenerationStatus::Failed, $attempt->status);
        $this->assertStringContainsString('HTTP 401', (string) $attempt->failed_reason);

        $shown = app(PronunciationPresenter::class)->present($attempt);
        $this->assertTrue($shown['settled']);
        $this->assertSame('We could not check this recording. Please try again.', $shown['error']);
        $this->assertStringNotContainsString('401', json_encode($shown) ?: '');
    }
}
