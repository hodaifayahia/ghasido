<?php

namespace App\Services\Ai;

use App\Models\AiModelSetting;
use App\Models\User;
use App\Services\Images\DashScopeImageProvider;
use App\Services\Owner\ApiKeyring;
use App\Services\Stt\DeepgramSpeechToTextProvider;
use App\Services\Tts\DeepgramVoiceCatalog;
use App\Services\Tts\TtsSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * The Super Admin's AI model overrides (API-04): which text, fast, image and
 * speech models run, and whether each paid capability is on (real) or off
 * (fake) while the client is testing.
 *
 * One stored row over .env: a blank value means "use .env". The provider
 * bindings in AppServiceProvider read overrides() at resolve time, so a save
 * applies on the next job. Keys and base URLs never live here: they stay in
 * config/services.php (API-02, SEC-03).
 *
 * The TTS voice and expressivity are the existing tts_settings row
 * (TtsSettings), edited here too, so lesson audio keeps one source of truth.
 *
 * @phpstan-type Overrides array{
 *     aiMode: string,
 *     aiModel: string,
 *     aiFastModel: string,
 *     imageMode: string,
 *     imageModel: string,
 *     imageSizeLandscape: string,
 *     imageSizeSquare: string,
 *     ttsMode: string,
 *     sttMode: string,
 *     sttModel: string,
 * }
 * @phpstan-type CheckState array{status: string, latency_ms: int|null, detail: string|null, error: string|null, checked_at: string|null, model: string|null, provider: string|null}
 */
final class AiModelSettings
{
    /** The capabilities with a fake|real switch. */
    public const array SWITCHES = ['ai', 'image', 'tts', 'stt'];

    /** The capabilities with a "Test" button. */
    public const array CHECKS = ['ai', 'fast', 'image', 'tts', 'stt'];

    /** '' = follow .env, 'fake' = off (no network, no bill), 'real' = on. */
    public const array MODES = ['', 'fake', 'real'];

    /** The real provider a capability switches to when .env says fake. */
    public const array REAL_DEFAULTS = ['ai' => 'qwen', 'image' => 'qwen', 'tts' => 'deepgram', 'stt' => 'deepgram'];

    /** Text model presets from the client's token plan. */
    public const array TEXT_PRESETS = [
        'qwen3.8-max', 'qwen3.8-flash', 'qwen3.7-max', 'qwen3.7-plus', 'qwen3.6-plus', 'qwen3.6-flash',
        'deepseek-v4-pro', 'deepseek-v4-flash', 'deepseek-v4.1-flash', 'deepseek-v3.2',
        'glm-5.3', 'glm-5.2', 'kimi-k2.6', 'kimi-k2.5', 'MiniMax-M2.5',
    ];

    public const array IMAGE_PRESETS = ['qwen-image-3.0-pro', 'qwen-image-2.0-pro', 'qwen-image-2.0', 'wan2.7-image-pro', 'wan2.7-image'];

    public const array IMAGE_LANDSCAPE_PRESETS = ['1664*928', '1472*1140', '1280*720', '1024*576'];

    public const array IMAGE_SQUARE_PRESETS = ['1328*1328', '1024*1024', '768*768', '512*512'];

    public const array STT_PRESETS = ['nova-3', 'nova-3-general', 'nova-2', 'flux-general-en'];

    /** @var Overrides */
    public const array BLANK = [
        'aiMode' => '',
        'aiModel' => '',
        'aiFastModel' => '',
        'imageMode' => '',
        'imageModel' => '',
        'imageSizeLandscape' => '',
        'imageSizeSquare' => '',
        'ttsMode' => '',
        'sttMode' => '',
        'sttModel' => '',
    ];

    private const string MODEL_PATTERN = '/^[A-Za-z0-9_.:\/\-]+$/';

    private const string SIZE_PATTERN = '/^\d{3,4}\*\d{3,4}$/';

    public function __construct(private readonly TtsSettings $tts) {}

    /**
     * The stored overrides, normalised; all blank before the first save or
     * when the table is not there yet (a fresh clone before migrate).
     *
     * @return Overrides
     */
    public function overrides(): array
    {
        try {
            $row = AiModelSetting::query()->first();
        } catch (QueryException) {
            return self::BLANK;
        }

        return $this->normalise($row->values ?? []);
    }

