<?php

namespace App\Contracts;

/**
 * A batch of interface strings translated into one language (client request
 * 2026-10-03). `texts` keeps the ids it was given; a missing id was not
 * translated.
 */
final readonly class InterfaceTranslationDraft
{
    /**
     * @param  array<string, string>  $texts  id → translation
     */
    public function __construct(
        public array $texts,
        public AiUsageInfo $usage,
    ) {}
}
