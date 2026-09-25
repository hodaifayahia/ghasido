<?php

namespace Tests\Feature\Ai;

use App\Contracts\SpeechToTextProvider;
use App\Services\Stt\DeepgramSpeechToTextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Deepgram pre-recorded transcription for spoken answers (RESP-05, TEST-07,
 * API-04; spec 0004 NOTES "Deepgram STT"). No network: Http::fake().
 */
class DeepgramSttTest extends TestCase
{
    use RefreshDatabase;

    private string $recording;

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(sys_get_temp_dir(), 'stt');
        $this->assertIsString($path);
        file_put_contents($path, 'fake-webm-bytes');
        $this->recording = $path;
    }

    protected function tearDown(): void
    {
        @unlink($this->recording);

        parent::tearDown();
    }

    public function test_it_posts_the_raw_audio_and_reads_the_transcript()
    {
        Http::fake([
            'api.deepgram.com/v1/listen*' => Http::response([
                'results' => ['channels' => [['alternatives' => [['transcript' => ' Good evening, welcome to the hotel. ', 'confidence' => 0.98]]]]],
            ]),
        ]);

        $stt = new DeepgramSpeechToTextProvider('dg-test-key');

        $this->assertSame('Good evening, welcome to the hotel.', $stt->transcribe($this->recording, 'audio/webm'));

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://api.deepgram.com/v1/listen?')
                && str_contains($request->url(), 'model=nova-3')
                && str_contains($request->url(), 'smart_format=true')
                && $request->hasHeader('Authorization', 'Token dg-test-key')
                && $request->hasHeader('Content-Type', 'audio/webm')
                && $request->body() === 'fake-webm-bytes';
        });
    }

    public function test_a_rejected_request_surfaces_deepgrams_own_message()
    {
        Http::fake([
            'api.deepgram.com/*' => Http::response(['err_code' => 'INVALID_AUTH', 'err_msg' => 'Invalid credentials.'], 401),
        ]);

        $stt = new DeepgramSpeechToTextProvider('bad-key');

        try {
            $stt->transcribe($this->recording, 'audio/webm');
            $this->fail('A 401 did not throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringContainsString('Invalid credentials.', $e->getMessage());
        }

        // 401 is not retried: exactly one call.
        Http::assertSentCount(1);
    }

    public function test_a_missing_key_or_transcript_is_a_clear_error()
    {
        try {
            (new DeepgramSpeechToTextProvider(''))->transcribe($this->recording, 'audio/webm');
            $this->fail('A blank key did not throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('key is not configured', $e->getMessage());
        }

        Http::fake(['api.deepgram.com/*' => Http::response(['results' => []])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no transcript');

        (new DeepgramSpeechToTextProvider('dg-test-key'))->transcribe($this->recording, 'audio/webm');
    }

    public function test_the_binding_resolves_deepgram_from_configuration()
    {
        config([
            'services.stt.provider' => 'deepgram',
            'services.stt.key' => 'dg-test-key',
            'services.stt.model' => 'nova-3',
        ]);

        $this->assertInstanceOf(DeepgramSpeechToTextProvider::class, app(SpeechToTextProvider::class));
    }
}
