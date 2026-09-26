<?php

namespace App\Enums;

/**
 * The English a lesson is taught in (spec 0006 §3): its audio is rendered in
 * a voice of this accent and a learner's speech is judged against it.
 *
 * Stored as the BCP 47 tag on `lessons.accent`; null there means the accent
 * of the platform voice (TtsSettings::defaultAccent()).
 */
enum Accent: string
{
    case British = 'en-GB';
    case American = 'en-US';

    public function label(?string $locale = null): string
    {
        return match ($this) {
            self::British => __('British English', [], $locale),
            self::American => __('American English', [], $locale),
        };
    }

    /**
     * The `accent` value DeepgramVoiceCatalog gives this accent's voices.
     */
    public function catalogAccent(): string
    {
        return match ($this) {
            self::British => 'British',
            self::American => 'American',
        };
    }

    /**
     * The voice used when the admin has not chosen one for this accent.
     */
    public function defaultVoice(): string
    {
        return match ($this) {
            self::British => 'flux-gemma-en',
            self::American => 'flux-hannah-en',
        };
    }

    /**
     * The accent of a catalog voice, or null for one that is neither
     * British nor American (Irish, Indian…).
     */
    public static function fromCatalogAccent(?string $accent): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->catalogAccent() === $accent) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $accent): array => ['value' => $accent->value, 'label' => $accent->label()],
            self::cases(),
        );
    }
}
