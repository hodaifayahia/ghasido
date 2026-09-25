<?php

namespace Tests\Feature\Admin;

use App\Enums\ContentStatus;
use App\Enums\RoleplayStatus;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Models\AiScenario;
use App\Models\Department;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Models\VoiceAgentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Super Admin voice-agent controls and the "Test voice call" preview
 * (RP-13, API-02, ADM-02; spec 0004).
 */
class VoiceAgentAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private AiScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('inertia.testing.ensure_pages_exist', false);
        config()->set('services.voice_agent.key', 'dg-admin-secret');
        $this->owner = User::factory()->superAdmin()->create();
        $this->scenario = AiScenario::factory()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => ContentStatus::Draft,
            'slug' => 'draft-voice',
        ]);

        Http::fake([
            'api.deepgram.com/v1/auth/grant' => Http::response(['access_token' => 'admin-grant', 'expires_in' => 60]),
        ]);
    }

    public function test_preview_voice_calls_are_flagged_is_preview()
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)
            ->postJson(route('ai-scenarios.voice-preview.start', $this->scenario))
            ->assertOk()
            ->assertJsonPath('session.token', 'admin-grant');

        $this->assertStringNotContainsString('dg-admin-secret', (string) $response->getContent());

        $attempt = RoleplayAttempt::query()->findOrFail($response->json('attemptId'));
        $this->assertTrue($attempt->is_preview);
        $this->assertSame(RoleplayAttempt::CHANNEL_VOICE_CALL, $attempt->channel);
        $this->assertSame(0, RoleplayAttempt::query()->counted()->count());

        $this->actingAs($this->owner)
            ->postJson(route('ai-scenarios.voice-preview.turn', $attempt), ['seq' => 0, 'role' => 'agent', 'content' => 'Hello!'])
            ->assertOk();
        $this->actingAs($this->owner)
            ->postJson(route('ai-scenarios.voice-preview.turn', $attempt), ['seq' => 1, 'role' => 'user', 'content' => 'Welcome!'])
            ->assertOk();

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.voice-preview.end', $attempt))
            ->assertRedirect(route('ai-scenarios', ['tab' => 'preview', 'preview' => $attempt->id]));

        $this->assertSame(RoleplayStatus::Evaluating, $attempt->refresh()->status);
        Queue::assertPushed(EvaluateRoleplayAttempt::class);
    }

    public function test_employees_cannot_start_a_preview_call_or_change_settings()
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->postJson(route('ai-scenarios.voice-preview.start', $this->scenario))
            ->assertForbidden();
        $this->actingAs($employee)
            ->patch(route('ai-scenarios.voice-agent.update'), ['greeting' => 'x'])
            ->assertForbidden();
    }

    public function test_the_admin_saves_global_settings_and_scenario_overrides()
    {
        config()->set('app.url', 'http://localhost');

        $values = [
            'listenModel' => 'flux-general-en',
            'eotThreshold' => 0.8,
            'keyterms' => ['check-in', 'passport'],
            'language' => 'en',
            'speakProvider' => 'eleven_labs',
            'speakModel' => 'aura-2-luna-en',
            'elevenModelId' => 'eleven_multilingual_v2',
            'elevenVoiceId' => 'cgSgspJ2msm6clMCkdW9',
            'thinkMode' => 'managed',
            'thinkProvider' => 'anthropic',
            'thinkModel' => 'claude-3-5-haiku-latest',
            'temperature' => 0.4,
            'greeting' => 'Good evening!',
            'prompt' => 'Speak slowly.',
            'maxCallSeconds' => 240,
            'inputSampleRate' => 16000,
            'outputSampleRate' => 24000,
        ];

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.voice-agent.update'), $values)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $stored = VoiceAgentSetting::query()->firstOrFail()->values ?? [];
        $this->assertSame('eleven_labs', $stored['speakProvider']);
        $this->assertSame(['check-in', 'passport'], $stored['keyterms']);
        $this->assertSame(240, $stored['maxCallSeconds']);

        // Qwen through the proxy needs a public HTTPS APP_URL.
        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.voice-agent.update'), ['thinkMode' => 'qwen_proxy'] + $values)
            ->assertSessionHasErrors('thinkMode');

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.voice-agent.scenario', $this->scenario), [
                'speakModel' => 'aura-2-orion-en',
                'greeting' => 'Hi, I lost my key.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = $this->scenario->refresh()->settings ?? [];
        $this->assertSame('aura-2-orion-en', $settings['voice_agent']['speakModel']);

        $response = $this->actingAs($this->owner)
            ->postJson(route('ai-scenarios.voice-preview.start', $this->scenario))
            ->assertOk();
        $response->assertJsonPath('session.settings.agent.speak.provider.type', 'eleven_labs')
            ->assertJsonPath('session.settings.agent.greeting', 'Hi, I lost my key.')
            ->assertJsonPath('session.settings.agent.think.provider.type', 'anthropic')
            ->assertJsonPath('session.settings.agent.listen.provider.keyterms', ['check-in', 'passport']);
    }
}
