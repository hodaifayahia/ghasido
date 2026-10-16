<?php

namespace Tests\Feature\Owner;

use App\Contracts\AiProvider;
use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Models\AiModelPrice;
use App\Models\AiModelSetting;
use App\Models\AiUsage;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\UsageMeter;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\ApiKeyring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Claude (Anthropic) as the text AI, funded from its own owner account: its
 * calls and the Deepgram voices take dollars off the credit at the default
 * prices (API-03, AIL-04; spec 0007, D4–D9).
 */
class ClaudeCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.provider' => 'anthropic',
            'services.ai.model' => 'claude-sonnet-5-5',
            'services.ai.fast_model' => null,
            'services.ai.base_url' => null,
            'services.ai.key' => 'env-claude-key',
            'services.ai.image_provider' => 'qwen',
            'services.ai.image_key' => 'env-qwen-key',
            'services.tts.provider' => 'deepgram',
            'services.tts.key' => 'env-deepgram-key',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'model' => 'claude-sonnet-5-5',
                'content' => [['type' => 'text', 'text' => '{"ok": true}']],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 1000,
                    'cache_read_input_tokens' => 4000,
                    'cache_creation_input_tokens' => 0,
                    'output_tokens' => 500,
                ],
            ]),
        ]);
    }

    private function recharge(ApiAccount $account, float $usd): void
    {
        $setting = ApiAccountSetting::for($account);
        $setting->metering_started_at ??= Date::now()->subMinute();
        $setting->save();

        ApiCreditTopup::query()->create(['account' => $account, 'amount_usd' => $usd]);
    }

    private function remaining(ApiAccount $account): ?float
    {
        $credit = app(ApiCredit::class);
        $credit->forget();

        return $credit->balance($account)->remainingUsd();
    }

    public function test_default_prices_cover_claude_and_the_deepgram_voices()
    {
        $sonnet = AiModelPrice::for('claude-sonnet-5-5');
        $this->assertSame('2.0000', $sonnet?->input_per_million);
        $this->assertSame('10.0000', $sonnet->output_per_million);

        // Speech per character, listening per second; the exact Flux
        // listening id wins over the `flux-*` voice pattern.
        $this->assertSame(['characters', '45.0000'], [AiModelPrice::for('flux-hannah-en')?->unit, AiModelPrice::for('flux-hannah-en')?->input_per_million]);
        $this->assertSame('30.0000', AiModelPrice::for('aura-2-thalia-en')?->input_per_million);
        $this->assertSame('seconds', AiModelPrice::for('flux-general-en')?->unit);
        $this->assertSame('seconds', AiModelPrice::for('nova-3')?->unit);
        $this->assertSame('1250.0000', AiModelPrice::for(UsageMeter::VOICE_AGENT_MODEL)?->input_per_million);
    }

    public function test_a_claude_call_takes_its_cost_off_the_claude_dollars()
    {
        $this->recharge(ApiAccount::Anthropic, 100);
        $this->recharge(ApiAccount::Qwen, 300);

        $reply = app(AiProvider::class)->ping();
        $row = app(UsageMeter::class)->record(null, AiFeature::LexiconGenerate, $reply->usage, chargePoints: false);

        // 1,000 fresh input at $2/M, 4,000 cache reads at a tenth of it,
        // 500 output at $10/M.
        $this->assertSame('anthropic', $row->provider);
        $this->assertSame(5000, $row->prompt_tokens);
        $this->assertEqualsWithDelta(0.0078, (float) $row->cost_estimate, 1e-9);
        $this->assertEqualsWithDelta(99.9922, $this->remaining(ApiAccount::Anthropic), 1e-6);
        $this->assertEqualsWithDelta(300.0, $this->remaining(ApiAccount::Qwen), 1e-9);

        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('x-api-key', 'env-claude-key')
            && $request['model'] === 'claude-sonnet-5-5'
            && $request['fallbacks'] === 'default'
            && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01'));
    }

    public function test_speech_takes_its_cost_off_the_deepgram_dollars()
    {
        $this->recharge(ApiAccount::Deepgram, 400);

        // 1,000 characters of a Flux voice at $0.045 per 1,000.
        app(UsageMeter::class)->record(null, AiFeature::Tts, new AiUsageInfo(1000, 0, 'flux-hannah-en', 'deepgram'), chargePoints: false);

        $this->assertEqualsWithDelta(399.955, $this->remaining(ApiAccount::Deepgram), 1e-6);
    }

    public function test_the_owners_claude_key_drives_the_text_ai_and_qwen_keeps_the_images()
    {
        ApiAccountSetting::for(ApiAccount::Anthropic)->fill(['api_key' => 'owner-claude-key'])->save();
        ApiAccountSetting::for(ApiAccount::Qwen)->fill(['api_key' => 'owner-qwen-key'])->save();

        $keys = app(ApiKeyring::class);
        $keys->forget();

        $this->assertSame('owner-claude-key', $keys->key('services.ai.key'));
        $this->assertSame('owner-qwen-key', $keys->key('services.ai.image_key'));
        $this->assertSame(ApiAccount::Anthropic, app(ApiCredit::class)->accountFor('ai'));
        $this->assertSame(ApiAccount::Qwen, app(ApiCredit::class)->accountFor('image'));
    }

    public function test_qwen_never_shows_the_claude_env_key_on_the_console()
    {
        config(['services.ai.image_key' => null]);

        $this->assertSame('none', app(ApiKeyring::class)->source(ApiAccount::Qwen));
        $this->assertSame('env', app(ApiKeyring::class)->source(ApiAccount::Anthropic));
    }

    public function test_spent_claude_credit_stops_the_text_ai()
    {
        $this->recharge(ApiAccount::Anthropic, 1);
        AiUsage::factory()->create(['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'cost_estimate' => 1.5]);

        $this->expectException(AiLimitReached::class);

        app(AiProvider::class);
    }

    public function test_a_qwen_model_override_is_never_sent_to_claude()
    {
        AiModelSetting::query()->create(['values' => ['aiModel' => 'qwen3.8-max', 'aiFastModel' => 'qwen3.8-flash']]);

        $this->assertSame(['model' => 'claude-sonnet-5-5', 'fast' => ''], app(AiModelSettings::class)->aiModels('anthropic'));
        $this->assertSame('claude-sonnet-5-5', app(AiModelSettings::class)->fastChatModel());
    }

    public function test_only_checks_that_run_on_an_account_are_offered_for_it()
    {
        $credit = app(ApiCredit::class);

        $this->assertSame(['ai', 'fast'], $credit->checksFor(ApiAccount::Anthropic));
        // Qwen's text check would call Claude now.
        $this->assertSame([], $credit->checksFor(ApiAccount::Qwen));
    }
}
