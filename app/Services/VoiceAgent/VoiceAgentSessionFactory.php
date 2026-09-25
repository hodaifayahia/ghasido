<?php

namespace App\Services\VoiceAgent;

use App\Models\AiScenario;
use App\Models\RoleplayAttempt;
use App\Services\Ai\RoleplayPrompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

/**
 * Builds everything the browser needs to open one Deepgram Voice Agent call
 * (RP-03, RP-04, API-02, SEC-03; spec 0004).
 *
 * The Settings message is assembled here, server-side, from the scenario row
 * and the admin's voice-agent controls — never from the client (RP-04). The
 * Deepgram key is exchanged for a short-lived grant token; neither it nor the
 * Qwen key ever appears in what this returns.
 */
final class VoiceAgentSessionFactory
{
    public const GRANT_URL = 'https://api.deepgram.com/v1/auth/grant';

    public function __construct(
        private readonly VoiceAgentSettings $settings,
        private readonly VoiceAgentProxyToken $proxyTokens,
    ) {}

    /**
     * @return array{url: string, token: string, expiresIn: int, settings: array<string, mixed>, maxCallSeconds: int, inputSampleRate: int, outputSampleRate: int, thinkMode: string}
     *
     * @throws RuntimeException when the voice agent is not configured or Deepgram refuses the grant
     */
    public function create(RoleplayAttempt $attempt, AiScenario $scenario): array
    {
        $values = $this->settings->forScenario($scenario);
        $grant = $this->grant();

        return [
            'url' => $this->agentUrl(),
            'token' => $grant['token'],
            'expiresIn' => $grant['expiresIn'],
            'settings' => $this->settingsMessage($attempt, $scenario, $values),
            'maxCallSeconds' => $values['maxCallSeconds'],
            'inputSampleRate' => $values['inputSampleRate'],
            'outputSampleRate' => $values['outputSampleRate'],
            'thinkMode' => $values['thinkMode'],
        ];
    }

    /**
     * The first message the client sends on the socket.
     *
     * @param  array<string, mixed>  $values  VoiceAgentSettings::forScenario()
     * @return array<string, mixed>
     */
    public function settingsMessage(RoleplayAttempt $attempt, AiScenario $scenario, array $values): array
    {
        $listenModel = (string) $values['listenModel'];
        $listen = [
            'type' => 'deepgram',
            'version' => VoiceAgentSettings::LISTEN_MODELS[$listenModel] ?? 'v2',
            'model' => $listenModel,
        ];

        if ($listen['version'] === 'v2') {
            $listen['eot_threshold'] = (float) $values['eotThreshold'];
        }

        if (is_array($values['keyterms']) && $values['keyterms'] !== []) {
            $listen['keyterms'] = array_values($values['keyterms']);
        }

        $speak = $values['speakProvider'] === 'eleven_labs'
            ? ['provider' => [
                'type' => 'eleven_labs',
                'model_id' => (string) $values['elevenModelId'],
                'voice_id' => (string) $values['elevenVoiceId'],
            ]]
            : ['provider' => [
                'type' => 'deepgram',
                'model' => (string) $values['speakModel'],
            ]];

        return [
            'type' => 'Settings',
            'audio' => [
                'input' => [
                    'encoding' => 'linear16',
                    'sample_rate' => (int) $values['inputSampleRate'],
                ],
                'output' => [
                    'encoding' => 'linear16',
                    'sample_rate' => (int) $values['outputSampleRate'],
                    'container' => 'none',
                ],
            ],
            'agent' => [
                'language' => (string) $values['language'],
                'listen' => ['provider' => $listen],
                'think' => $this->think($attempt, $scenario, $values),
                'speak' => $speak,
                'greeting' => (string) $values['greeting'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function think(RoleplayAttempt $attempt, AiScenario $scenario, array $values): array
    {
        $prompt = RoleplayPrompt::forVoice($scenario, (string) $values['prompt']);

        if ($values['thinkMode'] === 'qwen_proxy') {
            $qwenModel = config('services.ai.fast_model') ?: config('services.ai.model');
            $token = $this->proxyTokens->issue($attempt, (int) $values['maxCallSeconds'] + 300);

            // The endpoint is our own server; no header carries a key. The
            // proxy forces the server-side prompt again (RP-04).
            return [
                'provider' => [
                    'type' => 'open_ai',
                    'model' => is_string($qwenModel) ? $qwenModel : 'qwen',
                    'temperature' => (float) $values['temperature'],
                ],
                'endpoint' => [
                    // Built on APP_URL, not the request host: Deepgram calls
                    // it from the internet (spec 0004 NOTES).
                    'url' => rtrim((string) config('app.url'), '/').route('voice-agent.llm', ['token' => $token], false),
                    'headers' => new stdClass,
                ],
                'prompt' => $prompt,
            ];
        }

        return [
            'provider' => [
                'type' => (string) $values['thinkProvider'],
                'model' => (string) $values['thinkModel'],
                'temperature' => (float) $values['temperature'],
            ],
            'prompt' => $prompt,
        ];
    }

    /**
     * @return array{token: string, expiresIn: int}
     */
    private function grant(): array
    {
        $key = config('services.voice_agent.key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException(__('Voice calls are not configured on the server yet.'));
        }

        $ttl = max(30, min(3600, (int) config('services.voice_agent.token_ttl', 60)));

        try {
            $response = Http::withHeaders(['Authorization' => 'Token '.trim($key)])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(8)
                ->timeout(20)
                // The route to Deepgram drops intermittently; a second try
                // usually connects. Only connection failures are retried.
                ->retry(3, 800, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->post(self::GRANT_URL, ['ttl_seconds' => $ttl]);
        } catch (ConnectionException) {
            throw new RuntimeException(__('Could not reach the voice service. Please try again.'));
        }

        if (in_array($response->status(), [401, 403], true)) {
            // Seen live 2026-09-23: keys scoped only `account:write` get
            // 403 "Insufficient permissions" from /v1/auth/grant.
            throw new RuntimeException(__('The voice service key is not allowed to start calls (HTTP :status). Use a Deepgram API key with Member permissions or higher.', ['status' => $response->status()]));
        }

        if (! $response->successful()) {
            // The status only: the body may echo request details.
            throw new RuntimeException(__('The voice service refused the call (HTTP :status).', ['status' => $response->status()]));
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException(__('The voice service returned no token.'));
        }

        $expires = $response->json('expires_in');

        return ['token' => $token, 'expiresIn' => is_numeric($expires) ? (int) $expires : $ttl];
    }

    private function agentUrl(): string
    {
        $url = config('services.voice_agent.url');

        return is_string($url) && $url !== '' ? $url : 'wss://agent.deepgram.com/v1/agent/converse';
    }
}
