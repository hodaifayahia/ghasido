<?php

namespace App\Contracts;

/**
 * The meaning of one English text in one helper language, for Show Meaning
 * (CTRL-01..03).
 */
final readonly class TextTranslationDraft
{
    public function __construct(
        public string $text,
        public AiUsageInfo $usage,
    ) {}
}
