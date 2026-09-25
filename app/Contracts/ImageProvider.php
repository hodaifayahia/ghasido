<?php

namespace App\Contracts;

/**
 * Text-to-image generation behind one interface (GEN-01, API-02, API-04,
 * SEC-03; spec 0004).
 *
 * Application code depends on this, never on a provider endpoint, so the
 * image model swaps through configuration alone. Every call is made from a
 * queued job, never inside a request (PERF-04).
 */
interface ImageProvider
{
    /** Landscape, the lesson cover and situation shape. */
    public const string SIZE_LANDSCAPE = 'landscape';

    /** Square, the vocabulary card shape. */
    public const string SIZE_SQUARE = 'square';

    /**
     * Generate one image and return its downloaded bytes.
     *
     * @param  string  $size  one of the SIZE_* constants
     */
    public function generate(string $prompt, string $size = self::SIZE_LANDSCAPE): GeneratedImage;
}
