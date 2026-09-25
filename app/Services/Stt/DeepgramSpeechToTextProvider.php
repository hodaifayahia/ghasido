<?php

namespace App\Services\Stt;

use App\Contracts\SpeechToTextProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Deepgram pre-recorded transcription for learners' spoken answers
 * (RESP-05, TEST-07, API-04; spec 0004 NOTES "Deepgram STT").
 *
 * `POST {base}/v1/listen?model=nova-3&smart_format=true` with the raw audio
 * as the body and its own Content-Type, `Authorization: Token <key>`. The
 * transcript is `results.channels[0].alternatives[0].transcript`. Key, model
 * and base URL come from config/services.php (`services.stt`), never code.
 */
final class DeepgramSpeechToTextProvider implements SpeechToTextProvider
{
    public const PROVIDER = 'deepgram';

    public const DEFAULT_BASE_URL = 'https://api.deepgram.com';

    public const DEFAULT_MODEL = 'nova-3';

    private const TIMEOUT_SECONDS = 120;

    private const CONNECT_TIMEOUT_SECONDS = 15;

    private const RETRY_TIMES = 3;

    private const RETRY_SLEEP_MS = 1500;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly ?string $baseUrl = null,
    ) {}

    public function transcribe(string $absolutePath, string $mime): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Deepgram speech-to-text key is not configured (set STT_KEY or DEEPGRAM_API_KEY).');
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException(sprintf('Recording [%s] is not readable.', basename($absolutePath)));
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(sprintf('Recording [%s] is empty or could not be read.', basename($absolutePath)));
        }

        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');
        $model = $this->model !== '' ? $this->model : self::DEFAULT_MODEL;
        $contentType = trim($mime) !== '' ? $mime : 'application/octet-stream';

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['Authorization' => 'Token '.$this->apiKey])
                ->acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(
                    self::RETRY_TIMES,
                    self::RETRY_SLEEP_MS,
                    // Retry what a retry can fix; a 400/401/402 fails at once.
                    fn (Throwable $e): bool => $e instanceof ConnectionException
                        || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
                )
                ->withBody($contents, $contentType)
                ->post('/v1/listen?'.http_build_query([
                    'model' => $model,
                    'smart_format' => 'true',
                ]));
        } catch (RequestException $e) {
            throw new RuntimeException(self::errorMessage($e->response->status(), $e->response->json()), 0, $e);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Deepgram speech-to-text could not be reached: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException(self::errorMessage($response->status(), $response->json()));
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Deepgram speech-to-text returned a non-JSON response.');
        }

        $transcript = data_get($data, 'results.channels.0.alternatives.0.transcript');

        if (! is_string($transcript)) {
            throw new RuntimeException('Deepgram speech-to-text returned no transcript.');
        }

        return trim($transcript);
    }

    /**
     * Deepgram's own error text, so `failed_reason` says what went wrong.
     */
    private static function errorMessage(int $status, mixed $body): string
    {
        $detail = '';

        if (is_array($body)) {
            foreach (['err_msg', 'message', 'reason', 'error'] as $key) {
                if (is_string($body[$key] ?? null) && $body[$key] !== '') {
                    $detail = $body[$key];

                    break;
                }
            }
        }

        return sprintf('Deepgram speech-to-text failed (HTTP %d)%s', $status, $detail !== '' ? ': '.$detail : '.');
    }
}
