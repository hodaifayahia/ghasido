<?php

namespace Tests\Feature\Owner;

use App\Enums\ApiAccount;
use App\Jobs\RunProviderCheck;
use App\Models\AiModelPrice;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Services\Owner\ApiKeyring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The owner console's actions (spec 0007): keys (encrypted, masked, never
 * sent to a browser), recharges, pause, connection checks, the Deepgram
 * balance and the price table (moved here from Settings → AI usage, D8).
 */
class OwnerConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'sk-live-owner-secret-7890';

    private Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = Owner::factory()->create();
        config([
            'services.ai.provider' => 'qwen',
            'services.ai.model' => 'qwen3.8-flash',
            'services.ai.key' => 'env-qwen-key-1111',
            'services.tts.provider' => 'deepgram',
            'services.tts.key' => 'env-deepgram-key-2222',
            'services.stt.provider' => 'deepgram',
            'services.stt.key' => 'env-deepgram-key-2222',
            'services.voice_agent.key' => 'env-deepgram-key-2222',
        ]);
    }

    public function test_a_saved_key_is_encrypted_used_by_the_app_and_never_sent_to_the_browser()
    {
        $this->actingAs($this->owner, 'owner')
            ->put(route('owner.accounts.key.update', ['account' => 'qwen']), ['key' => self::SECRET])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $raw = (string) DB::table('api_account_settings')->where('account', 'qwen')->value('api_key');
        $this->assertStringNotContainsString(self::SECRET, $raw);

        $keys = app(ApiKeyring::class);
        $keys->forget();
        $this->assertSame(self::SECRET, $keys->key('services.ai.key'));
        $this->assertSame(self::SECRET, $keys->key('services.ai.image_key'));
        // Deepgram is another account: untouched.
        $this->assertSame('env-deepgram-key-2222', $keys->key('services.tts.key'));

        $audit = AuditLog::query()->where('action', 'api_key.replaced')->sole();
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode($audit->changes));
        $this->assertSame('7890', $audit->changes['ends_with'] ?? null);

        $this->actingAs($this->owner, 'owner')
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertDontSee(self::SECRET)
            ->assertDontSee('env-deepgram-key-2222')
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.account', 'qwen')
                ->where('accounts.0.key.source', 'owner')
                ->where('accounts.0.key.masked', '••••7890')
                ->where('accounts.1.key.source', 'env')
                ->where('accounts.1.key.masked', '••••2222'));
    }

    public function test_a_deepgram_key_feeds_speech_transcription_and_voice_calls()
    {
        $this->actingAs($this->owner, 'owner')
            ->put(route('owner.accounts.key.update', ['account' => 'deepgram']), ['key' => 'dg-owner-key-5555'])
            ->assertSessionHasNoErrors();

        $keys = app(ApiKeyring::class);
        $keys->forget();

        foreach (['services.tts.key', 'services.stt.key', 'services.voice_agent.key'] as $slot) {
            $this->assertSame('dg-owner-key-5555', $keys->key($slot), $slot);
        }

        $this->assertSame('env-qwen-key-1111', $keys->key('services.ai.key'));
    }

    public function test_the_owner_key_never_replaces_another_vendors_key()
    {
        ApiAccountSetting::for(ApiAccount::Qwen)->forceFill(['api_key' => self::SECRET])->save();
        config(['services.ai.provider' => 'anthropic', 'services.ai.key' => 'anthropic-env-key']);

        $this->assertSame('anthropic-env-key', app(ApiKeyring::class)->key('services.ai.key'));
    }

    public function test_clearing_a_key_falls_back_to_the_env_key()
    {
        ApiAccountSetting::for(ApiAccount::Qwen)->forceFill(['api_key' => self::SECRET])->save();

        $this->actingAs($this->owner, 'owner')
            ->delete(route('owner.accounts.key.destroy', ['account' => 'qwen']))
            ->assertRedirect();

        $keys = app(ApiKeyring::class);
        $keys->forget();
        $this->assertSame('env-qwen-key-1111', $keys->key('services.ai.key'));
        $this->assertSame(1, AuditLog::query()->where('action', 'api_key.cleared')->count());
    }

    public function test_a_key_must_be_one_token()
    {
        $this->actingAs($this->owner, 'owner')
            ->put(route('owner.accounts.key.update', ['account' => 'qwen']), ['key' => 'has a space'])
            ->assertSessionHasErrors('key');

        $this->actingAs($this->owner, 'owner')
            ->put(route('owner.accounts.key.update', ['account' => 'openai']), ['key' => self::SECRET])
            ->assertNotFound();
    }

    public function test_a_recharge_adds_credit_starts_metering_and_is_audited()
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 200, 'tokens' => 250000, 'note' => 'September plan'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $topup = ApiCreditTopup::query()->sole();
        $this->assertSame(ApiAccount::Qwen, $topup->account);
        $this->assertSame('200.0000', $topup->amount_usd);
        $this->assertSame(250000, $topup->amount_tokens);
        $this->assertSame($this->owner->id, $topup->owner_id);
        $this->assertNotNull(ApiAccountSetting::for(ApiAccount::Qwen)->metering_started_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'api_credit.recharged')->count());

        $this->actingAs($this->owner, 'owner')
            ->get(route('owner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.state', 'active')
                ->where('accounts.0.mode', 'units')
                ->where('accounts.0.client.creditUsd', 200)
                ->where('accounts.0.client.remainingUsd', 200)
                ->where('accounts.0.meters.0.meter', 'tokens')
                ->where('accounts.0.meters.0.granted', 250000)
                ->has('accounts.0.topups', 1)
                ->where('accounts.1.state', 'unlimited'));
    }

    public function test_a_recharge_is_validated()
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 0])
            ->assertSessionHasErrors('usd');

        // Deepgram bills audio, not tokens.
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'deepgram']), ['usd' => 50, 'tokens' => 1000])
            ->assertSessionHasErrors('tokens');

        $this->assertSame(0, ApiCreditTopup::query()->count());
    }

    public function test_a_deepgram_recharge_takes_minutes_and_characters()
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'deepgram']), ['usd' => 50, 'minutes' => 300, 'characters' => 500000])
            ->assertSessionHasNoErrors();

        $topup = ApiCreditTopup::query()->sole();
        $this->assertSame(18000, $topup->amount_seconds);
        $this->assertSame(500000, $topup->amount_characters);
        $this->assertSame(0, $topup->amount_tokens);
    }

    public function test_a_pack_account_needs_units_with_its_dollars()
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 200, 'tokens' => 250000]);

        // Dollars alone would raise her balance without buying anything.
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 50])
            ->assertSessionHasErrors('tokens');

        $this->assertSame(1, ApiCreditTopup::query()->count());
    }

    public function test_a_units_only_recharge_fixes_a_mistyped_pack()
    {
        // Meant "$200 = 25,000,000 tokens" but typed 250,000: add the
        // missing tokens with no dollars, and her $200 stays $200.
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 200, 'tokens' => 250000]);
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['tokens' => 24750000, 'note' => 'Correction: 25M tokens'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner, 'owner')
            ->get(route('owner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.meters.0.granted', 25000000)
                ->where('accounts.0.client.creditUsd', 200)
                ->where('accounts.0.client.remainingUsd', 200));
    }

    public function test_a_negative_recharge_corrects_a_mistake()
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'deepgram']), ['usd' => 2000]);
        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'deepgram']), ['usd' => -1800, 'note' => 'Typo'])
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(200.0, (float) ApiCreditTopup::query()->sum('amount_usd'), 0.0001);
    }

    public function test_pause_stops_and_resumes_an_account()
    {
        $this->actingAs($this->owner, 'owner')
            ->patch(route('owner.accounts.pause', ['account' => 'deepgram']))
            ->assertRedirect();

        $this->assertNotNull(ApiAccountSetting::for(ApiAccount::Deepgram)->paused_at);

        $this->actingAs($this->owner, 'owner')
            ->patch(route('owner.accounts.pause', ['account' => 'deepgram']));

        $this->assertNull(ApiAccountSetting::for(ApiAccount::Deepgram)->paused_at);
        $this->assertSame(['api_account.paused', 'api_account.resumed'], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_a_check_queues_the_accounts_connection_tests()
    {
        Queue::fake();

        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.check', ['account' => 'deepgram']))
            ->assertRedirect();

        Queue::assertPushed(RunProviderCheck::class, 2);
        Queue::assertPushed(RunProviderCheck::class, fn (RunProviderCheck $job): bool => $job->capability === 'stt' && $job->userId === null);
    }

    public function test_the_deepgram_balance_is_read_on_request()
    {
        Http::fake([
            'api.deepgram.com/v1/projects' => Http::response(['projects' => [['project_id' => 'p-1', 'name' => 'GHASIDO']]]),
            'api.deepgram.com/v1/projects/p-1/balances' => Http::response(['balances' => [['balance_id' => 'b', 'amount' => 187.25, 'units' => 'usd']]]),
        ]);

        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.balance'))
            ->assertRedirect();

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token env-deepgram-key-2222'));

        $this->actingAs($this->owner, 'owner')
            ->get(route('owner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deepgramBalance.ok', true)
                ->where('deepgramBalance.project', 'GHASIDO')
                ->where('deepgramBalance.balances.0.amount', 187.25)
                ->where('deepgramBalance.balances.0.units', 'USD'));
    }

    public function test_a_refused_deepgram_balance_says_why()
    {
        Http::fake(['api.deepgram.com/*' => Http::response(['err_msg' => 'Insufficient permissions'], 403)]);

        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.balance'))
            ->assertRedirect();

        $this->actingAs($this->owner, 'owner')
            ->get(route('owner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deepgramBalance.ok', false)
                ->where('deepgramBalance.error', fn (string $error): bool => str_contains($error, 'HTTP 403')));
    }

    public function test_prices_are_saved_replaced_and_audited()
    {
        AiModelPrice::query()->create(['model' => 'old-model', 'input_per_million' => 1, 'output_per_million' => 1]);

        $this->actingAs($this->owner, 'owner')
            ->put(route('owner.prices.update'), ['prices' => [
                ['model' => 'qwen3.8-flash', 'unit' => 'tokens', 'input' => 0.5, 'output' => 2],
                ['model' => 'aura-2-*', 'unit' => 'characters', 'input' => 30, 'output' => 0],
                ['model' => 'nova-3', 'unit' => 'seconds', 'input' => 71.6667, 'output' => 0],
                ['model' => 'qwen-image-2.0', 'unit' => 'images', 'input' => 40000, 'output' => 0],
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['aura-2-*', 'nova-3', 'qwen-image-2.0', 'qwen3.8-flash'], AiModelPrice::query()->orderBy('model')->pluck('model')->all());
        // Four created, one removed.
        $this->assertSame(5, AuditLog::query()->where('action', 'like', 'ai_price.%')->count());
        $this->assertSame('30.0000', AiModelPrice::for('aura-2-thalia-en')?->input_per_million);
    }

    public function test_price_rows_are_validated()
    {
        $this->actingAs($this->owner, 'owner')
            ->put(route('owner.prices.update'), ['prices' => [
                ['model' => '', 'unit' => 'minutes', 'input' => -1, 'output' => 0],
                ['model' => '*', 'unit' => 'tokens', 'input' => 1, 'output' => 1],
            ]])
            ->assertSessionHasErrors(['prices.0.model', 'prices.0.unit', 'prices.0.input', 'prices.1.model']);
    }
}
