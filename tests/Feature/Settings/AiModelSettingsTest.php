<?php

namespace Tests\Feature\Settings;

use App\Contracts\AiProvider;
use App\Contracts\ChecksConnection;
use App\Contracts\ImageProvider;
use App\Contracts\SpeechToTextProvider;
use App\Contracts\TtsProvider;
use App\Models\AiModelSetting;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Images\DashScopeImageProvider;
use App\Services\Images\FakeImageProvider;
use App\Services\Stt\DeepgramSpeechToTextProvider;
use App\Services\Tts\DeepgramTtsProvider;
use App\Services\Tts\FakeTtsProvider;
use App\Services\Tts\TtsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings → AI models (API-04, ROLE-01, SEC-03): Super Admin only, the
 * overrides reach the bound providers on the next resolve, a blank override
 * falls back to .env, and a fake switch turns a paid provider off.
 */
class AiModelSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private const QWEN = [
        'services.ai.provider' => 'qwen',
        'services.ai.model' => 'qwen3.8-max',
        'services.ai.fast_model' => 'qwen3.8-flash',
        'services.ai.key' => 'sk-test',
        'services.ai.base_url' => 'https://token-plan.test.aliyuncs.com/compatible-mode/v1',
    ];

    public function test_the_super_admin_can_view_the_page()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('ai-models.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiModels')
                ->where('settings.values.aiModel', '')
                ->where('settings.presets.text.0', 'qwen3.8-max')
                ->has('checks.ai')
                ->has('voiceAgent.settings.values')
            );
    }

    public function test_the_page_never_ships_a_key()
    {
        config(['services.ai.key' => 'sk-secret-value', 'services.tts.key' => 'dg-secret-value']);
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get(route('ai-models.edit'))->assertOk();

        $this->assertStringNotContainsString('sk-secret-value', (string) $response->getContent());
        $this->assertStringNotContainsString('dg-secret-value', (string) $response->getContent());
    }

    public function test_managers_employees_and_admins_are_refused()
    {
        foreach ([User::factory()->manager()->create(), User::factory()->employee()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('ai-models.edit'))->assertForbidden();
            $this->actingAs($user)->patch(route('ai-models.update'), ['aiModel' => 'glm-5.3'])->assertForbidden();
            $this->actingAs($user)->post(route('ai-models.check', ['capability' => 'ai']))->assertForbidden();
        }

        $this->assertDatabaseCount('ai_model_settings', 0);
    }

    public function test_the_super_admin_can_update_the_overrides()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('ai-models.update'), [
                'aiMode' => 'real',
                'aiModel' => 'deepseek-v4-pro',
                'aiFastModel' => 'some-new-model-id',
                'imageMode' => 'fake',
                'imageModel' => 'wan2.7-image',
                'imageSizeLandscape' => '1280*720',
                'imageSizeSquare' => '1024*1024',
                'ttsMode' => '',
                'ttsVoice' => 'flux-kit-en',
                'ttsExpressivity' => 1,
                'sttMode' => '',
                'sttModel' => 'nova-3-general',
            ])
            ->assertRedirect();

        $values = AiModelSetting::query()->firstOrFail()->values ?? [];
        $this->assertSame('deepseek-v4-pro', $values['aiModel']);
        $this->assertSame('some-new-model-id', $values['aiFastModel']);
        $this->assertSame('fake', $values['imageMode']);
        $this->assertSame('flux-kit-en', app(TtsSettings::class)->voice());
        $this->assertSame(1, app(TtsSettings::class)->expressivity());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai-models.settings.updated']);
    }

    public function test_invalid_values_are_refused()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('ai-models.update'), [
                'aiModel' => 'bad model; rm -rf',
                'aiMode' => 'maybe',
                'imageSizeSquare' => 'huge',
                'ttsExpressivity' => 7,
            ])
            ->assertSessionHasErrors(['aiModel', 'aiMode', 'imageSizeSquare', 'ttsExpressivity']);
    }

    public function test_an_override_reaches_the_bound_ai_provider()
    {
        config(self::QWEN);
        Http::fake(['*' => Http::response($this->chatReply('{"ok": true}'))]);
        $this->saveOverrides(['aiModel' => 'glm-5.3', 'aiFastModel' => 'kimi-k2.6']);

        $provider = $this->app->make(AiProvider::class);
        $this->assertInstanceOf(ChecksConnection::class, $provider);
        $provider->ping();
        $provider->ping(true);

        $models = [];
        Http::assertSent(function (HttpRequest $request) use (&$models): bool {
            $models[] = $request['model'];

            // DashScope's compatible mode: thinking stays off for every model.
            return $request['enable_thinking'] === false;
        });
        $this->assertSame(['glm-5.3', 'kimi-k2.6'], $models);
    }

    public function test_a_model_that_refuses_enable_thinking_is_resent_without_it()
    {
        config(self::QWEN);
        Http::fake(['token-plan.test.aliyuncs.com/*' => Http::sequence()
            ->push(['error' => ['code' => 'invalid_parameter_error', 'message' => 'The value of the enable_thinking parameter is restricted to True.']], 400)
            ->push($this->chatReply('{"ok": true}'))]);
        $this->saveOverrides(['aiModel' => 'glm-5.3']);

        /** @var ChecksConnection $provider */
        $provider = $this->app->make(AiProvider::class);
        $reply = $provider->ping();

        $this->assertSame('{"ok": true}', $reply->text);
        $sent = [];
        Http::assertSent(function (HttpRequest $request) use (&$sent): bool {
            $sent[] = $request->data()['enable_thinking'] ?? 'absent';

            return true;
        });
        $this->assertSame([false, 'absent'], $sent);
    }

    public function test_a_blank_override_falls_back_to_env()
    {
        config(self::QWEN);
        Http::fake(['*' => Http::response($this->chatReply('{"ok": true}'))]);
        $this->saveOverrides(['aiModel' => '', 'aiFastModel' => '']);

        /** @var ChecksConnection $provider */
        $provider = $this->app->make(AiProvider::class);
        $provider->ping();
        $provider->ping(true);

        $models = [];
        Http::assertSent(function (HttpRequest $request) use (&$models): bool {
            $models[] = $request['model'];

            return true;
        });
        $this->assertSame(['qwen3.8-max', 'qwen3.8-flash'], $models);
    }

    public function test_the_fake_switch_turns_a_paid_provider_off()
    {
        config([...self::QWEN, 'services.tts.provider' => 'deepgram', 'services.tts.key' => 'dg', 'services.ai.image_provider' => 'qwen']);

        $this->assertNotInstanceOf(FakeAiProvider::class, $this->app->make(AiProvider::class));
        $this->assertInstanceOf(DeepgramTtsProvider::class, $this->app->make(TtsProvider::class));

        $this->saveOverrides(['aiMode' => 'fake', 'ttsMode' => 'fake', 'imageMode' => 'fake']);

        $this->assertInstanceOf(FakeAiProvider::class, $this->app->make(AiProvider::class));
        $this->assertInstanceOf(FakeTtsProvider::class, $this->app->make(TtsProvider::class));
        $this->assertInstanceOf(FakeImageProvider::class, $this->app->make(ImageProvider::class));
    }

    public function test_the_real_switch_turns_a_fake_env_on()
    {
        // TestCase forces every provider to fake.
        $this->saveOverrides(['imageMode' => 'real', 'sttMode' => 'real', 'sttModel' => 'nova-3-general']);

        $this->assertInstanceOf(DashScopeImageProvider::class, $this->app->make(ImageProvider::class));
        $this->assertInstanceOf(DeepgramSpeechToTextProvider::class, $this->app->make(SpeechToTextProvider::class));
    }

    public function test_the_image_overrides_reach_the_request()
    {
        config([
            'services.ai.image_provider' => 'qwen',
            'services.ai.image_key' => 'sk-img',
            'services.ai.image_base_url' => 'https://dashscope.test/api/v1',
        ]);
        Http::fake([
            'dashscope.test/api/v1/services/aigc/text2image/image-synthesis' => Http::response(['output' => ['task_id' => 't1']]),
            'dashscope.test/api/v1/tasks/t1' => Http::response(['output' => ['task_status' => 'SUCCEEDED', 'results' => [['url' => 'https://oss.test/a.png']]]]),
            'oss.test/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);
        $this->saveOverrides(['imageModel' => 'wan2.7-image-pro', 'imageSizeSquare' => '768*768']);

        $this->app->make(ImageProvider::class)->generate('A bell', ImageProvider::SIZE_SQUARE);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'image-synthesis')
            && $request['model'] === 'wan2.7-image-pro'
            && $request['parameters']['size'] === '768*768');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function saveOverrides(array $values): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('ai-models.update'), $values)
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function chatReply(string $content): array
    {
        return [
            'model' => 'echo',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
        ];
    }
}
