<?php

namespace Tests\Unit;

use App\Enums\AudioSpeed;
use App\Services\Tts\DeepgramTtsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Deepgram is called only by the provider boundary; lesson playback remains
 * file-backed through GenerateAudioClip (CTRL-05, TTS-01, TTS-02).
 */
class DeepgramTtsProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_flux_batch_request_uses_the_selected_voice_and_controls(): void
    {
        Http::fake([
            'https://api.deepgram.com/v2/speak*' => Http::response(
                'fake-mp3-bytes',
                200,
                ['Content-Type' => 'audio/mpeg'],
            ),
        ]);

        $provider = new DeepgramTtsProvider(
            apiKey: 'dg-test-key',
            model: 'flux-brittany-en',
            expressivity: -1,
        );

        $audio = $provider->synthesise('Welcome to our hotel.', 'flux-alexis-en', AudioSpeed::Slow);

        $this->assertSame('fake-mp3-bytes', $audio->binary);
        $this->assertSame('audio/mpeg', $audio->mime);
        $this->assertSame('mp3', $audio->extension);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->url() !== ''
                && $request->hasHeader('Authorization', 'Token dg-test-key')
                && $request->data() === ['text' => 'Welcome to our hotel.']
                && $query['model'] === 'flux-alexis-en'
                && $query['encoding'] === 'mp3'
                && $query['speed'] === '0.75'
                && $query['expressivity'] === '-1';
        });
    }

    public function test_a_missing_key_fails_before_a_network_call(): void
    {
        Http::fake();

        $provider = new DeepgramTtsProvider(apiKey: '');

        $this->expectExceptionMessage('TTS_KEY or DEEPGRAM_API_KEY is not configured.');
        $provider->synthesise('Hello.', 'flux-brittany-en', AudioSpeed::Normal);

        Http::assertNothingSent();
    }
}
