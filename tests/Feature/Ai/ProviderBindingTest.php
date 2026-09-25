<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiProvider;
use App\Contracts\SpeechToTextProvider;
use App\Contracts\TtsProvider;
use App\Services\Ai\AnthropicAiProvider;
use App\Services\Ai\FakeAiProvider;
use App\Services\Stt\FakeSpeechToTextProvider;
use App\Services\Stt\OpenAiCompatibleSpeechToTextProvider;
use App\Services\Tts\FakeTtsProvider;
use App\Services\Tts\OpenAiCompatibleTtsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The provider swap is configuration, not code (API-04; spec 0003 Part C):
 * the container resolves whichever class `services.*.provider` names.
 */
class ProviderBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_fake_providers_are_the_default()
    {
        config([
            'services.ai.provider' => 'fake',
            'services.tts.provider' => 'fake',
            'services.stt.provider' => 'fake',
        ]);

        $this->assertInstanceOf(FakeAiProvider::class, $this->app->make(AiProvider::class));
        $this->assertInstanceOf(FakeTtsProvider::class, $this->app->make(TtsProvider::class));
        $this->assertInstanceOf(FakeSpeechToTextProvider::class, $this->app->make(SpeechToTextProvider::class));
    }

    public function test_a_blank_provider_falls_back_to_fake()
    {
        config([
            'services.ai.provider' => '',
            'services.tts.provider' => null,
            'services.stt.provider' => '   ',
        ]);

        $this->assertInstanceOf(FakeAiProvider::class, $this->app->make(AiProvider::class));
        $this->assertInstanceOf(FakeTtsProvider::class, $this->app->make(TtsProvider::class));
        $this->assertInstanceOf(FakeSpeechToTextProvider::class, $this->app->make(SpeechToTextProvider::class));
    }

    public function test_the_real_providers_resolve_from_configuration()
    {
        config([
            'services.ai.provider' => 'anthropic',
            'services.ai.model' => 'test-model',
            'services.ai.key' => 'sk-test',
            'services.tts.provider' => 'openai',
            'services.tts.model' => 'tts-model',
            'services.tts.key' => 'sk-tts',
            'services.stt.provider' => 'openai',
            'services.stt.model' => 'stt-model',
            'services.stt.key' => 'sk-stt',
        ]);

        $this->assertInstanceOf(AnthropicAiProvider::class, $this->app->make(AiProvider::class));
        $this->assertInstanceOf(OpenAiCompatibleTtsProvider::class, $this->app->make(TtsProvider::class));
        $this->assertInstanceOf(OpenAiCompatibleSpeechToTextProvider::class, $this->app->make(SpeechToTextProvider::class));
    }

    public function test_a_config_change_takes_effect_on_the_next_resolve()
    {
        config(['services.ai.provider' => 'fake']);
        $this->assertInstanceOf(FakeAiProvider::class, $this->app->make(AiProvider::class));

        config(['services.ai.provider' => 'anthropic', 'services.ai.model' => 'm', 'services.ai.key' => 'k']);
        $this->assertInstanceOf(AnthropicAiProvider::class, $this->app->make(AiProvider::class));
    }

    public function test_an_unknown_provider_name_is_refused_loudly()
    {
        config(['services.ai.provider' => 'unknown-provider']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown AI provider [unknown-provider]');

        $this->app->make(AiProvider::class);
    }

    public function test_an_unknown_tts_or_stt_provider_name_is_refused_loudly()
    {
        config(['services.tts.provider' => 'elevenlabs']);

        try {
            $this->app->make(TtsProvider::class);
            $this->fail('No exception for an unknown TTS provider.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unknown TTS provider [elevenlabs]', $e->getMessage());
        }

        // `deepgram` is a real STT provider since spec 0004; use a made-up name.
        config(['services.stt.provider' => 'whisper-local']);

        try {
            $this->app->make(SpeechToTextProvider::class);
            $this->fail('No exception for an unknown STT provider.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unknown STT provider [whisper-local]', $e->getMessage());
        }
    }
}
