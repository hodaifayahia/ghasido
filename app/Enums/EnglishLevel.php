<?php

namespace App\Enums;

/**
 * A learner's working English level, measured by their latest Pre- or
 * Post-test (spec 0005 §2.1).
 *
 * The same three bands as ScenarioDifficulty, so a learner's level maps onto
 * the scenarios pitched at them. Deliberately not CEFR: whether the platform
 * adopts A1/A2/B1 is an open client decision (system/08 8.1). The score
 * thresholds live in config('guesvia.levels.thresholds').
 */
enum EnglishLevel: string
{
    case Beginner = 'beginner';
    case Elementary = 'elementary';
    case Intermediate = 'intermediate';

    public function label(?string $locale = null): string
    {
        return match ($this) {
            self::Beginner => __('Beginner', [], $locale),
            self::Elementary => __('Elementary', [], $locale),
            self::Intermediate => __('Intermediate', [], $locale),
        };
    }

    /**
     * How the AI should pitch its English to this learner. Read by the
     * role-play guest and every evaluator, so the guest speaks at the
     * learner's level and feedback is judged against it (RP-08, RP-09).
     */
    public function promptDescription(): string
    {
        return match ($this) {
            self::Beginner => 'a beginner: use very short, simple sentences (6-10 words), everyday hotel words only, one question at a time, and no idioms. If they struggle, rephrase more simply rather than moving on.',
            self::Elementary => 'at elementary level: use short, clear sentences with common hotel vocabulary, at most one question per turn, and avoid idioms and phrasal verbs they are unlikely to know.',
            self::Intermediate => 'at intermediate level: speak naturally as a real guest would, with the occasional follow-up question, a polite complication or a detail they must handle, while staying clear and professional.',
        };
    }

    /**
     * The scenario difficulty pitched at this level.
     */
    public function difficulty(): ScenarioDifficulty
    {
        return ScenarioDifficulty::from($this->value);
    }

    /**
     * The level a test percentage (0-100) places a learner at.
     */
    public static function fromPercent(float $percent): self
    {
        /** @var array<string, int|float> $thresholds */
        $thresholds = config('guesvia.levels.thresholds', []);

        if ($percent >= (float) ($thresholds[self::Intermediate->value] ?? 70)) {
            return self::Intermediate;
        }

        if ($percent >= (float) ($thresholds[self::Elementary->value] ?? 40)) {
            return self::Elementary;
        }

        return self::Beginner;
    }
}
