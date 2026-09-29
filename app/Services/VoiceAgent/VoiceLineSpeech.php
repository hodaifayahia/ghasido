<?php

namespace App\Services\VoiceAgent;

use App\Contracts\SynthesisedAudio;
use App\Enums\AudioSpeed;
use App\Services\Ai\AiModelSettings;
use App\Services\Owner\ApiKeyring;
use App\Services\Tts\FakeTtsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Voices one guest line of the fast engine in the call's Aura-2 voice
 * (spec 0009), with Deepgram's REST `/v1/speak` — the same voice the voice
 * agent speaks with, so a stored line sounds the same in both engines.
 *
 * A learner is waiting for this line, so the timeouts are short: a failure
 * leaves the line without audio and the browser reads it out itself rather
 * than stall the call. The key never leaves the server (API-02, SEC-03).
 */
final class VoiceLineSpeech
{
    public const URL = 'https://api.deepgram.com/v1/speak';

    public function provider(): string
    {
        $env = config('services.tts.provider');

        return app(AiModelSettings::class)->provider('tts', is_string($env) && trim($env) !== '' ? trim($env) : 'fake') === 'fake'
            ? 'fake'
            : 'deepgram';
    }

    /**
     * @throws RuntimeException when the line could not be voiced
     */
    public function synthesise(string $text, string $voice): SynthesisedAudio
    {
        if ($this->provider() === 'fake') {
            return (new FakeTtsProvider)->synthesise($text, $voice, AudioSpeed::Normal);
        }

        $key = app(ApiKeyring::class)->key('services.voice_agent.key');

        if (trim($key) === '') {
            throw new RuntimeException('The Deepgram key is not configured.');
        }

        try {
            $response = Http::withHeaders(['Authorization' => 'Token '.trim($key)])
                ->asJson()
                ->accept('audio/mpeg')
                ->connectTimeout(4)
                ->timeout(10)
                // The route to Deepgram drops now and then; one quick retry
                // on a failed connection only (spec 0004 NOTES).
                ->retry(2, 150, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->post(self::URL.'?'.http_build_query(['model' => $voice, 'encoding' => 'mp3']), ['text' => $text]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Deepgram to voice the line.');
        }

        if ($response->failed()) {
            $reason = $response->json('err_msg') ?? $response->json('message');

            throw new RuntimeException(sprintf('Deepgram speech failed (HTTP %d)%s', $response->status(), is_string($reason) ? ': '.mb_substr($reason, 0, 200) : ''));
        }

        $binary = $response->body();

        if ($binary === '' || str_contains(strtolower((string) $response->header('Content-Type')), 'application/json')) {
            throw new RuntimeException('Deepgram returned no audio.');
        }

        return new SynthesisedAudio(binary: $binary, mime: 'audio/mpeg', extension: 'mp3', durationMs: null);
    }
}
