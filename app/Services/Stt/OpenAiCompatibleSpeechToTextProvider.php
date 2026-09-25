<?php

namespace App\Services\Stt;

use App\Contracts\SpeechToTextProvider;
use App\Contracts\TranscribedWord;
use App\Contracts\WordTranscript;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Any transcription endpoint that speaks the OpenAI audio API shape:
 * multipart `POST {base_url}/audio/transcriptions` with a bearer key
 * (spec 0003 Part C). Base URL, key and model come from config/services.php.
 */
final class OpenAiCompatibleSpeechToTextProvider implements SpeechToTextProvider
{
    public const PROVIDER = 'openai';

    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    private const TIMEOUT_SECONDS = 60;

    private const RETRY_TIMES = 2;

    private const RETRY_SLEEP_MS = 500;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $baseUrl = null,
    ) {}

    public function transcribe(string $absolutePath, string $mime): string
    {
        if ($this->model === '') {
            throw new RuntimeException('STT_MODEL is not configured (config services.stt.model).');
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException(sprintf('Recording [%s] is not readable.', $absolutePath));
        }

        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Recording [%s] could not be read.', $absolutePath));
        }

        $response = Http::baseUrl($baseUrl)
            ->withToken($this->apiKey)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
            ->attach('file', $contents, basename($absolutePath), ['Content-Type' => $mime])
            ->post('/audio/transcriptions', [
                'model' => $this->model,
                'response_format' => 'json',
            ])
            ->throw();

        $text = $response->json('text');

        return is_string($text) ? trim($text) : '';
    }

    /**
     * Word timings through `verbose_json` + `timestamp_granularities[]=word`
     * (spec 0006 §5). This API gives no per-word confidence, so the check
     * runs without its clarity score; keyterms go in as the `prompt`.
     */
    public function transcribeWords(string $absolutePath, string $mime, array $keyterms = []): WordTranscript
    {
        if ($this->model === '') {
            throw new RuntimeException('STT_MODEL is not configured (config services.stt.model).');
        }

        $contents = is_readable($absolutePath) ? file_get_contents($absolutePath) : false;

        if ($contents === false) {
            throw new RuntimeException(sprintf('Recording [%s] could not be read.', basename($absolutePath)));
        }

        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');

        $fields = [
            'model' => $this->model,
            'response_format' => 'verbose_json',
            'timestamp_granularities[]' => 'word',
            'language' => 'en',
        ];

        if ($keyterms !== []) {
            $fields['prompt'] = implode(', ', $keyterms);
        }

        $response = Http::baseUrl($baseUrl)
            ->withToken($this->apiKey)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
            ->attach('file', $contents, basename($absolutePath), ['Content-Type' => $mime])
            ->post('/audio/transcriptions', $fields)
            ->throw();

        $words = [];

        foreach ((array) $response->json('words', []) as $word) {
            if (is_array($word) && is_string($word['word'] ?? null)) {
                $words[] = new TranscribedWord(
                    word: $word['word'],
                    confidence: null,
                    startMs: is_numeric($word['start'] ?? null) ? (int) round((float) $word['start'] * 1000) : null,
                    endMs: is_numeric($word['end'] ?? null) ? (int) round((float) $word['end'] * 1000) : null,
                );
            }
        }

        $text = $response->json('text');

        return new WordTranscript(
            text: is_string($text) ? trim($text) : '',
            words: $words,
            provider: self::PROVIDER,
            model: $this->model,
            keyterms: $keyterms,
        );
    }
}
