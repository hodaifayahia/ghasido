<?php

namespace App\Enums;

/**
 * The icon an English Essentials topic shows on the learner's list (client
 * request 2026-10-18). The admin picks one per topic; the learner side maps
 * each to a Lucide glyph or a short text mark ("Aa", "123").
 */
enum EssentialsIcon: string
{
    case Letters = 'letters';
    case Numbers = 'numbers';
    case Time = 'time';
    case Greetings = 'greetings';
    case Clarification = 'clarification';
    case Sprout = 'sprout';

    public function label(): string
    {
        return match ($this) {
            self::Letters => __('Letters & Spelling'),
            self::Numbers => __('Numbers'),
            self::Time => __('Time & Appointments'),
            self::Greetings => __('Greetings'),
            self::Clarification => __('Asking for Clarification'),
            self::Sprout => __('Other'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $icon): array => ['value' => $icon->value, 'label' => $icon->label()],
            self::cases(),
        );
    }
}
