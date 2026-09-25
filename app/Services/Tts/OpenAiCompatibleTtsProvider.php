<?php

namespace App\Services\Tts;

use App\Contracts\SynthesisedAudio;
use App\Contracts\TtsProvider;
use App\Enums\AudioSpeed;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Any speech endpoint that speaks the OpenAI audio API shape:
 * `POST {base_url}/audio/speech` with a bearer key (spec 0003 Part C).
 *
 * Base URL, key and model come from config/services.php (API-04, SEC-03).
 * The slow variant is the provider's own slowed rendering, stored as its
 * own file (TTS-01); nothing is stretched client side.
 */
final class OpenAiCompatibleTtsProvider implements TtsProvider
{
    public const PROVIDER = 'openai';

    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public const SLOW_RATE = 0.75;

    private const TIMEOUT_SECONDS = 60;

    private const RETRY_TIMES = 2;

    private const RETRY_SLEEP_MS = 500;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $baseUrl = null,
    ) {}

    public function synthesise(string $text, string $voice, AudioSpeed $speed): SynthesisedAudio
    {
        if ($this->model === '') {
            throw new RuntimeException('TTS_MODEL is not configured (config services.tts.model).');
        }

        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');

        $response = Http::baseUrl($baseUrl)
            ->withToken($this->apiKey)
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
            ->post('/audio/speech', [
                'model' => $this->model,
                'input' => $text,
                'voice' => $voice,
                'response_format' => 'mp3',
                'speed' => $speed === AudioSpeed::Slow ? self::SLOW_RATE : 1.0,
            ])
            ->throw();

        $binary = $response->body();
        if ($binary === '') {
            throw new RuntimeException('The TTS provider returned no audio.');
        }

        return new SynthesisedAudio(
            binary: $binary,
            mime: 'audio/mpeg',
            extension: 'mp3',
            durationMs: null,
        );
    }
}
