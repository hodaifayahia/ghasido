<?php

namespace App\Services\Tts;

use App\Models\TtsSetting;
use App\Models\User;

/**
 * Resolves global TTS configuration for admins, lesson generation and
 * learner playback (TTS-02, TTS-04, API-04).
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
        ];
    }

    public function voice(): string
    {
        return $this->current(TtsSetting::query()->first())['voice'];
    }

    public function expressivity(): int
    {
        return $this->current(TtsSetting::query()->first())['expressivity'];
    }

    /** @param array{voice: string, expressivity: int} $values */
    public function update(array $values, User $user): TtsSetting
    {
        return TtsSetting::query()->updateOrCreate(
            ['id' => TtsSetting::query()->value('id') ?? 1],
            [
                'voice' => $values['voice'],
                'expressivity' => $values['expressivity'],
                'updated_by' => $user->id,
            ],
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
        $key = config('services.tts.key');

        return is_string($key) && trim($key) !== '';
    }
}
