<?php

namespace App\Enums;

/**
 * How the AI guest behaves in a role-play (client request 2026-10-02:
 * "the characters are all nice people; I want an angry one, an impatient
 * one"). Stored per scenario in `settings.guest_mood` and written into the
 * AI's brief. A difficult guest is realistic, never abusive: no insults,
 * no swearing, and they calm down once they are treated well.
 */
enum GuestMood: string
{
    case Friendly = 'friendly';
    case Neutral = 'neutral';
    case Impatient = 'impatient';
    case Angry = 'angry';
    case Demanding = 'demanding';
    case Confused = 'confused';
    case Worried = 'worried';
    case Tired = 'tired';

    public function label(): string
    {
        return match ($this) {
            self::Friendly => __('Friendly and polite'),
            self::Neutral => __('Neutral, matter-of-fact'),
            self::Impatient => __('Impatient, in a hurry'),
            self::Angry => __('Angry, upset about a problem'),
            self::Demanding => __('Demanding, hard to please'),
            self::Confused => __('Confused, needs things explained'),
            self::Worried => __('Worried, anxious'),
            self::Tired => __('Tired after a long trip'),
        };
    }

    /** The line written into the AI's brief. */
    public function prompt(): string
    {
        $behaviour = match ($this) {
            self::Friendly => 'You are friendly, warm and polite.',
            self::Neutral => 'You are neutral and matter-of-fact: polite, but you do not chat.',
            self::Impatient => 'You are impatient and in a hurry: short sentences, you push for a quick answer and say so if things take too long. You relax once the employee is quick and clear.',
            self::Angry => 'You are upset and angry about a real problem with your stay. Show it clearly in your words (frustration, raised tone, insisting), but never insult or swear. Stay upset until the employee apologises sincerely and offers a concrete solution; then calm down step by step and thank them.',
            self::Demanding => 'You are demanding and hard to please: you ask for more, question the first offer and expect excellent service. You accept a good, well-explained offer.',
            self::Confused => 'You are confused: you misunderstand some things, ask the employee to repeat or explain more simply, and need clear step-by-step answers.',
            self::Worried => 'You are worried and anxious about your situation: you ask for reassurance and details. You feel better when the employee is calm, kind and clear.',
            self::Tired => 'You are very tired after a long journey: low energy, you want things to be simple and fast, and you may ask the employee to repeat.',
        };

        return 'Your mood and manner: '.$behaviour;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mood): array => ['value' => $mood->value, 'label' => $mood->label()],
            self::cases(),
        );
    }

    /** @param  array<string, mixed>|null  $settings */
    public static function fromSettings(?array $settings): self
    {
        $value = $settings['guest_mood'] ?? null;

        return is_string($value) ? (self::tryFrom($value) ?? self::Friendly) : self::Friendly;
    }
}
