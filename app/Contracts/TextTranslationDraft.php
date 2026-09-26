<?php

namespace App\Contracts;

/**
 * The Arabic meaning of one English text, for Show Meaning (CTRL-01..03).
 */
final readonly class TextTranslationDraft
{
    public function __construct(
        public string $arabic,
        public AiUsageInfo $usage,
    ) {}
}
