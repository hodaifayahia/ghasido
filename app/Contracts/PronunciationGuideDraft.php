<?php

namespace App\Contracts;

/**
 * How one text sounds in one accent, as the model drafted it (spec 0006 §4).
 *
 * Per word: IPA, syllables with the stressed one in capitals, a simple
 * "sounds like", one tip for Arabic speakers, the trap words a listener
 * would hear after a typical error, and homophones that count as correct.
 * An internal scoring aid the admin can review, never published content by
 * itself (GEN-03).
 *
 * @phpstan-type GuideWord array{word: string, ipa: string, syllables: string, sounds_like: string, tip: string, traps: list<array{heard_as: string, sound: string, tip: string}>, homophones: list<string>}
 */
final readonly class PronunciationGuideDraft
{
    /**
     * @param  list<GuideWord>  $words
     * @param  list<string>  $tips
     */
    public function __construct(
        public string $ipa,
        public array $words,
        public array $tips,
        public AiUsageInfo $usage,
    ) {}
}
