<?php

namespace App\Services\Stt;

use App\Contracts\SpeechToTextProvider;

/**
 * The speech-to-text stand-in for local runs and tests (spec 0003 Part C).
 * Returns a fixed marker so a voice turn still lands in the transcript.
 */
final class FakeSpeechToTextProvider implements SpeechToTextProvider
{
    public const PROVIDER = 'fake';

    public const TRANSCRIPT = '[voice message]';

    public function transcribe(string $absolutePath, string $mime): string
    {
        return self::TRANSCRIPT;
    }
}
