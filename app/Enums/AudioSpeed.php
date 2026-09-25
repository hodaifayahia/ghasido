<?php

namespace App\Enums;

/**
 * The two playback speeds every playable sentence is stored at (CTRL-05,
 * TTS-01, TTS-02, spec 0003 B.4).
 *
 * Each speed is its own generated file; playback never slows a file down on
 * the client and never synthesises at play time.
 */
enum AudioSpeed: string
{
    case Normal = 'normal';
    case Slow = 'slow';

    public function label(): string
    {
        return match ($this) {
            self::Normal => __('Normal Speed'),
            self::Slow => __('Slower Speed'),
        };
    }
}
