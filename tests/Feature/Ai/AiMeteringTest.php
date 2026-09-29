<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiUsageInfo;
use App\Enums\ActivityType;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Jobs\GenerateLexiconDraft;
use App\Jobs\TranscribeAndEvaluateSpokenAnswer;
use App\Models\Activity;
use App\Models\AiModelPrice;
use App\Models\AiModelSetting;
use App\Models\AiScenario;
use App\Models\AiUsage;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Models\Attempt;
use App\Models\AudioClip;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Ai\AiUsageReport;
use App\Services\Ai\UsageMeter;
use App\Services\Audio\AudioLibrary;
use App\Services\Learning\RoleplayService;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\CreditSummary;
use App\Services\VoiceAgent\VoiceCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Every AI, speech and voice call lands in the ai_usages ledger once, under
 * the account that billed it and at the owner's prices, so the Super Admin's
 * "AI cost and points this month" card and her AI credit go down (API-03,
 * AIL-01, AIL-04; spec 0007 D4–D11).
 *
 * The configuration is the live one: `.env` says `fake` for every provider
 * and the Super Admin switched each capability to real on Settings → AI
 * models, with the Qwen token-plan host as the AI base URL. Every provider
 * is faked at the HTTP layer, so the real provider classes run.
 */
class AiMeteringTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    private const QWEN_HOST = 'https://token-plan.ap-southeast-1.maas.aliyuncs.com/compatible-mode/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        Storage::fake(MediaAsset::DISK_LOCAL);
        Storage::fake(MediaAsset::DISK_PUBLIC);

        config([
            'services.ai.provider' => 'fake',
            'services.ai.base_url' => self::QWEN_HOST,
            'services.ai.model' => 'qwen3.8-max',
            'services.ai.fast_model' => 'qwen3.8-flash',
            'services.ai.key' => 'qwen-key',
            'services.ai.image_provider' => 'fake',
            'services.tts.provider' => 'fake',
            'services.tts.key' => 'deepgram-key',
            'services.tts.model' => 'flux-hannah-en',
            'services.stt.provider' => 'fake',
            'services.stt.key' => 'deepgram-key',
            'services.stt.model' => null,
            'services.voice_agent.key' => 'deepgram-key',
        ]);

        $this->switches(['aiMode' => 'real', 'ttsMode' => 'real', 'sttMode' => 'real']);

        Http::fake([
            'token-plan.ap-southeast-1.maas.aliyuncs.com/*' => fn ($request) => Http::response([
                'model' => $request['model'],
                'choices' => [['message' => ['role' => 'assistant', 'content' => self::qwenJson()]]],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 200],
            ]),
            'api.deepgram.com/v2/speak*' => Http::response('ID3-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
            'api.deepgram.com/v1/listen*' => Http::response(['results' => ['channels' => [['alternatives' => [[
                'transcript' => 'Good evening, welcome to the hotel.',
                'words' => [],
            ]]]]]]),
        ]);

        // The owner's prices, per million units (spec 0007, D8/D9).
        foreach ([
            ['qwen3.8-flash', 'tokens', 0.5, 2.0],
            ['qwen3.8-max', 'tokens', 2.0, 6.0],
            ['flux-*', 'characters', 30000, 0],
            ['nova-3', 'seconds', 120, 0],
            [UsageMeter::VOICE_AGENT_MODEL, 'seconds', 1250, 0],
        ] as [$model, $unit, $input, $output]) {
            AiModelPrice::query()->create(['model' => $model, 'unit' => $unit, 'input_per_million' => $input, 'output_per_million' => $output]);
        }

        $this->recharge(ApiAccount::Qwen, 300);
        $this->recharge(ApiAccount::Deepgram, 400);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function switches(array $values): void
    {
        AiModelSetting::query()->updateOrCreate(['id' => 1], ['values' => $values]);
    }

    /**
     * One JSON answer every OpenAI-compatible call in these tests parses.
     */
    private static function qwenJson(): string
    {
        return json_encode([
            'reply' => 'Good evening, I have a booking.',
            'arabic_meaning' => 'مساء الخير',
            'simple_explanation' => 'A greeting in the evening.',
            'hotel_example' => 'Good evening, sir. Welcome.',
        ], JSON_THROW_ON_ERROR);
    }

    private function recharge(ApiAccount $account, float $usd): void
    {
        $setting = ApiAccountSetting::for($account);
        $setting->metering_started_at ??= Date::now()->subMinute();
        $setting->save();

        ApiCreditTopup::query()->create(['account' => $account, 'amount_usd' => $usd]);
    }

    /**
     * @return array{account: string, service: string, provider: string, state: string, mode: string, creditUsd: float, remainingUsd: float|null, usedUsd: float|null, shareLeft: float|null, meters: list<array{meter: string, label: string, granted: int, left: int}>}
     */
    private function credit(ApiAccount $account): array
    {
        app(ApiCredit::class)->forget();

        return app(CreditSummary::class)->present($account);
    }

    /**
     * @return array<string, array{points: int, costUsd: float, priceComplete: bool}>
     */
    private function spend(): array
    {
        return app(AiUsageReport::class)->dashboardPointSpend();
    }

    private function scenario(): AiScenario
    {
        return AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
        ]);
    }

    public function test_a_text_role_play_turn_bills_qwen_and_charges_the_learners_points()
    {
        $learner = $this->learner();

        app(RoleplayService::class)->start($learner, $this->scenario(), null, null);

        $row = AiUsage::query()->forFeature(AiFeature::RoleplayTurn)->sole();
        $this->assertSame('qwen', $row->provider);
        $this->assertSame('qwen3.8-flash', $row->model);
        $this->assertSame($learner->id, $row->user_id);
        $this->assertSame($this->hotel->id, $row->hotel_id);
        $this->assertSame(1000, $row->prompt_tokens);
        $this->assertSame(200, $row->completion_tokens);
        // (1000 × $0.50 + 200 × $2.00) / 1M.
        $this->assertEqualsWithDelta(0.0009, (float) $row->cost_estimate, 1e-9);
        $this->assertSame(50, $row->points_charged);

        $spend = $this->spend();
        $this->assertEqualsWithDelta(0.0009, $spend['llm']['costUsd'], 1e-9);
        $this->assertSame(50, $spend['llm']['points']);
        $this->assertTrue($spend['llm']['priceComplete']);

        $qwen = $this->credit(ApiAccount::Qwen);
        $this->assertEqualsWithDelta(0.0009, $qwen['usedUsd'], 1e-9);
        $this->assertEqualsWithDelta(299.9991, $qwen['remainingUsd'], 1e-9);
    }

    public function test_an_admins_generation_is_metered_under_the_admin_without_points()
    {
        $admin = User::factory()->superAdmin()->create();
        $item = LexiconItem::factory()->create(['created_by' => $admin->id]);

        GenerateLexiconDraft::dispatch($item->id);

        $row = AiUsage::query()->forFeature(AiFeature::LexiconGenerate)->sole();
        $this->assertSame('qwen', $row->provider);
        $this->assertSame('qwen3.8-max', $row->model);
        $this->assertSame($admin->id, $row->user_id);
        $this->assertSame(0, $row->points_charged);
        // (1000 × $2.00 + 200 × $6.00) / 1M.
        $this->assertEqualsWithDelta(0.0032, (float) $row->cost_estimate, 1e-9);

        $this->assertEqualsWithDelta(0.0032, $this->spend()['llm']['costUsd'], 1e-9);
        $this->assertEqualsWithDelta(0.0032, $this->credit(ApiAccount::Qwen)['usedUsd'], 1e-9);
    }

    public function test_lesson_audio_bills_deepgram_although_env_says_fake()
    {
        $text = 'Welcome to the hotel.';

        app(AudioLibrary::class)->ensureBoth($text);

        $rows = AiUsage::query()->forFeature(AiFeature::Tts)->get();
        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame('deepgram', $row->provider);
            $this->assertSame(mb_strlen($text), $row->prompt_tokens);
        }

        // 21 characters × 2 speeds × $30,000 per million.
        $expected = 2 * 21 * 30000 / 1_000_000;
        $this->assertEqualsWithDelta($expected, $this->spend()['speech']['costUsd'], 1e-9);

        $deepgram = $this->credit(ApiAccount::Deepgram);
        $this->assertEqualsWithDelta($expected, $deepgram['usedUsd'], 1e-9);
        $this->assertEqualsWithDelta(400 - $expected, $deepgram['remainingUsd'], 1e-9);
    }

    public function test_a_spoken_answer_transcription_bills_deepgram_in_seconds()
    {
        // Evaluation on the fake text AI: only the listening is under test.
        $this->switches(['aiMode' => 'fake', 'ttsMode' => 'real', 'sttMode' => 'real']);
        $learner = $this->learner();

        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);
        $activity = Activity::factory()->ofType(ActivityType::Speaking)->create(['show_meaning_enabled' => false]);
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);
        $asset = MediaAsset::factory()->recording()->create(['uploaded_by' => $learner->id]);
        Storage::disk(MediaAsset::DISK_LOCAL)->put($asset->path, 'webm-bytes');

        $attempt = Attempt::factory()->create([
            'user_id' => $learner->id,
            'activity_id' => $activity->id,
            'activity_version_id' => $activity->currentVersion()->firstOrFail()->id,
            'test_attempt_id' => TestAttempt::factory()->create(['user_id' => $learner->id, 'test_id' => $test->id])->id,
            'raw_answer' => ['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 5200]],
            'response_media_id' => $asset->id,
            'transcript' => null,
            'ai_status' => GenerationStatus::Pending,
        ]);

        TranscribeAndEvaluateSpokenAnswer::dispatch($attempt->id);

        $row = AiUsage::query()->forFeature(AiFeature::Stt)->sole();
        $this->assertSame('deepgram', $row->provider);
        $this->assertSame('nova-3', $row->model);
        $this->assertSame(6, $row->prompt_tokens);
        $this->assertEqualsWithDelta(6 * 120 / 1_000_000, (float) $row->cost_estimate, 1e-12);
        // Her credit figures are kept to four decimals.
        $this->assertEqualsWithDelta(6 * 120 / 1_000_000, $this->credit(ApiAccount::Deepgram)['usedUsd'], 1e-4);
        $this->assertLessThan(400, $this->credit(ApiAccount::Deepgram)['remainingUsd']);

        // The evaluation ran on the fake AI: free, and billed to nobody.
        $evaluation = AiUsage::query()->forFeature(AiFeature::SpeakingEval)->sole();
        $this->assertSame('fake', $evaluation->provider);
        $this->assertEqualsWithDelta(0.0, (float) $evaluation->cost_estimate, 1e-12);
    }

    public function test_a_voice_call_is_metered_and_charged_when_it_ends()
    {
        $this->freezeSecond();
        $learner = $this->learner();
        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario()->id,
            'channel' => RoleplayAttempt::CHANNEL_VOICE_CALL,
            'started_at' => Date::now()->subMinutes(3),
            'transcript' => [
                ['role' => RoleplayAttempt::ROLE_GUEST, 'text' => 'Good evening.', 'seq' => 1],
                ['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => 'Welcome to the hotel.', 'seq' => 2],
            ],
        ]);

        app(VoiceCallService::class)->end($attempt);

        $row = AiUsage::query()->forFeature(AiFeature::VoiceCall)->sole();
        $this->assertSame('deepgram', $row->provider);
        $this->assertSame(180, $row->prompt_tokens);

        $spend = $this->spend();
        $this->assertEqualsWithDelta(180 * 1250 / 1_000_000, $spend['voiceAgent']['costUsd'], 1e-9);
        // One started ten-minute block at the plan's 100 points.
        $this->assertSame(100, $spend['voiceAgent']['points']);
        $this->assertEqualsWithDelta(180 * 1250 / 1_000_000, $this->credit(ApiAccount::Deepgram)['usedUsd'], 1e-9);
    }

    public function test_a_call_left_open_is_closed_as_of_its_last_caption_and_metered_once()
    {
        $learner = $this->learner();
        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario()->id,
            'channel' => RoleplayAttempt::CHANNEL_VOICE_CALL,
            'started_at' => Date::now()->subMinutes(45),
            'transcript' => [
                ['role' => RoleplayAttempt::ROLE_GUEST, 'text' => 'Good evening.', 'seq' => 1],
                ['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => 'Welcome to the hotel.', 'seq' => 2],
            ],
        ]);
        // The last caption came five minutes into the call; then the tab closed.
        RoleplayAttempt::query()->whereKey($attempt->id)->update(['updated_at' => Date::now()->subMinutes(40)]);

        // Still inside the idle window: left alone.
        $this->assertSame(0, app(VoiceCallService::class)->closeStale(null, 60));

        $this->assertSame(1, app(VoiceCallService::class)->closeStale());
        $this->assertSame(0, app(VoiceCallService::class)->closeStale());

        $attempt->refresh();
        $this->assertNotSame(RoleplayStatus::InProgress, $attempt->status);
        $this->assertSame(300, (int) round($attempt->duration_ms / 1000));
        $this->assertSame(100, $attempt->ai_points_charged);

        $row = AiUsage::query()->forFeature(AiFeature::VoiceCall)->sole();
        $this->assertSame(300, $row->prompt_tokens);
    }

    public function test_starting_a_new_call_closes_the_learners_call_left_open()
    {
        $learner = $this->learner();
        $scenario = $this->scenario();
        $stale = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $scenario->id,
            'channel' => RoleplayAttempt::CHANNEL_VOICE_CALL,
            'started_at' => Date::now()->subHours(2),
            'transcript' => [['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => 'Hello.', 'seq' => 1]],
        ]);
        RoleplayAttempt::query()->whereKey($stale->id)->update(['updated_at' => Date::now()->subHour()]);

        app(VoiceCallService::class)->start($learner, $scenario, null, null);

        $this->assertNotSame(RoleplayStatus::InProgress, $stale->refresh()->status);
        $this->assertSame(1, AiUsage::query()->forFeature(AiFeature::VoiceCall)->count());
    }

    public function test_the_dashboard_counts_the_current_month_only()
    {
        AiUsage::factory()->create([
            'provider' => 'qwen',
            'model' => 'qwen3.8-flash',
            'feature' => AiFeature::RoleplayTurn,
            'prompt_tokens' => 1000,
            'completion_tokens' => 200,
            'cost_estimate' => 0.0009,
            'points_charged' => 50,
            'occurred_at' => Date::now()->startOfMonth()->subMinute(),
        ]);

        $this->assertSame(0, $this->spend()['llm']['points']);
        $this->assertEqualsWithDelta(0.0, $this->spend()['llm']['costUsd'], 1e-12);

        AiUsage::factory()->create([
            'provider' => 'qwen',
            'model' => 'qwen3.8-flash',
            'feature' => AiFeature::RoleplayTurn,
            'prompt_tokens' => 1000,
            'completion_tokens' => 200,
            // Written before the price existed: costed at today's price.
            'cost_estimate' => 0,
            'points_charged' => 50,
            'occurred_at' => Date::now()->startOfMonth()->addMinute(),
        ]);

        $this->assertSame(50, $this->spend()['llm']['points']);
        $this->assertEqualsWithDelta(0.0009, $this->spend()['llm']['costUsd'], 1e-9);
    }

    public function test_a_fake_call_costs_nothing_whatever_model_it_names()
    {
        $row = app(UsageMeter::class)->record(null, AiFeature::Tts, new AiUsageInfo(500, 0, 'flux-hannah-en', 'fake'), chargePoints: false);

        $this->assertEqualsWithDelta(0.0, (float) $row->cost_estimate, 1e-12);
        $this->assertEqualsWithDelta(0.0, $this->spend()['speech']['costUsd'], 1e-12);
        $this->assertTrue($this->spend()['speech']['priceComplete']);
    }

    public function test_the_openai_compatible_label_on_alibabas_host_bills_qwen()
    {
        config(['services.ai.provider' => 'openai']);
        $this->switches([]);

        app(RoleplayService::class)->start($this->learner(), $this->scenario(), null, null);

        $this->assertSame('qwen', AiUsage::query()->forFeature(AiFeature::RoleplayTurn)->sole()->provider);
        $this->assertEqualsWithDelta(0.0009, $this->credit(ApiAccount::Qwen)['usedUsd'], 1e-9);
        $this->assertSame(ApiAccount::Qwen, app(ApiCredit::class)->accountFor('ai'));
    }

    public function test_a_price_matches_the_model_id_whatever_its_case_or_vendor_prefix()
    {
        $prices = AiModelPrice::byModel();

        $this->assertSame('qwen3.8-flash', AiModelPrice::lookup($prices, 'Qwen3.8-Flash')?->model);
        $this->assertSame('qwen3.8-flash', AiModelPrice::lookup($prices, 'qwen/qwen3.8-flash')?->model);
        $this->assertSame('flux-*', AiModelPrice::lookup($prices, 'FLUX-hannah-en')?->model);
        $this->assertNull(AiModelPrice::lookup($prices, 'qwen3.8-plus'));
    }

    public function test_the_repair_command_relabels_real_audio_once_and_reports()
    {
        $asset = MediaAsset::factory()->audio()->create(['mime' => 'audio/mpeg']);
        $clip = AudioClip::factory()->done()->create([
            'text' => 'Welcome to the hotel.',
            'text_hash' => AudioClip::hashFor('Welcome to the hotel.'),
            'voice' => 'flux-hannah-en',
            'media_asset_id' => $asset->id,
            'generated_at' => Date::now(),
        ]);
        // Written before the fix: real Deepgram audio labelled `fake`.
        $mislabelled = AiUsage::factory()->create([
            'provider' => 'fake',
            'model' => 'flux-hannah-en',
            'feature' => AiFeature::Tts,
            'prompt_tokens' => mb_strlen($clip->text),
            'completion_tokens' => 0,
            'cost_estimate' => 0,
            'points_charged' => 0,
            'occurred_at' => Date::now()->subSeconds(2),
        ]);
        // A real fake tone stays fake.
        $tone = AudioClip::factory()->done()->create(['voice' => 'flux-hannah-en']);
        $fakeRow = AiUsage::factory()->create([
            'provider' => 'fake',
            'model' => 'flux-hannah-en',
            'feature' => AiFeature::Tts,
            'prompt_tokens' => mb_strlen($tone->text),
            'completion_tokens' => 0,
            'cost_estimate' => 0.5,
            'occurred_at' => Date::now()->subSeconds(2),
        ]);

        $this->assertEqualsWithDelta(0.0, $this->credit(ApiAccount::Deepgram)['usedUsd'], 1e-12);

        $this->artisan('ai:usage-repair', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('fake', $mislabelled->refresh()->provider);

        $this->artisan('ai:usage-repair')
            ->expectsOutputToContain('lesson audio rows relabelled to the real provider: 1')
            ->assertSuccessful();

        $mislabelled->refresh();
        $this->assertSame('deepgram', $mislabelled->provider);
        $this->assertEqualsWithDelta(21 * 30000 / 1_000_000, (float) $mislabelled->cost_estimate, 1e-9);
        $this->assertSame('fake', $fakeRow->refresh()->provider);
        $this->assertEqualsWithDelta(0.0, (float) $fakeRow->cost_estimate, 1e-12);
        $this->assertEqualsWithDelta(21 * 30000 / 1_000_000, $this->credit(ApiAccount::Deepgram)['usedUsd'], 1e-9);

        // Idempotent.
        $this->artisan('ai:usage-repair')
            ->expectsOutputToContain('lesson audio rows relabelled to the real provider: 0')
            ->assertSuccessful();
        $this->assertSame(1, AiUsage::query()->where('provider', 'deepgram')->count());
    }
}
