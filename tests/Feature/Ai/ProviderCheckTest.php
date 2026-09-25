<?php

namespace Tests\Feature\Ai;

use App\Enums\AiFeature;
use App\Jobs\RunProviderCheck;
use App\Models\User;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Settings → AI models "Test" button (API-04, PERF-04, AIL-04): the
 * request only queues the job; the job makes one tiny real call and stores
 * ok or failed with latency, model and the provider's error text.
 */
class ProviderCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_button_queues_the_check_and_marks_it_pending()
    {
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('ai-models.check', ['capability' => 'image']))
            ->assertRedirect();

        Queue::assertPushed(RunProviderCheck::class, fn (RunProviderCheck $job): bool => $job->capability === 'image' && $job->userId === $admin->id);
        $this->assertSame('pending', app(AiModelSettings::class)->checks()['image']['status'] ?? null);
    }

    public function test_an_unknown_capability_is_not_a_route()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/settings/ai-models/check/voice')->assertNotFound();
    }

    public function test_every_capability_passes_on_the_fake_providers()
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (AiModelSettings::CHECKS as $capability) {
            (new RunProviderCheck($capability, $admin->id))->handle(app(AiModelSettings::class), app(UsageMeter::class));
        }

        $checks = app(AiModelSettings::class)->checks();

        foreach (AiModelSettings::CHECKS as $capability) {
            $this->assertSame('ok', $checks[$capability]['status'] ?? null, $capability.': '.($checks[$capability]['error'] ?? ''));
            $this->assertIsInt($checks[$capability]['latency_ms'] ?? null);
            $this->assertNotNull($checks[$capability]['checked_at'] ?? null);
        }

        $this->assertDatabaseHas('ai_usages', ['feature' => AiFeature::ProviderCheck->value, 'user_id' => $admin->id]);
    }

    public function test_a_real_text_check_stores_ok_with_the_model_and_meters_it()
    {
        config([
            'services.ai.provider' => 'qwen',
            'services.ai.model' => 'deepseek-v4-flash',
            'services.ai.key' => 'sk-test',
            'services.ai.base_url' => 'https://token-plan.test.aliyuncs.com/compatible-mode/v1',
        ]);
        Http::fake(['*' => Http::response([
            'model' => 'deepseek-v4-flash',
            'choices' => [['message' => ['role' => 'assistant', 'content' => '{"ok": true}']]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 4],
        ])]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('ai-models.check', ['capability' => 'ai']));

        $check = app(AiModelSettings::class)->checks()['ai'];
        $this->assertSame('ok', $check['status'] ?? null);
        $this->assertSame('deepseek-v4-flash', $check['model'] ?? null);
        $this->assertSame('qwen', $check['provider'] ?? null);
        $this->assertStringContainsString('"ok"', (string) ($check['detail'] ?? ''));
        $this->assertDatabaseHas('ai_usages', [
            'feature' => AiFeature::ProviderCheck->value,
            'model' => 'deepseek-v4-flash',
            'prompt_tokens' => 20,
        ]);
    }

    public function test_a_failing_provider_stores_failed_with_its_error_text()
    {
        config([
            'services.ai.provider' => 'qwen',
            'services.ai.model' => 'no-such-model',
            'services.ai.key' => 'sk-test',
            'services.ai.base_url' => 'https://token-plan.test.aliyuncs.com/compatible-mode/v1',
        ]);
        Http::fake(['token-plan.test.aliyuncs.com/*' => Http::response(['error' => ['message' => 'Model not exist: no-such-model']], 400)]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('ai-models.check', ['capability' => 'ai']));

        $check = app(AiModelSettings::class)->checks()['ai'];
        $this->assertSame('failed', $check['status'] ?? null);
        $this->assertStringContainsString('Model not exist', (string) ($check['error'] ?? ''));

        $this->actingAs($admin)
            ->get(route('ai-models.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('checks.ai.status', 'failed')
                ->where('checks.fast', null)
            );
    }

    public function test_a_missing_key_fails_without_a_network_call()
    {
        config(['services.stt.key' => '']);
        Http::fake();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->patch(route('ai-models.update'), ['sttMode' => 'real'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('ai-models.check', ['capability' => 'stt']));

        $check = app(AiModelSettings::class)->checks()['stt'];
        $this->assertSame('failed', $check['status'] ?? null);
        $this->assertSame('deepgram', $check['provider'] ?? null);
        Http::assertNothingSent();
    }
}
