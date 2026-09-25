<?php

namespace App\Contracts;

/**
 * One image an ImageProvider produced (GEN-01, MED-01).
 *
 * The bytes are already downloaded: provider URLs expire (DashScope keeps
 * them 24 hours), so nothing downstream ever stores a provider URL.
 */
final readonly class GeneratedImage
{
    public function __construct(
        public string $binary,
        public string $mime,
        public string $extension,
        public ?int $width,
        public ?int $height,
        public AiUsageInfo $usage,
    ) {}
}
