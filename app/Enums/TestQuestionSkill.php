<?php

namespace App\Enums;

/**
 * The skill mix an admin asks the AI for when drafting test questions
 * (GEN-01, TEST-05, TSTM-03).
 *
 * Each skill maps onto one existing answerable ActivityType, so a generated
 * question is an ordinary versioned activity (DATA-11) the runner, scorer and
 * reports already understand.
 */
enum TestQuestionSkill: string
{
    case MultipleChoice = 'multiple_choice';
    case Listening = 'listening';
    case Speaking = 'speaking';
    case Writing = 'writing';
    case Ordering = 'ordering';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => __('Multiple choice'),
            self::Listening => __('Listening'),
            self::Speaking => __('Speaking'),
            self::Writing => __('Writing'),
            self::Ordering => __('Ordering'),
        };
    }

    public function activityType(): ActivityType
    {
        return match ($this) {
            self::MultipleChoice => ActivityType::MultipleChoice,
            self::Listening => ActivityType::BestResponse,
            self::Speaking => ActivityType::Speaking,
            self::Writing => ActivityType::Writing,
            self::Ordering => ActivityType::DialogueOrder,
        };
    }

    /**
     * The breakdown band a question of this skill scores into (TEST-04).
     */
    public function skillLabel(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Reading',
            self::Listening => 'Listening',
            self::Speaking => 'Speaking',
            self::Writing => 'Writing',
            self::Ordering => 'Ordering',
        };
    }

    /**
     * The skill a stored activity was drafted as, for paired generation.
     */
    public static function fromActivityType(ActivityType $type): self
    {
        return match ($type) {
            ActivityType::Speaking => self::Speaking,
            ActivityType::Writing => self::Writing,
            ActivityType::DialogueOrder, ActivityType::PictureOrder, ActivityType::Ordering => self::Ordering,
            ActivityType::BestResponse, ActivityType::ListenChoose, ActivityType::LookListen, ActivityType::ListenMatch, ActivityType::AudioQuestion => self::Listening,
            default => self::MultipleChoice,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $skill): string => $skill->value, self::cases());
    }
}
