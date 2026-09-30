<?php

namespace App\Services\Meaning;

use App\Enums\GenerationStatus;
use App\Models\TextTranslation;
use App\Models\User;

/**
 * Lessons store some meanings inline in Arabic, the first helper language:
 * a word's `arabic_meaning`, a dialogue line's `arabic`, an activity's
 * `prompt_arabic`. A learner who reads meanings in another helper language
 * (French, Malay…; client request 2026-09-30) gets that language instead:
 * each inline Arabic value is swapped for the stored translation of its
 * English text in their language, or removed when there is none yet. The
 * Arabic never reaches them, and nothing is ever translated on the fly.
 *
 * Arabic readers get the payload unchanged.
 */
final class HelperMeaningSwap
{
    /** Inline meaning keys and the English sibling each one translates. */
    private const array PAIRS = [
        'arabic' => ['text', 'english', 'english_text', 'prompt', 'question', 'sentence'],
        'promptArabic' => ['prompt'],
        'prompt_arabic' => ['prompt'],
        'exampleArabic' => ['example'],
        'arabicMeaning' => ['englishText', 'text'],
        'arabic_meaning' => ['english_text', 'text'],
        'hotelExampleArabic' => ['hotelExample', 'example'],
        'hotel_example_arabic' => ['hotel_example', 'example'],
    ];

    public function __construct(private readonly HelperLanguages $languages) {}

    /**
     * @template T of array
     *
     * @param  T  $payload
     * @return T
     */
    public function apply(array $payload, User $user): array
    {
        $locale = $this->languages->forUser($user);

        if ($locale === 'ar') {
            return $payload;
        }

        $texts = [];
        $this->collect($payload, null, $texts);

        $translations = $texts === [] ? [] : TextTranslation::query()
            ->where('locale', $locale)
            ->where('status', GenerationStatus::Done->value)
            ->whereIn('hash', array_map(TextTranslation::hashOf(...), array_keys($texts)))
            ->pluck('translation', 'hash')
            ->all();

        /** @var T $swapped */
        $swapped = $this->swap($payload, null, $translations);

        return $swapped;
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<mixed>|null  $parent
     * @param  array<string, true>  $texts
     */
    private function collect(array $node, ?array $parent, array &$texts): void
    {
        foreach (self::PAIRS as $key => $siblings) {
            if (array_key_exists($key, $node) && is_string($english = $this->english($node, $parent, $key, $siblings))) {
                $texts[$english] = true;
            }
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collect($child, $node, $texts);
            }
        }
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<mixed>|null  $parent
     * @param  array<string, string|null>  $translations
     * @return array<mixed>
     */
    private function swap(array $node, ?array $parent, array $translations): array
    {
        $original = $node;

        foreach ($node as $key => $child) {
            if (is_array($child)) {
                $node[$key] = $this->swap($child, $original, $translations);
            }
        }

        foreach (self::PAIRS as $key => $siblings) {
            if (! array_key_exists($key, $original)) {
                continue;
            }

            $english = $this->english($original, $parent, $key, $siblings);
            $node[$key] = $english === null
                ? null
                : ($translations[TextTranslation::hashOf($english)] ?? null);
        }

        return $node;
    }

    /**
     * The English text an inline meaning belongs to: a sibling in the same
     * node, or, for a `meaning` object, its owner's text.
     *
     * @param  array<mixed>  $node
     * @param  array<mixed>|null  $parent
     * @param  list<string>  $siblings
     */
    private function english(array $node, ?array $parent, string $key, array $siblings): ?string
    {
        foreach ([$node, $parent] as $candidate) {
            if ($candidate === null) {
                continue;
            }

            foreach ($siblings as $sibling) {
                $value = $candidate[$sibling] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    return TextTranslation::normalise($value);
                }
            }

            // Only a `meaning` object looks up to its owner; and in the
            // owner a meaning's example is the owner's example.
            if ($key === 'exampleArabic' || $key === 'arabic') {
                continue;
            }

            break;
        }

        return null;
    }
}
