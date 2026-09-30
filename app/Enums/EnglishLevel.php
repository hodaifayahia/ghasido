<?php

namespace App\Enums;

/**
 * A learner's English level: Beginner, Intermediate or Advanced (client
 * decision 2026-09-30). The learner chooses it with their department and
 * can change it later; a Pre-test at or above the Super Admin's threshold
 * suggests the next level, never forces it (PlatformSettings).
 *
 * The same three bands as ScenarioDifficulty, so a learner's level maps onto
 * the scenarios pitched at them, and the level lessons and tests are tagged
 * with (courses.level, tests.level).
 */
enum EnglishLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(?string $locale = null): string
    {
        return match ($this) {
            self::Beginner => __('Beginner', [], $locale),
            self::Intermediate => __('Intermediate', [], $locale),
            self::Advanced => __('Advanced', [], $locale),
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
            self::Intermediate => 'at intermediate level: use short, clear sentences with common hotel vocabulary, at most one question per turn, and avoid idioms and phrasal verbs they are unlikely to know.',
            self::Advanced => 'at advanced level: speak naturally as a real guest would, with the occasional follow-up question, a polite complication or a detail they must handle, while staying clear and professional.',
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
     * The level after this one, or null at Advanced: the last level has
     * nowhere to move up to (client decision 2026-09-30).
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Beginner => self::Intermediate,
            self::Intermediate => self::Advanced,
            self::Advanced => null,
        };
    }

    /**
     * The choices, for a select.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $level): array => ['value' => $level->value, 'label' => $level->label()],
            self::cases(),
        );
    }
}