    /**
     * The provider a capability resolves to: the switch wins over .env; a
     * "real" switch on a fake .env picks the capability's real default.
     */
    public function provider(string $capability, string $envProvider): string
    {
        $mode = $this->overrides()[$capability.'Mode'] ?? '';

        return match ($mode) {
            'fake' => 'fake',
            'real' => $envProvider !== 'fake' ? $envProvider : (self::REAL_DEFAULTS[$capability] ?? $envProvider),
            default => $envProvider,
        };
    }

    /**
     * @param  array<string, mixed>  $values  validated by rules()
     */
    public function update(array $values, User $user): AiModelSetting
    {
        $row = AiModelSetting::query()->updateOrCreate(
            ['id' => AiModelSetting::query()->value('id') ?? 1],
            ['values' => $this->normalise($values), 'updated_by' => $user->id],
        );

        $voice = is_string($values['ttsVoice'] ?? null) ? trim($values['ttsVoice']) : '';
        $expressivity = is_numeric($values['ttsExpressivity'] ?? null) ? (int) $values['ttsExpressivity'] : null;
        $this->tts->updateOverrides($voice === '' ? null : $voice, $expressivity, $user);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $model = ['nullable', 'string', 'max:100', 'regex:'.self::MODEL_PATTERN];
        $size = ['nullable', 'string', 'regex:'.self::SIZE_PATTERN];
        $mode = ['nullable', 'string', Rule::in(self::MODES)];

        return [
            'aiMode' => $mode,
            'aiModel' => $model,
            'aiFastModel' => $model,
            'imageMode' => $mode,
            'imageModel' => $model,
            'imageSizeLandscape' => $size,
            'imageSizeSquare' => $size,
            'ttsMode' => $mode,
            'ttsVoice' => $model,
            'ttsExpressivity' => ['nullable', 'integer', Rule::in([-2, -1, 0, 1, 2])],
            'sttMode' => $mode,
            'sttModel' => $model,
        ];
    }

    // ------------------------------------------------------------- checks

    /**
     * The last connection test per capability.
     *
     * @return array<string, CheckState|null>
     */
    public function checks(): array
    {
        try {
            $stored = AiModelSetting::query()->value('checks');
        } catch (QueryException) {
            $stored = null;
        }

        $stored = is_string($stored) ? json_decode($stored, true) : $stored;
        $stored = is_array($stored) ? $stored : [];
        $checks = [];

        foreach (self::CHECKS as $capability) {
            $raw = is_array($stored[$capability] ?? null) ? $stored[$capability] : null;
            $checks[$capability] = $raw === null ? null : [
                'status' => is_string($raw['status'] ?? null) ? $raw['status'] : 'failed',
                'latency_ms' => is_numeric($raw['latency_ms'] ?? null) ? (int) $raw['latency_ms'] : null,
                'detail' => is_string($raw['detail'] ?? null) ? $raw['detail'] : null,
                'error' => is_string($raw['error'] ?? null) ? $raw['error'] : null,
                'checked_at' => is_string($raw['checked_at'] ?? null) ? $raw['checked_at'] : null,
                'model' => is_string($raw['model'] ?? null) ? $raw['model'] : null,
                'provider' => is_string($raw['provider'] ?? null) ? $raw['provider'] : null,
            ];
        }

        return $checks;
    }

    /**
     * Write one capability's check state, leaving the others untouched.
     *
     * @param  array<string, mixed>  $state
     */
    public function recordCheck(string $capability, array $state): void
    {
        $row = AiModelSetting::query()->first() ?? new AiModelSetting(['values' => []]);
        $checks = $row->checks ?? [];
        $checks[$capability] = [
            'status' => 'pending',
            'latency_ms' => null,
            'detail' => null,
            'error' => null,
            'model' => null,
            'provider' => null,
            ...$state,
            'checked_at' => Date::now()->toIso8601String(),
        ];
        $row->checks = $checks;
        $row->save();
    }

    // ----------------------------------------------------------- effective

    /**
     * The fast chat model after the admin's override, for callers that talk
     * to the endpoint directly (the live voice call). Falls back to the main
     * model, as the provider binding does (spec 0005 §2.3).
     */
    public function fastChatModel(): string
    {
        return $this->effective()['fast']['model'];
    }

