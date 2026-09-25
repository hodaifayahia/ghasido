<?php

namespace App\Services\Tts;

use App\Contracts\SynthesisedAudio;
use App\Contracts\TtsProvider;
use App\Enums\AudioSpeed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Deepgram Flux batch TTS (TTS-01..05, API-02..04; spec 0003 Part C).
 *
 * Flux's batch REST transport is the correct surface for fixed lesson and
 * scenario copy: the response is an MP3 which GenerateAudioClip stores. A
 * learner therefore never spends a provider call while pressing Play
 * (CTRL-05).
 */
final class DeepgramTtsProvider implements TtsProvider
{
    public const PROVIDER = 'deepgram';

    public const DEFAULT_BASE_URL = 'https://api.deepgram.com';

    public const DEFAULT_MODEL = 'flux-brittany-en';

    public const SLOW_RATE = 0.75;

    private const TIMEOUT_SECONDS = 90;

    private const RETRY_TIMES = 2;

    private const RETRY_SLEEP_MS = 500;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly ?string $baseUrl = null,
        private readonly int $expressivity = 0,
    ) {}

    public function synthesise(string $text, string $voice, AudioSpeed $speed): SynthesisedAudio
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('TTS_KEY or DEEPGRAM_API_KEY is not configured.');
        }

        $model = $this->modelFor($voice);
        if ($model === '') {
            throw new RuntimeException('TTS_MODEL is not configured for Deepgram.');
        }

        $expressivity = max(-2, min(2, $this->expressivity));
        $rate = $speed === AudioSpeed::Slow ? self::SLOW_RATE : 1.0;
        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== ''
            ? $this->baseUrl
            : self::DEFAULT_BASE_URL, '/');

        $query = http_build_query([
            'model' => $model,
            'encoding' => 'mp3',
            'speed' => $rate,
            'expressivity' => $expressivity,
        ]);

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(['Authorization' => 'Token '.$this->apiKey])
            ->asJson()
            ->accept('audio/mpeg')
            ->connectTimeout(15)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(
                self::RETRY_TIMES,
                self::RETRY_SLEEP_MS,
                fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
                throw: false,
            )
            ->post('/v2/speak?'.$query, ['text' => $text]);

        if ($response->failed()) {
            // Surface Deepgram's own reason (bad key, unknown voice) in the
            // clip's failed_reason instead of a bare "HTTP 400".
            $reason = $response->json('err_msg') ?? $response->json('message') ?? $response->body();

            throw new RuntimeException(sprintf('Deepgram TTS failed (HTTP %d): %s', $response->status(), mb_substr(is_string($reason) ? $reason : '', 0, 300)));
        }

        $binary = $response->body();
        if ($binary === '') {
            throw new RuntimeException('Deepgram returned no audio.');
        }

        if (str_contains(strtolower((string) $response->header('Content-Type')), 'application/json')) {
            throw new RuntimeException('Deepgram returned an acknowledgement instead of audio.');
        }

        return new SynthesisedAudio(
            binary: $binary,
            mime: 'audio/mpeg',
            extension: 'mp3',
            durationMs: null,
        );
    }

    private function modelFor(string $voice): string
    {
        $voice = trim($voice);

        return str_starts_with($voice, 'flux-') ? $voice : trim($this->model);
    }
}
