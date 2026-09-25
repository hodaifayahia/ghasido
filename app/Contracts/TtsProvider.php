<?php

namespace App\Contracts;

use App\Enums\AudioSpeed;

/**
 * Text-to-speech behind one interface (TTS-01, TTS-02, API-04).
 *
 * Called only from the GenerateAudioClip job, never from a request cycle:
 * playback reads the stored file (CTRL-05). The provider swaps through
 * config/services.php alone (spec 0003 Part C).
 */
interface TtsProvider
{
    /**
     * Render one sentence at one speed. The slow variant is the provider's
     * own slowed rendering, stored as its own file, never a client-side
     * stretch of the normal one.
     */
    public function synthesise(string $text, string $voice, AudioSpeed $speed): SynthesisedAudio;
}
