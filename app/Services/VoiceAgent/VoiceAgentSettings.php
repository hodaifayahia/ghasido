<?php

namespace App\Services\VoiceAgent;

use App\Models\AiScenario;
use App\Models\User;
use App\Models\VoiceAgentSetting;
use App\Services\Ai\AiModelSettings;
use App\Services\Owner\ApiKeyring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * The Super Admin's live voice-call controls (RP-03, RP-04, API-04, spec 0004).
 *
 * One stored row normalised over the defaults below, so a fresh install works
 * before anyone saves. Nothing here is a credential: the Deepgram key and the
 * Qwen key stay in config/services.php and never reach a payload (API-02,
 * SEC-03).
 *
 * @phpstan-type VoiceAgentValues array{
 *     engine: string,
 *     reuseStoredLines: bool,
 *     listenModel: string,
 *     eotThreshold: float,
 *     eagerEotThreshold: float,
 *     eotTimeoutMs: int,
 *     keyterms: list<string>,
 *     language: string,
 *     speakProvider: string,
 *     speakModel: string,
 *     elevenModelId: string,
 *     elevenVoiceId: string,
 *     thinkMode: string,
 *     thinkProvider: string,
 *     thinkModel: string,
 *     temperature: float,
 *     greeting: string,
 *     prompt: string,
 *     maxCallSeconds: int,
 *     inputSampleRate: int,
 *     outputSampleRate: int,
 * }
 * @phpstan-type ScenarioVoiceOverrides array{speakModel: string|null, elevenVoiceId: string|null, greeting: string|null}
 */
final class VoiceAgentSettings
{
    /**
     * `pipeline` = the fast engine (spec 0009): the browser streams the
     * employee to Deepgram Flux, our server writes the guest's line with
     * Qwen and plays it from the stored-voice bank or voices it once.
     * `agent` = the Deepgram Voice Agent, which listens, thinks and speaks
     * on Deepgram's side (spec 0004).
     */
    public const ENGINES = ['pipeline', 'agent'];

    /**
     * Set for a while when Qwen answers "quota exhausted": new calls then
     * run on the voice agent with Deepgram's own model instead of failing
     * on every turn (spec 0009).
     */
    public const QWEN_QUOTA_FLAG = 'voice:qwen-quota-exhausted';

    public const QWEN_QUOTA_MINUTES = 15;

    /** Deepgram Aura-2 English voices offered in the picker. */
    public const AURA_VOICES = ['thalia', 'andromeda', 'helena', 'apollo', 'arcas', 'aries', 'asteria', 'luna', 'orion', 'zeus'];

    /** listen model => Deepgram listen API version. */
    public const LISTEN_MODELS = ['flux-general-en' => 'v2', 'nova-3' => 'v1'];

    public const SPEAK_PROVIDERS = ['deepgram', 'eleven_labs'];

    public const THINK_MODES = ['managed', 'qwen_proxy'];

    public const THINK_PROVIDERS = ['open_ai', 'anthropic', 'google'];

    public const INPUT_SAMPLE_RATES = [16000, 24000, 44100, 48000];

    public const OUTPUT_SAMPLE_RATES = [16000, 24000, 48000];

    /** @var VoiceAgentValues */
    public const DEFAULTS = [
        'engine' => 'pipeline',
        'reuseStoredLines' => true,
        'listenModel' => 'flux-general-en',
        // Patient turn-taking: learners with low English pause mid-sentence; at 0.7
        // the live probe cut the employee off after "Good evening." (2026-09-23).
        'eotThreshold' => 0.8,
        // The reply is prepared while the employee is finishing: Flux raises
        // an early end-of-turn at this confidence and the guest's line is
        // ready when the turn is confirmed (spec 0009).
        'eagerEotThreshold' => 0.5,
        // The longest silence before the guest answers. Deepgram's default
        // is 5000 ms, so a hesitant learner who never sounded "finished"
        // waited five seconds for every reply (spec 0009).
        'eotTimeoutMs' => 2000,
        'keyterms' => [],
        'language' => 'en',
        'speakProvider' => 'deepgram',
        'speakModel' => 'aura-2-thalia-en',
        // Flash is ElevenLabs' real-time model; multilingual v2 adds about a
        // second to every reply (spec 0009).
        'elevenModelId' => 'eleven_flash_v2_5',
        'elevenVoiceId' => 'cgSgspJ2msm6clMCkdW9',
        'thinkMode' => 'managed',
        'thinkProvider' => 'open_ai',
        'thinkModel' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'greeting' => 'Hello! How may I help you?',
        'prompt' => '',
        'maxCallSeconds' => 300,
        // Flux's native rate: a third of the upload of 48 kHz, which on a
        // phone connection is audio that arrives late (spec 0009).
        'inputSampleRate' => 16000,
        'outputSampleRate' => 24000,
    ];

