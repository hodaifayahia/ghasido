<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Contracts\ImageProvider;
use App\Contracts\SpeechToTextProvider;
use App\Contracts\TtsProvider;
use App\Enums\Role;
use App\Models\User;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\AnthropicAiProvider;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\OpenAiCompatibleAiProvider;
use App\Services\Images\DashScopeImageProvider;
use App\Services\Images\FakeImageProvider;
use App\Services\Stt\DeepgramSpeechToTextProvider;
use App\Services\Stt\FakeSpeechToTextProvider;
use App\Services\Stt\OpenAiCompatibleSpeechToTextProvider;
use App\Services\Tts\DeepgramTtsProvider;
use App\Services\Tts\FakeTtsProvider;
use App\Services\Tts\OpenAiCompatibleTtsProvider;
use App\Services\Tts\TtsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerProviders();
    }

    /**
     * The AI, TTS and STT providers, chosen by config/services.php at
     * resolve time (API-04, SEC-03; spec 0003 Part C).
     *
     * Plain bind(), not singleton, so a config change (or a test setting
     * `services.ai.provider`) takes effect on the next make(). Keys and
     * endpoints are read here and nowhere else.
     *
     * The Super Admin's Settings → AI models row (AiModelSettings) overrides
     * the model ids and can switch a capability to fake or real; a blank
     * override falls back to config. It is read inside each closure, so a
     * save applies on the next job. Keys are never overridable (SEC-03).
     */
    protected function registerProviders(): void
    {
        $this->app->bind(AiProvider::class, function (): AiProvider {
            $models = app(AiModelSettings::class);
            $overrides = $models->overrides();
            $provider = $models->provider('ai', self::configString('services.ai.provider', FakeAiProvider::PROVIDER));
            $model = $overrides['aiModel'] ?: self::configString('services.ai.model');
            $fastModel = $overrides['aiFastModel'] ?: self::configString('services.ai.fast_model');

            return match ($provider) {
                FakeAiProvider::PROVIDER => new FakeAiProvider,
                AnthropicAiProvider::PROVIDER => new AnthropicAiProvider(
                    apiKey: self::configString('services.ai.key'),
                    model: $model,
                    baseUrl: self::configString('services.ai.base_url') ?: null,
                ),
                OpenAiCompatibleAiProvider::PROVIDER, 'qwen' => new OpenAiCompatibleAiProvider(
                    apiKey: self::configString('services.ai.key'),
                    model: $model,
                    baseUrl: self::configString('services.ai.base_url') ?: null,
                    providerLabel: $provider,
                    fastModel: $fastModel ?: null,
                ),
                default => throw new InvalidArgumentException(sprintf('Unknown AI provider [%s]; set AI_PROVIDER to fake, anthropic, openai or qwen.', $provider)),
            };
        });

        $this->app->bind(TtsProvider::class, function (): TtsProvider {
            $provider = app(AiModelSettings::class)->provider('tts', self::configString('services.tts.provider', FakeTtsProvider::PROVIDER));

            return match ($provider) {
                FakeTtsProvider::PROVIDER => new FakeTtsProvider,
                DeepgramTtsProvider::PROVIDER => new DeepgramTtsProvider(
                    apiKey: self::configString('services.tts.key'),
                    model: self::configString('services.tts.model', DeepgramTtsProvider::DEFAULT_MODEL),
                    baseUrl: self::configString('services.tts.base_url') ?: null,
                    expressivity: app(TtsSettings::class)->expressivity(),
                ),
                OpenAiCompatibleTtsProvider::PROVIDER => new OpenAiCompatibleTtsProvider(
                    apiKey: self::configString('services.tts.key'),
                    model: self::configString('services.tts.model'),
                    baseUrl: self::configString('services.tts.base_url') ?: null,
                ),
                default => throw new InvalidArgumentException(sprintf('Unknown TTS provider [%s]; set TTS_PROVIDER to fake, deepgram or openai.', $provider)),
            };
        });

        $this->app->bind(SpeechToTextProvider::class, function (): SpeechToTextProvider {
            $models = app(AiModelSettings::class);
            $provider = $models->provider('stt', self::configString('services.stt.provider', FakeSpeechToTextProvider::PROVIDER));
            $sttModel = $models->sttModel($provider);

            return match ($provider) {
                FakeSpeechToTextProvider::PROVIDER => new FakeSpeechToTextProvider,
                DeepgramSpeechToTextProvider::PROVIDER => new DeepgramSpeechToTextProvider(
                    apiKey: self::configString('services.stt.key'),
                    model: $sttModel,
                    baseUrl: self::configString('services.stt.base_url') ?: null,
                ),
                OpenAiCompatibleSpeechToTextProvider::PROVIDER => new OpenAiCompatibleSpeechToTextProvider(
                    apiKey: self::configString('services.stt.key'),
                    model: $sttModel,
                    baseUrl: self::configString('services.stt.base_url') ?: null,
                ),
                default => throw new InvalidArgumentException(sprintf('Unknown STT provider [%s]; set STT_PROVIDER to fake, deepgram or openai.', $provider)),
            };
        });

        // Images (GEN-01, spec 0004): DashScope's async task API for Qwen /
        // Wan models, or the no-network fake for tests and local runs.
        $this->app->bind(ImageProvider::class, function (): ImageProvider {
            $models = app(AiModelSettings::class);
            $overrides = $models->overrides();
            $provider = $models->provider('image', self::configString('services.ai.image_provider', FakeImageProvider::PROVIDER));

            return match ($provider) {
                FakeImageProvider::PROVIDER => new FakeImageProvider,
                DashScopeImageProvider::PROVIDER, 'dashscope' => new DashScopeImageProvider(
                    apiKey: self::configString('services.ai.image_key'),
                    model: $overrides['imageModel'] ?: self::configString('services.ai.image_model', DashScopeImageProvider::DEFAULT_MODEL),
                    baseUrl: self::configString('services.ai.image_base_url') ?: null,
                    landscapeSize: $overrides['imageSizeLandscape'] ?: null,
                    squareSize: $overrides['imageSizeSquare'] ?: null,
                ),
                default => throw new InvalidArgumentException(sprintf('Unknown image provider [%s]; set AI_IMAGE_PROVIDER to fake or qwen.', $provider)),
            };
        });
    }

    /**
     * A config value as a trimmed string; blank or non-string reads as the
     * default, so an empty .env line never becomes the literal "" provider.
     */
    private static function configString(string $key, string $default = ''): string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSuperAdmin();
    }

    /**
     * The Super Admin passes every authorization check (ROLE-03, spec 0001).
     *
     * Returns null rather than false for everyone else, so the remaining
     * abilities still evaluate normally. spatie's PermissionMiddleware calls
     * canAny(), which routes through the Gate, so this one line covers the
     * route boundary as well as every policy and @can.
     */
    protected function configureSuperAdmin(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        // Settings → AI models: platform-owner only (API-04, ROLE-01).
        // Written out rather than left to Gate::before so the rule stands
        // on its own if the override ever changes.
        Gate::define('manage-ai-models', fn (User $user): bool => $user->hasRole(Role::SuperAdmin->value));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