    /**
     * What actually runs now, per capability, after overrides (no keys, only
     * whether one is set).
     *
     * @return array<string, array{provider: string, model: string, keyConfigured: bool}>
     */
    public function effective(): array
    {
        $o = $this->overrides();
        $aiProvider = $this->provider('ai', self::config('services.ai.provider', 'fake'));
        $aiModel = $o['aiModel'] ?: self::config('services.ai.model');
        $sttProvider = $this->provider('stt', self::config('services.stt.provider', 'fake'));

        return [
            'ai' => ['provider' => $aiProvider, 'model' => $aiModel, 'keyConfigured' => app(ApiKeyring::class)->key('services.ai.key') !== ''],
            'fast' => [
                'provider' => $aiProvider,
                'model' => $o['aiFastModel'] ?: (self::config('services.ai.fast_model') ?: $aiModel),
                'keyConfigured' => app(ApiKeyring::class)->key('services.ai.key') !== '',
            ],
            'image' => [
                'provider' => $this->provider('image', self::config('services.ai.image_provider', 'fake')),
                'model' => $o['imageModel'] ?: self::config('services.ai.image_model', DashScopeImageProvider::DEFAULT_MODEL),
                'keyConfigured' => app(ApiKeyring::class)->key('services.ai.image_key') !== '',
            ],
            'tts' => [
                'provider' => $this->provider('tts', self::config('services.tts.provider', 'fake')),
                'model' => $this->tts->voice(),
                'keyConfigured' => app(ApiKeyring::class)->key('services.tts.key') !== '',
            ],
            'stt' => [
                'provider' => $sttProvider,
                'model' => $this->sttModel($sttProvider),
                'keyConfigured' => app(ApiKeyring::class)->key('services.stt.key') !== '',
            ],
        ];
    }

    /**
     * The STT model after the override; Deepgram falls back to nova-3.
     */
    public function sttModel(string $provider): string
    {
        $model = $this->overrides()['sttModel'] ?: self::config('services.stt.model');

        return $model !== '' ? $model : ($provider === DeepgramSpeechToTextProvider::PROVIDER ? DeepgramSpeechToTextProvider::DEFAULT_MODEL : '');
    }

    /**
     * Everything the settings page needs.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $tts = $this->tts->overrides();

        return [
            'values' => [...$this->overrides(), 'ttsVoice' => $tts['voice'] ?? '', 'ttsExpressivity' => $tts['expressivity']],
            'env' => [
                'aiProvider' => self::config('services.ai.provider', 'fake'),
                'aiModel' => self::config('services.ai.model'),
                'aiFastModel' => self::config('services.ai.fast_model'),
                'imageProvider' => self::config('services.ai.image_provider', 'fake'),
                'imageModel' => self::config('services.ai.image_model', DashScopeImageProvider::DEFAULT_MODEL),
                'imageSizeLandscape' => DashScopeImageProvider::DEFAULT_LANDSCAPE_SIZE,
                'imageSizeSquare' => DashScopeImageProvider::DEFAULT_SQUARE_SIZE,
                'ttsProvider' => self::config('services.tts.provider', 'fake'),
                'ttsVoice' => self::config('services.tts.voice', 'flux-brittany-en'),
                'ttsExpressivity' => (int) config('services.tts.expressivity', 0),
                'sttProvider' => self::config('services.stt.provider', 'fake'),
                'sttModel' => self::config('services.stt.model'),
            ],
            'effective' => $this->effective(),
            'realDefaults' => self::REAL_DEFAULTS,
            'presets' => [
                'text' => self::TEXT_PRESETS,
                'image' => self::IMAGE_PRESETS,
                'imageLandscape' => self::IMAGE_LANDSCAPE_PRESETS,
                'imageSquare' => self::IMAGE_SQUARE_PRESETS,
                'ttsVoices' => DeepgramVoiceCatalog::models(),
                'stt' => self::STT_PRESETS,
            ],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @return Overrides
     */
    private function normalise(array $raw): array
    {
        $values = self::BLANK;

        foreach (array_keys(self::BLANK) as $key) {
            $value = is_string($raw[$key] ?? null) ? trim($raw[$key]) : '';

            $valid = match (true) {
                str_ends_with($key, 'Mode') => in_array($value, self::MODES, true),
                str_starts_with($key, 'imageSize') => $value === '' || preg_match(self::SIZE_PATTERN, $value) === 1,
                default => $value === '' || (mb_strlen($value) <= 100 && preg_match(self::MODEL_PATTERN, $value) === 1),
            };

            $values[$key] = $valid ? $value : '';
        }

        return $values;
    }

    private static function config(string $key, string $default = ''): string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }
}