    /**
     * @return list<string>
     */
    public static function speakModels(): array
    {
        return array_map(static fn (string $voice): string => 'aura-2-'.$voice.'-en', self::AURA_VOICES);
    }

    /**
     * @return VoiceAgentValues
     */
    public function current(): array
    {
        return $this->normalise(VoiceAgentSetting::query()->first()->values ?? []);
    }

    /**
     * The global values with one scenario's overrides applied.
     *
     * @return VoiceAgentValues
     */
    public function forScenario(AiScenario $scenario): array
    {
        $values = $this->current();
        $overrides = $this->scenarioOverrides($scenario);

        if ($overrides['speakModel'] !== null) {
            $values['speakModel'] = $overrides['speakModel'];
        }

        if ($overrides['elevenVoiceId'] !== null) {
            $values['elevenVoiceId'] = $overrides['elevenVoiceId'];
        }

        if ($overrides['greeting'] !== null) {
            $values['greeting'] = $overrides['greeting'];
        }

        // A stored qwen_proxy choice falls back to the managed brain when the
        // proxy cannot be reached by Deepgram (non-HTTPS APP_URL, no key).
        if ($values['thinkMode'] === 'qwen_proxy' && ! $this->qwenProxyAvailable()) {
            $values['thinkMode'] = 'managed';
        }

        // The fast engine writes the guest's lines with our own text model;
        // without one the call still works on the voice agent.
        if ($values['engine'] === 'pipeline' && ! $this->pipelineAvailable()) {
            $values['engine'] = 'agent';
        }

        return $values;
    }

    /**
     * `$now` also counts a Qwen quota outage, which moves calls to the voice
     * agent for a while; the admin may still choose the engine meanwhile.
     */
    public function pipelineAvailable(bool $now = true): bool
    {
        return $this->pipelineReason($now) === null;
    }

    /**
     * Why the fast engine cannot run, or null when it can (spec 0009).
     */
    public function pipelineReason(bool $now = true): ?string
    {
        $env = config('services.ai.provider');
        $provider = app(AiModelSettings::class)->provider('ai', is_string($env) && trim($env) !== '' ? trim($env) : 'fake');

        if ($provider === 'fake') {
            return null;
        }

        if (! in_array($provider, ['qwen', 'openai'], true)) {
            return __('The fast engine needs an OpenAI-compatible text model such as Qwen; the AI provider is :provider.', ['provider' => $provider]);
        }

        if (trim(app(ApiKeyring::class)->key('services.ai.key')) === '') {
            return __('Qwen is not configured on the server (AI_KEY is empty).');
        }

        return $now ? $this->qwenQuotaReason() : null;
    }

    /**
     * Remember that Qwen said its quota is spent (VoiceReplyService, the
     * Qwen proxy), so the next calls use the voice agent.
     */
    public static function markQwenQuotaExhausted(): void
    {
        Cache::put(self::QWEN_QUOTA_FLAG, true, now()->addMinutes(self::QWEN_QUOTA_MINUTES));
    }

    private function qwenQuotaReason(): ?string
    {
        return Cache::get(self::QWEN_QUOTA_FLAG) === true
            ? (string) __('Qwen has run out of quota, so calls use the Deepgram voice agent until it is topped up.')
            : null;
    }

    /**
     * @return ScenarioVoiceOverrides
     */
    public function scenarioOverrides(AiScenario $scenario): array
    {
        $settings = $scenario->settings ?? [];
        $raw = is_array($settings['voice_agent'] ?? null) ? $settings['voice_agent'] : [];

        $speakModel = is_string($raw['speakModel'] ?? null) && in_array($raw['speakModel'], self::speakModels(), true)
            ? $raw['speakModel'] : null;
        $voiceId = is_string($raw['elevenVoiceId'] ?? null) && trim($raw['elevenVoiceId']) !== ''
            ? trim($raw['elevenVoiceId']) : null;
        $greeting = is_string($raw['greeting'] ?? null) && trim($raw['greeting']) !== ''
            ? trim($raw['greeting']) : null;

        return ['speakModel' => $speakModel, 'elevenVoiceId' => $voiceId, 'greeting' => $greeting];
    }

