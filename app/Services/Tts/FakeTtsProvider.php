<?php

namespace App\Services\Tts;

use App\Contracts\SynthesisedAudio;
use App\Contracts\TtsProvider;
use App\Enums\AudioSpeed;

/**
 * A text-to-speech stand-in that needs no key and no network (spec 0003
 * Part C). It writes a real, playable WAV so every audio button works
 * locally and in tests.
 *
 * The tone's pitch is derived from the text, so two different sentences
 * sound different, and the slow file is longer than the normal one, so the
 * two speeds are distinguishable by ear (TTS-01).
 */
final class FakeTtsProvider implements TtsProvider
{
    public const PROVIDER = 'fake';

    public const SAMPLE_RATE = 22050;

    public const NORMAL_SECONDS = 0.6;

    public const SLOW_SECONDS = 0.9;

    /**
     * Samples over which the tone fades in and out, so it never clicks.
     */
    private const FADE_SAMPLES = 400;

    private const AMPLITUDE = 0.6;

    public function synthesise(string $text, string $voice, AudioSpeed $speed): SynthesisedAudio
    {
        $seconds = $speed === AudioSpeed::Slow ? self::SLOW_SECONDS : self::NORMAL_SECONDS;
        $frequency = self::frequencyFor($text);
        $sampleCount = (int) round(self::SAMPLE_RATE * $seconds);

        $samples = '';
        for ($i = 0; $i < $sampleCount; $i++) {
            $envelope = min(1.0, $i / self::FADE_SAMPLES, ($sampleCount - $i) / self::FADE_SAMPLES);
            $value = (int) round(sin(2 * M_PI * $frequency * $i / self::SAMPLE_RATE) * self::AMPLITUDE * 32767 * $envelope);
            $samples .= pack('v', $value & 0xFFFF);
        }

        return new SynthesisedAudio(
            binary: self::wavHeader(strlen($samples)).$samples,
            mime: 'audio/wav',
            extension: 'wav',
            durationMs: (int) round($seconds * 1000),
        );
    }

    /**
     * 220-659 Hz, from the text's CRC so it is stable across runs.
     */
    public static function frequencyFor(string $text): int
    {
        return 220 + (crc32($text) % 440);
    }

    /**
     * A canonical 44-byte PCM WAV header: 16-bit, mono, 22050 Hz.
     */
    private static function wavHeader(int $dataBytes): string
    {
        $channels = 1;
        $bitsPerSample = 16;
        $blockAlign = (int) ($channels * $bitsPerSample / 8);
        $byteRate = self::SAMPLE_RATE * $blockAlign;

        return 'RIFF'
            .pack('V', 36 + $dataBytes)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, $channels, self::SAMPLE_RATE, $byteRate, $blockAlign, $bitsPerSample)
            .'data'
            .pack('V', $dataBytes);
    }
}
