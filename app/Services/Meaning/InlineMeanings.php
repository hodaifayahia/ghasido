<?php

namespace App\Services\Meaning;

use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Models\Block;
use App\Models\LexiconItem;
use App\Models\TextTranslation;

/**
 * The fixed Arabic fields lessons used to carry (a word's `arabic_meaning`
 * and `hotel_example_arabic`, a Listen & Repeat sentence's or a dialogue
 * line's `arabic`, the video example's `arabic`) are now ordinary Show
 * Meaning translations of their English text, kept once per text and
 * language in `text_translations` (client request 2026-11-05: "Translate
 * meaning" on those fields, any helper language, no lesson to rewrite).
 *
 * The columns and JSON keys stay (DATA-10). Wherever an inline Arabic value
 * is still written (seeded lessons, an older client, the AI lesson
 * generator), it fills the per-text store's gap for its English text. It
 * never replaces a finished translation: a hand-written one is the admin's
 * (CTRL-03), and an AI one may be a fresher draft the admin asked for.
 */
final class InlineMeanings
{
    /** The language the inline fields were written in. */
    public const string LOCALE = 'ar';

    /** Block types whose settings hold English + inline Arabic pairs. */
    public const array BLOCK_TYPES = [BlockType::ListenRepeat, BlockType::Dialogue, BlockType::Video];

    /**
     * Fill the gap for one English text: write the translation when the
     * text has none yet (or only a learner's request, a queued or a failed
     * draft). Returns whether a row was written.
     */
    public function remember(?string $english, ?string $translation, string $source = MeaningTranslations::SOURCE_MANUAL, string $locale = self::LOCALE): bool
    {
        $english = TextTranslation::normalise((string) $english);
        $translation = trim((string) $translation);

        if (! self::usable($english, $translation)) {
            return false;
        }

        $row = MeaningTranslations::find($english, $locale);

        if ($row->exists && ($row->status === GenerationStatus::Done || $row->source === MeaningTranslations::SOURCE_MANUAL)) {
            return false;
        }

        $row->forceFill([
            'source_text' => $row->source_text ?? $english,
            'translation' => $translation,
            'status' => GenerationStatus::Done,
            'source' => $source,
            'failed_reason' => null,
        ])->save();

        return true;
    }

    /**
     * An AI draft the admin chose to apply (GEN-03): it replaces an older
     * draft but never a meaning written by hand. Returns whether a row was
     * written.
     */
    public function draft(?string $english, ?string $translation, string $locale = self::LOCALE): bool
    {
        $english = TextTranslation::normalise((string) $english);
        $translation = trim((string) $translation);

        if (! self::usable($english, $translation)) {
            return false;
        }

        $row = MeaningTranslations::find($english, $locale);

        if ($row->exists && $row->source === MeaningTranslations::SOURCE_MANUAL) {
            return false;
        }

        $row->forceFill([
            'source_text' => $row->source_text ?? $english,
            'translation' => $translation,
            'status' => GenerationStatus::Done,
            'source' => MeaningTranslations::SOURCE_AI,
            'failed_reason' => null,
        ])->save();

        return true;
    }

    /**
     * Remember every inline pair of a block.
     */
    public function rememberBlock(Block $block): void
    {
        foreach (self::pairsOf($block->type, $block->settings ?? []) as [$english, $arabic]) {
            $this->remember($english, $arabic);
        }
    }

    /**
     * Remember a word's or expression's inline Arabic. An AI-made item's
     * Arabic is an AI draft (GEN-03), anything else was written by hand.
     */
    public function rememberLexicon(LexiconItem $item): void
    {
        $source = $item->source === LexiconItem::SOURCE_AI ? MeaningTranslations::SOURCE_AI : MeaningTranslations::SOURCE_MANUAL;

        $this->remember($item->english_text, $item->arabic_meaning, $source);
        $this->remember($item->hotel_example, $item->hotel_example_arabic, $source);
    }

    /**
     * The (English, Arabic) pairs a block's settings carry inline.
     *
     * @param  array<mixed>  $settings
     * @return list<array{0: string, 1: string}>
     */
    public static function pairsOf(BlockType $type, array $settings): array
    {
        $rows = match ($type) {
            BlockType::ListenRepeat => is_array($settings['items'] ?? null) ? $settings['items'] : [],
            BlockType::Dialogue => is_array($settings['lines'] ?? null) ? $settings['lines'] : [],
            BlockType::Video => [$settings['example'] ?? null],
            default => [],
        };

        $pairs = [];

        foreach ($rows as $row) {
            $english = is_array($row) ? ($row['text'] ?? null) : null;
            $arabic = is_array($row) ? ($row['arabic'] ?? null) : null;

            if (is_string($english) && is_string($arabic) && trim($english) !== '' && trim($arabic) !== '') {
                $pairs[] = [$english, $arabic];
            }
        }

        return $pairs;
    }

    private static function usable(string $english, string $translation): bool
    {
        return $english !== ''
            && $translation !== ''
            && mb_strlen($english) <= TextTranslation::MAX_LENGTH
            && preg_match('/\p{L}/u', $english) === 1;
    }
}
