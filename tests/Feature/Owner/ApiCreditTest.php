<?php

namespace Tests\Feature\Owner;

use App\Contracts\AiProvider;
use App\Contracts\ImageProvider;
use App\Contracts\TtsProvider;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Models\AiModelPrice;
use App\Models\AiUsage;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use App\Services\Content\LessonGenerator;
use App\Services\Owner\ApiCredit;
use App\Services\Tts\FakeTtsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * The owner's credit per paid API account and the stop when it is spent
 * (spec 0007, D4–D7, D9; AIL-03, AIL-04).
 */
class ApiCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.provider' => 'qwen',
            'services.ai.model' => 'qwen3.8-flash',
            'services.ai.key' => 'env-qwen-key',
            'services.ai.image_provider' => 'qwen',
            'services.tts.provider' => 'deepgram',
            'services.tts.key' => 'env-deepgram-key',
        ]);
    }

    private function credit(): ApiCredit
    {
        $credit = app(ApiCredit::class);
        $credit->forget();

        return $credit;
    }

    private function recharge(ApiAccount $account, float $usd = 0, int $tokens = 0): void
    {
        $setting = ApiAccountSetting::for($account);
        $setting->metering_started_at ??= Date::now()->subMinute();
        $setting->save();

        ApiCreditTopup::query()->create(['account' => $account, 'amount_usd' => $usd, 'amount_tokens' => $tokens]);
    }

    private function spend(string $provider, float $cost, int $tokens = 0, string $model = 'qwen3.8-flash', ?\DateTimeInterface $at = null): void
    {
        AiUsage::factory()->create([
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $tokens,
            'completion_tokens' => 0,
            'cost_estimate' => $cost,
            'occurred_at' => $at ?? Date::now(),
        ]);
    }

    public function test_an_account_without_a_recharge_has_no_limit()
    {
        $this->spend('qwen', 500);

        $balance = $this->credit()->balance(ApiAccount::Qwen);

        $this->assertSame('unlimited', $balance->state());
        $this->assertTrue($this->credit()->isAvailable(ApiAccount::Qwen));
        $this->assertEqualsWithDelta(500.0, $balance->spentUsd, 0.0001);
    }

    public function test_spend_since_the_first_recharge_counts_against_the_credit()
    {
        $this->spend('qwen', 90, at: Date::now()->subDays(3));
        $this->recharge(ApiAccount::Qwen, usd: 10);
        $this->spend('qwen', 4);
        $this->spend('qwen_voice_proxy', 1);
        $this->spend('deepgram', 50, model: 'nova-3');

        $balance = $this->credit()->balance(ApiAccount::Qwen);
        $this->assertEqualsWithDelta(5.0, $balance->spentUsd, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $balance->remainingUsd(), 0.0001);
        $this->assertSame('active', $balance->state());

        $this->spend('qwen', 4.5);
        $this->assertSame('low', $this->credit()->balance(ApiAccount::Qwen)->state());

        $this->spend('qwen', 1);
        $this->assertSame('exhausted', $this->credit()->balance(ApiAccount::Qwen)->state());
        $this->assertFalse($this->credit()->isAvailable(ApiAccount::Qwen));
    }

    public function test_a_recharge_makes_a_spent_account_callable_again()
    {
        $this->recharge(ApiAccount::Deepgram, usd: 1);
        $this->spend('deepgram', 2, model: 'nova-3');
        $this->assertFalse($this->credit()->isAvailable(ApiAccount::Deepgram));

        $this->recharge(ApiAccount::Deepgram, usd: 5);
        $this->assertTrue($this->credit()->isAvailable(ApiAccount::Deepgram));
    }

    public function test_unpriced_usage_is_costed_at_the_current_price_and_listed()
    {
        $this->recharge(ApiAccount::Qwen, usd: 100);
        $this->spend('qwen', 0, tokens: 2_000_000, model: 'qwen3.8-max');
        $this->spend('deepgram', 0, tokens: 1000, model: 'aura-2-thalia-en');

        $this->assertSame([['model' => 'qwen3.8-max', 'unit' => 'tokens']], $this->credit()->balance(ApiAccount::Qwen)->unpricedModels);

        AiModelPrice::query()->create(['model' => 'qwen3.8-*', 'unit' => 'tokens', 'input_per_million' => 3, 'output_per_million' => 9]);

        $balance = $this->credit()->balance(ApiAccount::Qwen);
        $this->assertEqualsWithDelta(6.0, $balance->spentUsd, 0.0001);
        $this->assertSame([], $balance->unpricedModels);
    }

    public function test_qwen_credit_can_also_be_counted_in_tokens()
    {
        $this->recharge(ApiAccount::Qwen, tokens: 1000);
        $this->spend('qwen', 0, tokens: 600);

        $balance = $this->credit()->balance(ApiAccount::Qwen);
        $this->assertFalse($balance->limitedByUsd);
        $this->assertSame(400, $balance->remainingTokens());

        // An image is one unit, not tokens: it does not eat the token credit.
        AiUsage::factory()->create(['provider' => 'qwen', 'feature' => AiFeature::ImageGenerate, 'prompt_tokens' => 1, 'completion_tokens' => 0, 'cost_estimate' => 0]);
        $this->assertSame(400, $this->credit()->balance(ApiAccount::Qwen)->remainingTokens());

        $this->spend('qwen', 0, tokens: 400);
        $this->assertFalse($this->credit()->isAvailable(ApiAccount::Qwen));
    }

    public function test_a_paused_account_is_blocked_whatever_its_credit()
    {
        ApiAccountSetting::for(ApiAccount::Deepgram)->forceFill(['paused_at' => Date::now()])->save();

        $this->assertFalse($this->credit()->isAvailable(ApiAccount::Deepgram));
        $this->assertSame('paused', $this->credit()->balance(ApiAccount::Deepgram)->state());
        $this->assertTrue($this->credit()->isAvailable(ApiAccount::Qwen));
    }

    public function test_a_blocked_account_cannot_be_resolved_so_no_job_can_spend()
    {
        $this->recharge(ApiAccount::Qwen, usd: 1);
        $this->spend('qwen', 1);
        $this->credit();

        try {
            app(AiProvider::class);
            $this->fail('The Qwen provider resolved although its credit is spent.');
        } catch (AiLimitReached $exception) {
            $this->assertSame('credit', $exception->scope);
            $this->assertStringContainsString('Qwen credit has run out', $exception->getMessage());
        }

        $this->expectException(AiLimitReached::class);
        app(ImageProvider::class);
    }

    public function test_fake_providers_are_never_blocked()
    {
        ApiAccountSetting::for(ApiAccount::Deepgram)->forceFill(['paused_at' => Date::now()])->save();
        config(['services.tts.provider' => 'fake']);
        $this->credit();

        $this->assertInstanceOf(FakeTtsProvider::class, app(TtsProvider::class));
    }

    public function test_a_learner_is_told_ai_practice_is_paused_and_the_super_admin_why()
    {
        $this->recharge(ApiAccount::Qwen, usd: 1);
        $this->spend('qwen', 2);
        $this->credit();

        try {
            app(UsageMeter::class)->assertWithinLimits(User::factory()->employee()->create(), AiFeature::RoleplayTurn);
            $this->fail('A learner started a role-play on spent credit.');
        } catch (AiLimitReached $exception) {
            $this->assertStringContainsString('AI practice is paused', $exception->getMessage());
            $this->assertStringNotContainsString('credit', $exception->getMessage());
        }

        $this->expectExceptionMessage('Qwen credit has run out');
        app(UsageMeter::class)->assertWithinLimits(User::factory()->superAdmin()->create(), AiFeature::RoleplayTurn);
    }

    public function test_lesson_generation_is_refused_before_anything_is_queued()
    {
        ApiAccountSetting::for(ApiAccount::Qwen)->forceFill(['paused_at' => Date::now()])->save();
        $this->credit();

        $this->expectException(AiLimitReached::class);
        $this->expectExceptionMessage('paused by the platform owner');

        app(LessonGenerator::class)->assertWithinLimits(User::factory()->superAdmin()->create(), 1, false);
    }

    public function test_a_live_voice_call_is_metered_in_seconds()
    {
        $attempt = RoleplayAttempt::factory()->create(['duration_ms' => 90_500]);

        app(UsageMeter::class)->recordVoiceCall($attempt);

        $usage = AiUsage::query()->where('feature', AiFeature::VoiceCall->value)->sole();
        $this->assertSame('deepgram', $usage->provider);
        $this->assertSame(UsageMeter::VOICE_AGENT_MODEL, $usage->model);
        $this->assertSame(91, $usage->prompt_tokens);
        $this->assertSame(0, $usage->points_charged);
    }

    public function test_seconds_are_priced_into_the_deepgram_spend()
    {
        // $0.08 a minute, stored per million seconds.
        AiModelPrice::query()->create(['model' => UsageMeter::VOICE_AGENT_MODEL, 'unit' => 'seconds', 'input_per_million' => 0.08 / 60 * 1_000_000, 'output_per_million' => 0]);
        $this->recharge(ApiAccount::Deepgram, usd: 10);

        app(UsageMeter::class)->recordVoiceCall(RoleplayAttempt::factory()->create(['duration_ms' => 600_000]));

        $this->assertEqualsWithDelta(0.8, $this->credit()->balance(ApiAccount::Deepgram)->spentUsd, 0.0001);
    }
}
