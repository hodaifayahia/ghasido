<?php

namespace App\Services\Pronunciation;

/**
 * The verdict on one recording (spec 0006 §5): a status per reference word,
 * the extra words and hesitations, and the three scores.
 *
 * @phpstan-type WordResult array{index: int, text: string, key: string, status: string, heard: string|null, confidence: float|null, reference_confidence: float|null, start_ms: int|null, end_ms: int|null, sound: string|null, tip: string|null, trap: bool}
 * @phpstan-type Extra array{word: string, confidence: float|null, start_ms: int|null}
 */
final readonly class PronunciationOutcome
{
    public const CORRECT = 'correct';

    public const UNCLEAR = 'unclear';

    public const ALMOST = 'almost';

    public const MISPRONOUNCED = 'mispronounced';

    public const DIFFERENT = 'different';

    public const MISSED = 'missed';

    public const SKIPPED = 'skipped';

    public const LEVEL_NOT_HEARD = 'not_heard';

    /**
     * @param  list<WordResult>  $words
     * @param  list<Extra>  $extras
     */
    public function __construct(
        public array $words,
        public array $extras,
        public int $fillers,
        public int $longPauses,
        public ?float $speedRatio,
        public ?float $score,
        public ?float $wordsScore,
        public ?float $clarityScore,
        public ?float $flowScore,
        public string $level,
        public bool $heardAnything,
        public string $version,
    ) {}

    /**
     * Every judged word heard clearly: no coaching call needed.
     */
    public function isPerfect(): bool
    {
        if (! $this->heardAnything) {
            return false;
        }

        foreach ($this->words as $word) {
            if ($word['status'] !== self::CORRECT && $word['status'] !== self::SKIPPED) {
                return false;
            }
        }

        return true;
    }

    /**
     * A word a hinted second listen could still find (spec 0006 §5 step 3).
     * A trap hit is already a named error, so it is not re-listened for.
     */
    public function needsHintedListen(): bool
    {
        if (! $this->heardAnything) {
            return false;
        }

        foreach ($this->words as $word) {
            if (
                in_array($word['status'], [self::MISPRONOUNCED, self::DIFFERENT, self::MISSED], true)
                && ! $word['trap']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The words to work on, worst first, for the coach and the drill.
     *
     * @return list<WordResult>
     */
    public function weakWords(): array
    {
        $rank = [self::MISSED => 0, self::DIFFERENT => 1, self::MISPRONOUNCED => 2, self::ALMOST => 3, self::UNCLEAR => 4];

        $weak = array_values(array_filter(
            $this->words,
            static fn (array $word): bool => isset($rank[$word['status']]),
        ));

        usort($weak, static fn (array $a, array $b): int => [$rank[$a['status']], $a['index']] <=> [$rank[$b['status']], $b['index']]);

        return $weak;
    }
}
