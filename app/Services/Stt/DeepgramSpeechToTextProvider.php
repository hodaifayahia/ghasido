<?php

namespace App\Services\Stt;

use App\Contracts\SpeechToTextProvider;
use App\Contracts\TranscribedWord;
use App\Contracts\WordTranscript;
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
        $data = $this->listen($absolutePath, $mime, http_build_query([
            'model' => $this->modelId(),
            'smart_format' => 'true',
        ]));

        $transcript = data_get($data, 'results.channels.0.alternatives.0.transcript');

        if (! is_string($transcript)) {
            throw new RuntimeException('Deepgram speech-to-text returned no transcript.');
        }

        return trim($transcript);
    }

    /**
     * The pronunciation listen (spec 0006 §2): `smart_format` off, so "three"
     * is never written "3"; `filler_words` on, so hesitations are counted;
     * `language=en` (the accent codes changed nothing measurable in the
     * probe). Each keyterm is its own repeated `keyterm=` parameter.
     */
    public function transcribeWords(string $absolutePath, string $mime, array $keyterms = []): WordTranscript
    {
        $query = http_build_query([
            'model' => $this->modelId(),
            'language' => 'en',
            'smart_format' => 'false',
            'punctuate' => 'false',
            'filler_words' => 'true',
        ]);

        foreach ($keyterms as $term) {
            $query .= '&keyterm='.rawurlencode($term);
        }

        $data = $this->listen($absolutePath, $mime, $query);
        $words = [];

        foreach ((array) data_get($data, 'results.channels.0.alternatives.0.words', []) as $word) {
            if (! is_array($word) || ! is_string($word['word'] ?? null)) {
                continue;
            }

            $words[] = new TranscribedWord(
                word: $word['word'],
                confidence: is_numeric($word['confidence'] ?? null) ? round((float) $word['confidence'], 4) : null,
                startMs: is_numeric($word['start'] ?? null) ? (int) round((float) $word['start'] * 1000) : null,
                endMs: is_numeric($word['end'] ?? null) ? (int) round((float) $word['end'] * 1000) : null,
            );
        }

        $transcript = data_get($data, 'results.channels.0.alternatives.0.transcript');

        return new WordTranscript(
            text: is_string($transcript) ? trim($transcript) : '',
            words: $words,
            provider: self::PROVIDER,
            model: $this->modelId(),
            keyterms: $keyterms,
        );
    }

    private function modelId(): string
    {
        return $this->model !== '' ? $this->model : self::DEFAULT_MODEL;
    }

    /**
     * One pre-recorded listen: the raw audio as the body, the decoded JSON
     * back.
     *
     * @return array<array-key, mixed>
     */
    private function listen(string $absolutePath, string $mime, string $query): array
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
                ->post('/v1/listen?'.$query);
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

        return $data;
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
