<?php

namespace App\Services\Audio;

use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use App\Jobs\GenerateAudioClip;
use App\Models\AudioClip;
use App\Models\MediaAsset;
use App\Services\Tts\TtsSettings;

/**
 * The one place a playable sentence turns into a stored file (CTRL-05,
 * TTS-01, TTS-02, spec 0003 B.4).
 *
 * `ensure()` is what content writes call: it finds or creates the clip row
 * and queues generation once. `urlFor()` / `urlsFor()` are what page shapers
 * call: they read, and only read, so a page render can never trigger a TTS
 * call. A missing file resolves to null and the speaker button renders
 * disabled, never a live synthesis.
 */
class AudioLibrary
{
    /**
     * The voice every clip is generated with (spec 0003 B.4, Part C).
     */
    public function voice(?TtsSettings $settings = null): string
    {
        $voice = ($settings ?? app(TtsSettings::class))->voice();

        return $voice !== '' ? $voice : 'flux-brittany-en';
    }

    /**
     * Find or create the clip for this sentence at this speed, queueing its
     * generation when it is new or has failed before.
     */
    public function ensure(string $text, AudioSpeed $speed, ?TtsSettings $settings = null): AudioClip
    {
        $normalised = AudioClip::normalise($text);
        $provider = $this->provider();

        $clip = AudioClip::query()->firstOrCreate(
            [
                'text_hash' => AudioClip::hashFor($normalised),
                'voice' => $this->voice($settings),
                'speed' => $speed,
            ],
            [
                'text' => $normalised,
                'status' => GenerationStatus::Pending,
            ],
        );

        $providerChanged = $clip->isDone()
            && $clip->provider !== null
            && $clip->provider !== 'upload'
            && $clip->provider !== $provider;

        if ($providerChanged) {
            // Do not keep serving a tone (or a clip from a different TTS
            // provider) after the administrator changes the configured
            // provider. The old asset remains recoverable, but it is no
            // longer part of the active playback path (TTS-02, API-04).
            $clip->forceFill([
                'media_asset_id' => null,
                'status' => GenerationStatus::Pending,
                'failed_reason' => null,
                'provider' => null,
                'generated_at' => null,
            ])->save();
        }

        if ($clip->wasRecentlyCreated || $clip->status === GenerationStatus::Failed || $providerChanged) {
            if (! $clip->wasRecentlyCreated && ! $providerChanged) {
                $clip->forceFill(['status' => GenerationStatus::Pending, 'failed_reason' => null])->save();
            }

            GenerateAudioClip::dispatch($clip->id);
        }

        return $clip;
    }

    /**
     * Both speeds of one sentence, queued if new.
     *
     * @return array{normal: AudioClip, slow: AudioClip}
     */
    public function ensureBoth(string $text, ?TtsSettings $settings = null): array
    {
        $settings ??= app(TtsSettings::class);

        return [
            'normal' => $this->ensure($text, AudioSpeed::Normal, $settings),
            'slow' => $this->ensure($text, AudioSpeed::Slow, $settings),
        ];
    }

    /**
     * Point one clip at an uploaded file, replacing whatever was generated
     * (TTS-03: the admin may upload their own audio). The clip is marked done
     * so playback resolves immediately, keyed by the text exactly as content
     * stores it.
     */
    public function attachUpload(string $text, AudioSpeed $speed, MediaAsset $media): AudioClip
    {
        $normalised = AudioClip::normalise($text);

        $clip = AudioClip::query()->firstOrCreate(
            [
                'text_hash' => AudioClip::hashFor($normalised),
                'voice' => $this->voice(),
                'speed' => $speed,
            ],
            [
                'text' => $normalised,
                'status' => GenerationStatus::Pending,
            ],
        );

        $clip->forceFill([
            'media_asset_id' => $media->id,
            'status' => GenerationStatus::Done,
            'failed_reason' => null,
            'provider' => 'upload',
            'generated_at' => now(),
        ])->save();

        return $clip;
    }

    /**
     * The playable URL for one sentence at one speed, or null while it does
     * not exist. Never queues anything.
     */
    public function urlFor(string $text, AudioSpeed $speed): ?string
    {
        $clip = AudioClip::query()
            ->with('mediaAsset')
            ->forVoice($this->voice())
            ->activeProvider($this->provider())
            ->done()
            ->where('text_hash', AudioClip::hashFor($text))
            ->where('speed', $speed)
            ->first();

        return $clip?->url();
    }

    /**
     * Both URLs for every sentence a page plays, in one query, keyed by the
     * text exactly as passed (spec 0003 B.4).
     *
     * @param  iterable<string>  $texts
     * @return array<string, array{normal: ?string, slow: ?string}>
     */
    public function urlsFor(iterable $texts): array
    {
        $byHash = [];

        foreach ($texts as $text) {
            if (trim($text) === '') {
                continue;
            }

            $byHash[AudioClip::hashFor($text)][] = $text;
        }

        if ($byHash === []) {
            return [];
        }

        $clips = AudioClip::query()
            ->with('mediaAsset')
            ->forVoice($this->voice())
            ->activeProvider($this->provider())
            ->done()
            ->whereIn('text_hash', array_keys($byHash))
            ->get();

        /** @var array<string, string|null> $normal */
        $normal = [];
        /** @var array<string, string|null> $slow */
        $slow = [];

        foreach ($clips as $clip) {
            if ($clip->speed === AudioSpeed::Slow) {
                $slow[$clip->text_hash] = $clip->url();
            } else {
                $normal[$clip->text_hash] = $clip->url();
            }
        }

        $result = [];

        foreach ($byHash as $hash => $originals) {
            foreach ($originals as $original) {
                $result[$original] = [
                    'normal' => $normal[$hash] ?? null,
                    'slow' => $slow[$hash] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Uploaded files are provider-independent. Generated files must belong to
     * the selected provider so stale fake tones cannot be returned after a
     * provider change (TTS-02, API-04).
     */
    private function provider(): string
    {
        // The test suite intentionally exercises the no-network fake
        // provider even when a developer's local .env contains production
        // provider settings.
        if (app()->environment('testing')) {
            return 'fake';
        }

        $provider = config('services.tts.provider', 'fake');

        return is_string($provider) && trim($provider) !== '' ? trim($provider) : 'fake';
    }
}
