<?php

namespace App\Contracts;

/**
 * One rendered audio file, as bytes, before it is stored (spec 0003 Part C).
 *
 * `durationMs` is null when the provider does not report it; the media row
 * then carries no duration rather than a guess.
 */
final readonly class SynthesisedAudio
{
    public function __construct(
        public string $binary,
        public string $mime,
        public string $extension,
        public ?int $durationMs,
    ) {}
}
