<?php

namespace App\Contracts;

/**
 * An AI-drafted vocabulary or expression entry (GEN-01, GEN-03).
 *
 * A draft, never the published row: the admin reviews it in the CMS and
 * applies it explicitly (GEN-03, GEN-04). Stored as `lexicon_items.ai_draft`
 * through toArray().
 */
final readonly class LexiconDraft
{
    public function __construct(
        public string $arabicMeaning,
        public string $simpleExplanation,
        public string $hotelExample,
        public string $hotelExampleArabic,
        public ?string $ipa,
        public ?string $partOfSpeech,
        public AiUsageInfo $usage,
    ) {}

    /**
     * The `lexicon_items.ai_draft` shape, keyed like the columns it may fill.
     *
     * @return array{arabic_meaning: string, simple_explanation: string, hotel_example: string, hotel_example_arabic: string, ipa: string|null, part_of_speech: string|null}
     */
    public function toArray(): array
    {
        return [
            'arabic_meaning' => $this->arabicMeaning,
            'simple_explanation' => $this->simpleExplanation,
            'hotel_example' => $this->hotelExample,
            'hotel_example_arabic' => $this->hotelExampleArabic,
            'ipa' => $this->ipa,
            'part_of_speech' => $this->partOfSpeech,
        ];
    }
}
