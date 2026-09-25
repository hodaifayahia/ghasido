<?php

namespace App\Services\Tts;

use App\Enums\Accent;
use App\Models\TtsSetting;
use App\Models\User;
use App\Services\Owner\ApiKeyring;

/**
 * Resolves global TTS configuration for admins, lesson generation and
 * learner playback (TTS-02, TTS-04, API-04).
 *
 * Besides the platform voice (role-play guests, tests, lessons with no
 * accent), each accent has its own lesson voice (spec 0006 §3): the one the
 * admin chose, else the platform voice when it is of that accent, else the
 * accent's default.
 */
final class TtsSettings
{
    /** @return array<string, mixed> */
    public function payload(): array
    {
        $setting = TtsSetting::query()->first();
        $current = $this->current($setting);

        return [
            ...$current,
            'apiConfigured' => $this->apiConfigured(),
            'voices' => DeepgramVoiceCatalog::all(),
            'filters' => DeepgramVoiceCatalog::filters(),
            'britishVoice' => $this->voiceFor(Accent::British, $setting),
            'americanVoice' => $this->voiceFor(Accent::American, $setting),
            'defaultAccent' => $this->defaultAccent($setting)->value,
        ];
    }

    public function voice(): string
    {
        return $this->current(TtsSetting::query()->first())['voice'];
    }

    /**
     * The voice lesson audio of this accent is rendered in (spec 0006 §3).
     * A platform voice outside the Deepgram catalog (an OpenAI-compatible
     * provider's voice) serves both accents: there is nothing to switch to.
     */
    public function voiceFor(Accent $accent, ?TtsSetting $setting = null): string
    {
        $setting ??= TtsSetting::query()->first();
        $platform = $this->current($setting)['voice'];

        if (DeepgramVoiceCatalog::accentOf($platform) === null) {
            return $platform;
        }

        $chosen = $accent === Accent::British ? $setting?->british_voice : $setting?->american_voice;

        if (is_string($chosen) && DeepgramVoiceCatalog::accentOf($chosen) === $accent->catalogAccent()) {
            return $chosen;
        }

        if (DeepgramVoiceCatalog::accentOf($platform) === $accent->catalogAccent()) {
            return $platform;
        }

        return $accent->defaultVoice();
    }

    /**
     * The accent of a lesson that has none: the platform voice's, American
     * when that voice is neither British nor American.
     */
    public function defaultAccent(?TtsSetting $setting = null): Accent
    {
        $platform = $this->current($setting ?? TtsSetting::query()->first())['voice'];

        return Accent::fromCatalogAccent(DeepgramVoiceCatalog::accentOf($platform)) ?? Accent::American;
    }

    public function expressivity(): int
    {
        return $this->current(TtsSetting::query()->first())['expressivity'];
    }

    /** @param array{voice: string, expressivity: int, british_voice?: string|null, american_voice?: string|null} $values */
    public function update(array $values, User $user): TtsSetting
    {
        $attributes = [
            'voice' => $values['voice'],
            'expressivity' => $values['expressivity'],
            'updated_by' => $user->id,
        ];

        foreach (['british_voice', 'american_voice'] as $key) {
            if (array_key_exists($key, $values)) {
                $attributes[$key] = $values[$key];
            }
        }

        return TtsSetting::query()->updateOrCreate(
            ['id' => TtsSetting::query()->value('id') ?? 1],
            $attributes,
        );
    }

    /**
     * The stored values only, null where .env applies (Settings → AI models).
     *
     * @return array{voice: string|null, expressivity: int|null}
     */
    public function overrides(): array
    {
        $setting = TtsSetting::query()->first();

        return [
            'voice' => $setting?->voice ?: null,
            'expressivity' => $setting?->expressivity,
        ];
    }

    /**
     * Save the voice and expressivity, null meaning "use .env" (API-04).
     */
    public function updateOverrides(?string $voice, ?int $expressivity, User $user): TtsSetting
    {
        return TtsSetting::query()->updateOrCreate(
            ['id' => TtsSetting::query()->value('id') ?? 1],
            ['voice' => $voice, 'expressivity' => $expressivity, 'updated_by' => $user->id],
        );
    }

    /** @return array{provider: string, model: string, voice: string, expressivity: int} */
    private function current(?TtsSetting $setting): array
    {
        $provider = config('services.tts.provider', 'fake');
        $model = config('services.tts.model', DeepgramTtsProvider::DEFAULT_MODEL);
        $configuredVoice = config('services.tts.voice', DeepgramTtsProvider::DEFAULT_MODEL);
        $configuredExpressivity = config('services.tts.expressivity', 0);

        return [
            'provider' => is_string($provider) && $provider !== '' ? $provider : 'fake',
            'model' => is_string($model) && $model !== '' ? $model : DeepgramTtsProvider::DEFAULT_MODEL,
            'voice' => $setting?->voice ?: (is_string($configuredVoice) && $configuredVoice !== '' ? $configuredVoice : DeepgramTtsProvider::DEFAULT_MODEL),
            'expressivity' => $setting->expressivity ?? (int) $configuredExpressivity,
        ];
    }

    private function apiConfigured(): bool
    {
        return app(ApiKeyring::class)->key('services.tts.key') !== '';
    }
}
