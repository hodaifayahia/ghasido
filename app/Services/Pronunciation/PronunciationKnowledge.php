<?php

namespace App\Services\Pronunciation;

use App\Models\PronunciationGuide;

/**
 * Everything the scorer knows about one text in one accent before it hears
 * the learner (spec 0006 §4): the guide's trap words and homophones per
 * word, and the calibration measured on our own reference audio.
 *
 * A plain value object so the scorer stays free of the database and can be
 * unit tested with a handful of arrays.
 */
final readonly class PronunciationKnowledge
{
    /**
     * @param  array<string, list<array{heard_as: string, sound: string, tip: string}>>  $traps  by reference key
     * @param  array<string, list<string>>  $homophones  by reference key
     * @param  array<string, array{ipa: string|null, syllables: string|null, sounds_like: string|null, tip: string|null}>  $entries  by reference key
     * @param  array<int, array{key: string, confidence: float|null, heard: bool}>  $calibration  by reference token index
     */
    public function __construct(
        public array $traps = [],
        public array $homophones = [],
        public array $entries = [],
        public array $calibration = [],
        public ?int $referenceDurationMs = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /**
     * The same word knowledge without the sentence's calibration: a word
     * drilled on its own is not measured against the sentence's timing.
     */
    public function withoutCalibration(): self
    {
        return new self($this->traps, $this->homophones, $this->entries);
    }

    /**
     * What a guide row and its calibration for this voice say. A guide not
     * generated yet still contributes its calibration.
     */
    public static function fromGuide(?PronunciationGuide $guide, ?string $voice = null): self
    {
        if ($guide === null) {
            return self::none();
        }

        $traps = [];
        $homophones = [];
        $entries = [];

        foreach ($guide->words ?? [] as $word) {
            $key = SpokenWords::key(is_string($word['word'] ?? null) ? $word['word'] : '');

            if ($key === '') {
                continue;
            }

            foreach (is_array($word['traps'] ?? null) ? $word['traps'] : [] as $trap) {
                $heardAs = is_array($trap) && is_string($trap['heard_as'] ?? null) ? SpokenWords::key($trap['heard_as']) : '';

                if ($heardAs !== '' && $heardAs !== $key) {
                    $traps[$key][] = [
                        'heard_as' => $heardAs,
                        'sound' => is_string($trap['sound'] ?? null) ? $trap['sound'] : '',
                        'tip' => is_string($trap['tip'] ?? null) ? $trap['tip'] : '',
                    ];
                }
            }

            foreach (is_array($word['homophones'] ?? null) ? $word['homophones'] : [] as $homophone) {
                if (is_string($homophone) && SpokenWords::key($homophone) !== '') {
                    $homophones[$key][] = SpokenWords::key($homophone);
                }
            }

            $entries[$key] = [
                'ipa' => self::stringOrNull($word['ipa'] ?? null),
                'syllables' => self::stringOrNull($word['syllables'] ?? null),
                'sounds_like' => self::stringOrNull($word['sounds_like'] ?? null),
                'tip' => self::stringOrNull($word['tip'] ?? null),
            ];
        }

        $calibration = [];
        $duration = null;
        $measured = $guide->calibration;

        if (is_array($measured) && ($voice === null || ($measured['voice'] ?? null) === $voice)) {
            foreach (is_array($measured['words'] ?? null) ? $measured['words'] : [] as $index => $entry) {
                if (is_int($index) && is_array($entry) && is_string($entry['key'] ?? null)) {
                    $confidence = $entry['confidence'] ?? null;
                    $calibration[$index] = [
                        'key' => $entry['key'],
                        'confidence' => is_numeric($confidence) ? (float) $confidence : null,
                        'heard' => (bool) ($entry['heard'] ?? false),
                    ];
                }
            }

            $duration = is_numeric($measured['duration_ms'] ?? null) ? (int) $measured['duration_ms'] : null;
        }

        return new self($traps, $homophones, $entries, $calibration, $duration);
    }

    /**
     * The trap the guide names for this word heard as that word, if any.
     *
     * @return array{heard_as: string, sound: string, tip: string}|null
     */
    public function trapFor(string $referenceKey, string $heardKey): ?array
    {
        foreach ($this->traps[$referenceKey] ?? [] as $trap) {
            if ($trap['heard_as'] === $heardKey) {
                return $trap;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function homophonesFor(string $referenceKey): array
    {
        return $this->homophones[$referenceKey] ?? [];
    }

    /**
     * @return array{ipa: string|null, syllables: string|null, sounds_like: string|null, tip: string|null}|null
     */
    public function entry(string $referenceKey): ?array
    {
        return $this->entries[$referenceKey] ?? null;
    }

    /**
     * How confidently the recogniser heard this word in our own reference
     * audio; null when not calibrated (or calibrated for other words).
     */
    public function referenceConfidence(int $index, string $key): ?float
    {
        $entry = $this->calibration[$index] ?? null;

        return $entry !== null && $entry['key'] === $key ? $entry['confidence'] : null;
    }

    /**
     * Did the recogniser fail on this word even in the reference audio? Then
     * it cannot fairly judge the learner on it (spec 0006 §2 finding 4).
     */
    public function referenceMissed(int $index, string $key): bool
    {
        $entry = $this->calibration[$index] ?? null;

        return $entry !== null && $entry['key'] === $key && ! $entry['heard'];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
