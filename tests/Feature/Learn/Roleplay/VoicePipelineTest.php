<?php

namespace Tests\Feature\Learn\Roleplay;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\RoleplayStatus;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\RecordVoiceLine;
use App\Jobs\WarmVoiceLines;
use App\Models\AiScenario;
use App\Models\AiUsage;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Models\VoiceLine;
use App\Services\Ai\RoleplayPrompt;
use App\Services\Ai\UsageMeter;
use App\Services\VoiceAgent\VoiceAgentSettings;
use App\Services\VoiceAgent\VoiceLineBank;
use App\Services\VoiceAgent\VoiceReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The fast voice engine and its stored-voice bank (RP-03, RP-04, RP-11,
 * ROLE-02, AIL-03, AIL-04, TTS-02, API-02, SEC-03; spec 0009).
 */
class VoicePipelineTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    private const DEEPGRAM_KEY = 'dg-secret-key-never-shipped';

    private const QWEN_KEY = 'qwen-secret-key-never-shipped';

    private const VOICE = 'aura-2-thalia-en';

    protected Lesson $lesson;

    protected Block $block;

    protected AiScenario $scenario;

    /** What the fake Qwen answers with (its message content). */
    protected string $qwenContent = '{"say": "Good evening, I have a booking.", "keep": true}';

    protected int $speakStatus = 200;

    /** Qwen answers 429 "quota exhausted", as seen live on 2026-09-26. */
    protected bool $qwenQuotaSpent = false;

    /** Only this model's plan is spent (the others still answer). */
    private ?string $spentModel = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->setUpLearnerFixtures();
        config()->set('services.voice_agent.key', self::DEEPGRAM_KEY);

        $this->lesson = $this->publishedLesson([BlockType::AiRoleplay, BlockType::Complete]);
        $this->block = $this->lesson->visibleBlocks()->firstOrFail();
        $this->scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
            'slug' => 'fast-check-in',
            'attempts_allowed' => 3,
        ]);
        $this->block->scenarios()->attach($this->scenario->id, ['position' => 1]);

        Http::fake([
            'api.deepgram.com/v1/auth/grant' => Http::response(['access_token' => 'temporary-grant', 'expires_in' => 60]),
            'api.deepgram.com/v1/speak*' => fn () => $this->speakStatus === 200
                ? Http::response('ID3-fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg'])
                : Http::response(['err_msg' => 'boom'], $this->speakStatus),
            'qwen.test/*' => fn ($request) => $this->qwenQuotaSpent || ($this->spentModel !== null && ($request->data()['model'] ?? null) === $this->spentModel)
                ? Http::response(['error' => ['message' => 'Your token-plan quota has been exhausted.', 'code' => 'insufficient_quota']], 429)
                : Http::response([
                    'model' => 'qwen-flash-test',
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $this->qwenContent]]],
                    'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 9],
                ]),
        ]);
    }

    /** Qwen as the real text model, speech still the no-network fake. */
    private function useQwen(): void
    {
        config()->set('services.ai.provider', 'qwen');
        config()->set('services.ai.key', self::QWEN_KEY);
        config()->set('services.ai.base_url', 'https://qwen.test/v1');
        config()->set('services.ai.fast_model', 'qwen-flash-test');
    }

    private function startUrl(): string
    {
        return route('learn.roleplay.voice.start', [
            'lesson' => $this->lesson,
            'block' => $this->block,
            'scenario' => $this->scenario,
        ]);
    }

    private function replyUrl(RoleplayAttempt $attempt): string
    {
        return route('learn.roleplay.voice.reply', ['attempt' => $attempt]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function voiceCall(User $user, array $attributes = []): RoleplayAttempt
    {
        return RoleplayAttempt::factory()->create($attributes + [
            'user_id' => $user->id,
            'ai_scenario_id' => $this->scenario->id,
            'lesson_id' => $this->lesson->id,
            'block_id' => $this->block->id,
            'status' => RoleplayStatus::InProgress,
            'channel' => RoleplayAttempt::CHANNEL_VOICE_CALL,
            'voice_engine' => 'pipeline',
            'transcript' => [['role' => 'guest', 'text' => 'Hello! How may I help you?', 'seq' => 0, 'at' => now()->toIso8601String()]],
        ]);
    }

    private function recordedLine(string $text, ?AiScenario $scenario = null, bool $reusable = true): VoiceLine
    {
        return VoiceLine::factory()->recorded()->create([
            'ai_scenario_id' => ($scenario ?? $this->scenario)->id,
            'voice' => self::VOICE,
            'text' => $text,
            'reusable' => $reusable,
        ]);
    }

    public function test_start_opens_a_fast_engine_session_with_a_stored_greeting_and_no_key()
    {
        Queue::fake();
        $learner = $this->learner();

        $response = $this->actingAs($learner)->postJson($this->startUrl())->assertOk();

        $response->assertJsonPath('session.engine', 'pipeline')
            ->assertJsonPath('session.token', 'temporary-grant')
            ->assertJsonPath('session.inputSampleRate', 16000)
            ->assertJsonPath('session.chunkMs', 80)
            ->assertJsonPath('session.greeting.text', 'Hello! How may I help you?');

        $attempt = RoleplayAttempt::query()->where('user_id', $learner->id)->firstOrFail();
        $response->assertJsonPath('replyUrl', route('learn.roleplay.voice.reply', ['attempt' => $attempt->id]));
        $this->assertSame('pipeline', $attempt->voice_engine);

        $stt = (string) $response->json('session.sttUrl');
        $this->assertStringStartsWith('wss://api.deepgram.com/v2/listen?model=flux-general-en', $stt);
        $this->assertStringContainsString('sample_rate=16000', $stt);
        $this->assertStringContainsString('eot_timeout_ms=2000', $stt);
        $this->assertStringContainsString('eager_eot_threshold=0.5', $stt);

        // Lines not recorded yet are voiced live on Deepgram's speech socket.
        $this->assertSame(
            'wss://api.deepgram.com/v1/speak?model=aura-2-thalia-en&encoding=linear16&sample_rate=24000',
            $response->json('session.ttsUrl'),
        );
        $response->assertJsonPath('session.outputSampleRate', 24000);

        // The first call streams the greeting; its recording is queued, and
        // every call after plays it from storage.
        $response->assertJsonPath('session.greeting.audioUrl', null);
        $this->assertDatabaseHas('voice_lines', ['ai_scenario_id' => $this->scenario->id, 'text' => 'Hello! How may I help you?', 'reusable' => false]);
        Queue::assertPushed(RecordVoiceLine::class);
        Queue::assertPushed(WarmVoiceLines::class, fn (WarmVoiceLines $job): bool => $job->voice === self::VOICE);

        $this->assertStringNotContainsString(self::DEEPGRAM_KEY, (string) $response->getContent());
        $this->assertStringNotContainsString(self::QWEN_KEY, (string) $response->getContent());
    }

    public function test_the_greeting_is_voiced_once_and_replayed_from_storage_after()
    {
        $bank = app(VoiceLineBank::class);

        $first = $bank->greetingUrl($this->scenario, self::VOICE, 'Good evening! I need some help.');
        $second = $bank->greetingUrl($this->scenario, self::VOICE, 'Good evening! I need some help.');

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
        $this->assertSame(1, AudioClip::query()->count());
        $this->assertSame(1, VoiceLine::query()->count());
        $this->assertDatabaseCount('ai_usages', 1);
        $this->assertSame(2, VoiceLine::query()->firstOrFail()->uses);
    }

    public function test_the_ai_is_offered_the_recorded_lines_and_the_one_it_picks_plays_without_new_speech()
    {
        $this->useQwen();
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);
        $line = $this->recordedLine('I would like to check in, please.');
        $this->recordedLine('This line mentions Samira by name.', reusable: false);
        $this->qwenContent = '{"reuse": 1}';

        $response = $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Good evening, how can I help you?'])
            ->assertOk()
            ->assertJsonPath('source', VoiceReplyService::SOURCE_REUSED)
            ->assertJsonPath('text', 'I would like to check in, please.')
            ->assertJsonPath('stale', false)
            ->assertJsonPath('limitReached', false);

        $this->assertSame($line->url(), $response->json('audioUrl'));
        $this->assertStringNotContainsString(self::QWEN_KEY, (string) $response->getContent());

        Http::assertSent(function (HttpRequest $request): bool {
            if (! str_contains($request->url(), 'qwen.test')) {
                return false;
            }

            $system = (string) $request['messages'][0]['content'];
            $last = $request['messages'][count($request['messages']) - 1];

            return $request['model'] === 'qwen-flash-test'
                && $request->hasHeader('Authorization', 'Bearer '.self::QWEN_KEY)
                && $request['enable_thinking'] === false
                && str_contains($system, RoleplayPrompt::GUARD)
                && str_contains($system, '1. "I would like to check in, please."')
                // A line tied to one conversation is never offered.
                && ! str_contains($system, 'Samira')
                && $last === ['role' => 'user', 'content' => 'Good evening, how can I help you?'];
        });

        // Nothing was voiced: the recording already existed.
        Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/v1/speak'));
        $this->assertDatabaseMissing('ai_usages', ['feature' => 'tts']);
        $this->assertDatabaseHas('ai_usages', ['feature' => 'roleplay_turn', 'provider' => 'qwen_voice_pipeline', 'prompt_tokens' => 120]);

        $line->refresh();
        $this->assertSame(1, $line->reuses);

        $transcript = $attempt->refresh()->transcript;
        $this->assertSame([0, 1, 2], array_column($transcript, 'seq'));
        $this->assertSame(['guest', 'employee', 'guest'], array_column($transcript, 'role'));
        $this->assertSame($line->audioClip?->media_asset_id, $transcript[2]['audio_media_id'] ?? null);
    }

    public function test_a_new_line_is_voiced_once_stored_and_offered_later_only_when_the_ai_keeps_it()
    {
        $this->useQwen();
        config()->set('services.tts.provider', 'deepgram');
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);

        $this->qwenContent = '{"say": "My name is John Carter, room 204.", "keep": false}';
        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Good evening, can I have your name?'])
            ->assertOk()
            ->assertJsonPath('source', VoiceReplyService::SOURCE_NEW)
            ->assertJsonPath('text', 'My name is John Carter, room 204.');

        $this->qwenContent = '{"say": "Could I have a room with a sea view, please?", "keep": true}';
        $second = $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 1, 'rev' => 0, 'text' => 'Thank you. What would you like?'])
            ->assertOk();

        $this->assertNotNull($second->json('audioUrl'));

        // Voiced in the call's Aura-2 voice, server-side, with the key in a header.
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'api.deepgram.com/v1/speak?model=aura-2-thalia-en&encoding=mp3')
            && $request->hasHeader('Authorization', 'Token '.self::DEEPGRAM_KEY)
            && $request['text'] === 'Could I have a room with a sea view, please?');

        $this->assertDatabaseHas('voice_lines', ['text' => 'My name is John Carter, room 204.', 'reusable' => false]);
        $this->assertDatabaseHas('voice_lines', ['text' => 'Could I have a room with a sea view, please?', 'reusable' => true]);
        $this->assertDatabaseHas('audio_clips', ['text' => 'Could I have a room with a sea view, please?', 'voice' => self::VOICE, 'provider' => 'deepgram', 'status' => 'done']);
        $this->assertSame(2, AiUsage::query()->where('feature', 'tts')->count());

        // The next turn offers the kept line and never the personal one.
        $this->qwenContent = '{"reuse": 1}';
        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 2, 'rev' => 0, 'text' => 'Certainly.'])
            ->assertOk()
            ->assertJsonPath('source', VoiceReplyService::SOURCE_REUSED)
            ->assertJsonPath('text', 'Could I have a room with a sea view, please?');

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'qwen.test')
            && str_contains((string) $request['messages'][0]['content'], 'sea view')
            && ! str_contains((string) $request['messages'][0]['content'], 'John Carter'));
    }

    public function test_a_new_line_is_returned_at_once_for_streaming_and_recorded_in_the_background()
    {
        Queue::fake();
        $this->useQwen();
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);

        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Hello, welcome.'])
            ->assertOk()
            ->assertJsonPath('source', VoiceReplyService::SOURCE_NEW)
            ->assertJsonPath('text', 'Good evening, I have a booking.')
            ->assertJsonPath('audioUrl', null);

        // The reply never waited for the speech file.
        Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/v1/speak'));

        $clip = AudioClip::query()->where('text', 'Good evening, I have a booking.')->firstOrFail();
        Queue::assertPushed(RecordVoiceLine::class, fn (RecordVoiceLine $job): bool => $job->clipId === $clip->id && $job->userId === $learner->id);

        // The queued job records it; the next call gets the stored file.
        (new RecordVoiceLine($clip->id, $learner->id))->handle(app(VoiceLineBank::class));
        $this->assertNotNull(VoiceLine::query()->where('audio_clip_id', $clip->id)->firstOrFail()->url());
        $this->assertDatabaseHas('ai_usages', ['feature' => 'tts', 'user_id' => $learner->id]);
    }

    public function test_a_spent_fast_model_falls_back_to_the_main_model()
    {
        $this->useQwen();
        config()->set('services.ai.model', 'qwen-main-test');
        $this->spentModel = 'qwen-flash-test';
        $this->qwenContent = json_encode(['say' => 'Good evening, I have a booking.', 'keep' => false]) ?: '';
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);

        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Hello, welcome.'])
            ->assertOk()
            ->assertJsonPath('limitReached', false)
            ->assertJsonPath('text', 'Good evening, I have a booking.');

        $this->assertTrue(app(VoiceAgentSettings::class)->pipelineAvailable());
    }

    public function test_a_spent_qwen_quota_moves_the_next_calls_to_the_voice_agent()
    {
        $this->useQwen();
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);
        $this->qwenQuotaSpent = true;

        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Hello, welcome.'])
            ->assertOk()
            // The call closes politely instead of breaking on an error.
            ->assertJsonPath('limitReached', true)
            ->assertJsonPath('endReason', 'quota')
            ->assertJsonPath('text', VoiceReplyService::CLOSING_LINE);

        // The employee's words are kept.
        $this->assertSame('Hello, welcome.', $attempt->refresh()->transcript[1]['text']);

        // The next call runs on Deepgram's own model instead of failing.
        $this->actingAs($learner)
            ->postJson($this->startUrl())
            ->assertOk()
            ->assertJsonPath('session.engine', 'agent')
            ->assertJsonPath('session.settings.agent.think.provider.type', 'open_ai');

        // The admin can still save the fast engine meanwhile.
        $this->assertTrue(app(VoiceAgentSettings::class)->pipelineAvailable(false));
        $this->assertFalse(app(VoiceAgentSettings::class)->pipelineAvailable());
    }

    public function test_a_speech_outage_still_returns_the_line_for_the_browser_to_read()
    {
        $this->useQwen();
        config()->set('services.tts.provider', 'deepgram');
        $this->speakStatus = 500;
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);

        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Hello, welcome.'])
            ->assertOk()
            ->assertJsonPath('text', 'Good evening, I have a booking.')
            ->assertJsonPath('audioUrl', null);

        $this->assertSame('Good evening, I have a booking.', $attempt->refresh()->transcript[2]['text']);
    }

    public function test_a_speculative_reply_overtaken_by_the_confirmed_one_never_overwrites_it()
    {
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);
        $url = $this->replyUrl($attempt);

        $this->actingAs($learner)->postJson($url, ['turn' => 0, 'rev' => 1, 'text' => 'Good evening, welcome.'])->assertOk()->assertJsonPath('stale', false);
        $this->actingAs($learner)->postJson($url, ['turn' => 0, 'rev' => 0, 'text' => 'Good'])->assertOk()->assertJsonPath('stale', true);

        $this->assertSame('Good evening, welcome.', $attempt->refresh()->transcript[1]['text']);

        // A later request for the same turn replaces it; the turn is never doubled.
        $this->actingAs($learner)->postJson($url, ['turn' => 0, 'rev' => 2, 'text' => 'Good evening, welcome to the hotel.'])->assertOk();

        $transcript = $attempt->refresh()->transcript;
        $this->assertCount(3, $transcript);
        $this->assertSame('Good evening, welcome to the hotel.', $transcript[1]['text']);
    }

    public function test_the_daily_allowance_ends_the_call_with_the_closing_line()
    {
        config()->set('guesvia.ai.limits.per_employee_daily_turns', 0);
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner);

        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Hello.'])
            ->assertOk()
            ->assertJsonPath('limitReached', true)
            ->assertJsonPath('text', VoiceReplyService::CLOSING_LINE);
    }

    public function test_another_learner_a_manager_and_an_admin_route_are_refused()
    {
        $owner = $this->learner();
        $attempt = $this->voiceCall($owner);
        $body = ['turn' => 0, 'rev' => 0, 'text' => 'Hello.'];

        $intruder = $this->learner(['username' => 'intruder']);
        $this->actingAs($intruder)->postJson($this->replyUrl($attempt), $body)->assertForbidden();

        $manager = User::factory()->manager()->firstLoginDone()->forHotel($this->hotel, $this->department)->create();
        $this->actingAs($manager)->postJson($this->replyUrl($attempt), $body)->assertForbidden();

        // The admin test-call route only answers the admin's own preview.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)
            ->postJson(route('ai-scenarios.voice-preview.reply', ['attempt' => $attempt]), $body)
            ->assertForbidden();

        $this->assertCount(1, $attempt->refresh()->transcript);
    }

    public function test_an_ended_call_takes_no_more_replies()
    {
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner, ['status' => RoleplayStatus::Evaluating]);

        $this->actingAs($learner)
            ->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Hello.'])
            ->assertStatus(409);
    }

    public function test_the_admin_test_call_runs_on_the_fast_engine_through_its_own_route()
    {
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();

        $start = $this->actingAs($admin)
            ->postJson(route('ai-scenarios.voice-preview.start', ['scenario' => $this->scenario]))
            ->assertOk()
            ->assertJsonPath('session.engine', 'pipeline');

        $attempt = RoleplayAttempt::query()->findOrFail($start->json('attemptId'));
        $this->assertTrue($attempt->is_preview);

        $this->actingAs($admin)
            ->postJson((string) $start->json('replyUrl'), ['turn' => 0, 'rev' => 0, 'text' => 'Good evening.'])
            ->assertOk()
            ->assertJsonPath('stale', false);
    }

    public function test_the_voice_agent_takes_over_when_no_text_model_is_configured()
    {
        config()->set('services.ai.provider', 'qwen');
        config()->set('services.ai.key', '');

        $this->actingAs($this->learner())
            ->postJson($this->startUrl())
            ->assertOk()
            ->assertJsonPath('session.engine', 'agent');
    }

    public function test_ending_a_fast_call_meters_the_streamed_seconds_under_the_flux_model()
    {
        Queue::fake();
        $learner = $this->learner();
        $attempt = $this->voiceCall($learner, ['started_at' => now()->subSeconds(90)]);
        $this->actingAs($learner)->postJson($this->replyUrl($attempt), ['turn' => 0, 'rev' => 0, 'text' => 'Good evening.'])->assertOk();

        $this->actingAs($learner)
            ->post(route('learn.roleplay.voice.end', ['attempt' => $attempt]))
            ->assertRedirect(route('learn.roleplay.feedback', ['attempt' => $attempt->id]));

        Queue::assertPushed(EvaluateRoleplayAttempt::class);
        $this->assertDatabaseHas('ai_usages', ['feature' => 'voice_call', 'model' => UsageMeter::VOICE_STREAM_MODEL]);
    }
}