    /**
     * @param  array{speakModel?: string|null, elevenVoiceId?: string|null, greeting?: string|null}  $overrides
     */
    public function updateScenario(AiScenario $scenario, array $overrides): void
    {
        $settings = $scenario->settings ?? [];
        $settings['voice_agent'] = array_filter([
            'speakModel' => $overrides['speakModel'] ?? null,
            'elevenVoiceId' => $overrides['elevenVoiceId'] ?? null,
            'greeting' => $overrides['greeting'] ?? null,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== '');

        $scenario->forceFill(['settings' => $settings])->save();
    }

    /**
     * @param  array<string, mixed>  $values  validated by rules()
     */
    public function update(array $values, User $user): VoiceAgentSetting
    {
        return VoiceAgentSetting::query()->updateOrCreate(
            ['id' => VoiceAgentSetting::query()->value('id') ?? 1],
            ['values' => $this->normalise($values), 'updated_by' => $user->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'engine' => ['required', 'string', Rule::in(self::ENGINES)],
            'reuseStoredLines' => ['required', 'boolean'],
            'listenModel' => ['required', 'string', Rule::in(array_keys(self::LISTEN_MODELS))],
            'eotThreshold' => ['required', 'numeric', 'min:0.5', 'max:0.9'],
            'eagerEotThreshold' => ['required', 'numeric', 'min:0.3', 'max:0.9', 'lte:eotThreshold'],
            'eotTimeoutMs' => ['required', 'integer', 'min:500', 'max:5000'],
            'keyterms' => ['present', 'array', 'max:50'],
            'keyterms.*' => ['string', 'max:60'],
            'language' => ['required', 'string', Rule::in(['en'])],
            'speakProvider' => ['required', 'string', Rule::in(self::SPEAK_PROVIDERS)],
            'speakModel' => ['required', 'string', Rule::in(self::speakModels())],
            'elevenModelId' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'elevenVoiceId' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'thinkMode' => ['required', 'string', Rule::in(self::THINK_MODES)],
            'thinkProvider' => ['required', 'string', Rule::in(self::THINK_PROVIDERS)],
            'thinkModel' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:\-]+$/'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'greeting' => ['required', 'string', 'max:300'],
            'prompt' => ['nullable', 'string', 'max:3000'],
            'maxCallSeconds' => ['required', 'integer', 'min:60', 'max:1800'],
            'inputSampleRate' => ['required', 'integer', Rule::in(self::INPUT_SAMPLE_RATES)],
            'outputSampleRate' => ['required', 'integer', Rule::in(self::OUTPUT_SAMPLE_RATES)],
        ];
    }

    /**
     * Qwen as the agent's brain goes through our own OpenAI-compatible proxy,
     * which Deepgram must reach over public HTTPS (spec 0004 NOTES).
     */
    public function qwenProxyAvailable(bool $now = true): bool
    {
        return $this->qwenProxyReason($now) === null;
    }

    public function qwenProxyReason(bool $now = true): ?string
    {
        $appUrl = (string) config('app.url', '');

        if (! str_starts_with($appUrl, 'https://')) {
            return __('Qwen needs a public HTTPS APP_URL so Deepgram can reach the /voice-agent/llm proxy. Current APP_URL: :url', ['url' => $appUrl]);
        }

        $key = app(ApiKeyring::class)->key('services.ai.key');

        if (trim($key) === '') {
            return __('Qwen is not configured on the server (AI_KEY is empty).');
        }

        return $now ? $this->qwenQuotaReason() : null;
    }

    public function apiConfigured(): bool
    {
        return app(ApiKeyring::class)->key('services.voice_agent.key') !== '';
    }

    /**
     * Everything the admin dialog needs. No key, only whether one is set.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $qwenModel = config('services.ai.fast_model') ?: config('services.ai.model');

        return [
            'values' => $this->current(),
            'defaults' => self::DEFAULTS,
            'apiConfigured' => $this->apiConfigured(),
            // Available = configured: the admin may choose an engine during
            // a quota outage; the reason still explains the fallback.
            'qwenProxyAvailable' => $this->qwenProxyAvailable(false),
            'qwenProxyReason' => $this->qwenProxyReason(),
            'qwenModel' => is_string($qwenModel) ? $qwenModel : null,
            'pipelineAvailable' => $this->pipelineAvailable(false),
            'pipelineReason' => $this->pipelineReason(),
            'bank' => app(VoiceLineBank::class)->stats(),
            'options' => [
                'engines' => self::ENGINES,
                'listenModels' => array_keys(self::LISTEN_MODELS),
                'speakProviders' => self::SPEAK_PROVIDERS,
                'voices' => array_map(static fn (string $voice): array => [
                    'value' => 'aura-2-'.$voice.'-en',
                    'label' => ucfirst($voice),
                ], self::AURA_VOICES),
                'thinkModes' => self::THINK_MODES,
                'thinkProviders' => self::THINK_PROVIDERS,
                'thinkModelSuggestions' => [
                    'open_ai' => ['gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4.1-nano', 'gpt-4o'],
                    'anthropic' => ['claude-3-5-haiku-latest', 'claude-sonnet-4-20250514'],
                    'google' => ['gemini-2.0-flash', 'gemini-2.5-flash'],
                ],
                'inputSampleRates' => self::INPUT_SAMPLE_RATES,
                'outputSampleRates' => self::OUTPUT_SAMPLE_RATES,
            ],
            'saveUrl' => route('ai-scenarios.voice-agent.update'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @return VoiceAgentValues
     */
    private function normalise(array $raw): array
    {
        $d = self::DEFAULTS;

        $string = static fn (string $key, string $default, ?array $allowed = null): string => is_string($raw[$key] ?? null)
            && trim($raw[$key]) !== ''
            && ($allowed === null || in_array(trim($raw[$key]), $allowed, true))
            ? trim($raw[$key]) : $default;
        $number = static fn (string $key, float $default, float $min, float $max): float => is_numeric($raw[$key] ?? null)
            ? max($min, min($max, (float) $raw[$key])) : $default;
        $integer = static fn (string $key, int $default, array $allowed): int => is_numeric($raw[$key] ?? null)
            && in_array((int) $raw[$key], $allowed, true)
            ? (int) $raw[$key] : $default;

        $keyterms = [];
        foreach (is_array($raw['keyterms'] ?? null) ? $raw['keyterms'] : [] as $term) {
            if (is_string($term) && trim($term) !== '' && ! in_array(trim($term), $keyterms, true)) {
                $keyterms[] = mb_substr(trim($term), 0, 60);
            }
        }

        $maxCall = is_numeric($raw['maxCallSeconds'] ?? null)
            ? max(60, min(1800, (int) $raw['maxCallSeconds']))
            : $d['maxCallSeconds'];

        $eot = $number('eotThreshold', $d['eotThreshold'], 0.5, 0.9);
        $timeout = is_numeric($raw['eotTimeoutMs'] ?? null)
            ? max(500, min(5000, (int) $raw['eotTimeoutMs']))
            : $d['eotTimeoutMs'];
        $reuse = $raw['reuseStoredLines'] ?? $d['reuseStoredLines'];

        return [
            'engine' => $string('engine', $d['engine'], self::ENGINES),
            'reuseStoredLines' => is_bool($reuse) ? $reuse : filter_var($reuse, FILTER_VALIDATE_BOOLEAN),
            'listenModel' => $string('listenModel', $d['listenModel'], array_keys(self::LISTEN_MODELS)),
            'eotThreshold' => $eot,
            // Deepgram refuses an eager threshold above the final one.
            'eagerEotThreshold' => min($eot, $number('eagerEotThreshold', $d['eagerEotThreshold'], 0.3, 0.9)),
            'eotTimeoutMs' => $timeout,
            'keyterms' => array_slice($keyterms, 0, 50),
            'language' => 'en',
            'speakProvider' => $string('speakProvider', $d['speakProvider'], self::SPEAK_PROVIDERS),
            'speakModel' => $string('speakModel', $d['speakModel'], self::speakModels()),
            'elevenModelId' => $string('elevenModelId', $d['elevenModelId']),
            'elevenVoiceId' => $string('elevenVoiceId', $d['elevenVoiceId']),
            'thinkMode' => $string('thinkMode', $d['thinkMode'], self::THINK_MODES),
            'thinkProvider' => $string('thinkProvider', $d['thinkProvider'], self::THINK_PROVIDERS),
            'thinkModel' => $string('thinkModel', $d['thinkModel']),
            'temperature' => $number('temperature', $d['temperature'], 0, 2),
            'greeting' => $string('greeting', $d['greeting']),
            'prompt' => is_string($raw['prompt'] ?? null) ? trim($raw['prompt']) : $d['prompt'],
            'maxCallSeconds' => $maxCall,
            'inputSampleRate' => $integer('inputSampleRate', $d['inputSampleRate'], self::INPUT_SAMPLE_RATES),
            'outputSampleRate' => $integer('outputSampleRate', $d['outputSampleRate'], self::OUTPUT_SAMPLE_RATES),
        ];
    }
}
