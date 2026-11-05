<?php

namespace App\Enums;

/**
 * Ready-made step sequences for an English Essentials lesson (client
 * request 2026-11-05): beginner lessons are short drills, not the
 * department training's nine-step pipeline, so the admin starts from the
 * shape that fits the topic and fills it in the same lesson editor
 * (LESSON-02, BLD-07: the steps stay ordinary, editable block rows).
 *
 * Every template ends on the "Lesson complete" step.
 */
enum EssentialsLessonTemplate: string
{
    case WordList = 'word_list';
    case Phrases = 'phrases';
    case SayItAloud = 'say_it_aloud';
    case Conversation = 'conversation';
    case Blank = 'blank';

    /**
     * @return list<BlockType>
     */
    public function blocks(): array
    {
        return match ($this) {
            self::WordList => [BlockType::Vocabulary, BlockType::ListenRepeat, BlockType::Practice, BlockType::Complete],
            self::Phrases => [BlockType::Expressions, BlockType::ListenRepeat, BlockType::Practice, BlockType::Complete],
            self::SayItAloud => [BlockType::ListenRepeat, BlockType::Practice, BlockType::Complete],
            self::Conversation => [BlockType::Situation, BlockType::Dialogue, BlockType::ListenRepeat, BlockType::Practice, BlockType::Complete],
            self::Blank => [BlockType::Complete],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::WordList => __('Words & pictures'),
            self::Phrases => __('Useful phrases'),
            self::SayItAloud => __('Say it out loud'),
            self::Conversation => __('Short conversation'),
            self::Blank => __('Start from scratch'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WordList => __('A few key words with pictures and audio, repeated aloud, then a quick check.'),
            self::Phrases => __('Ready-to-use sentences for one moment at work, repeated aloud, then a quick check.'),
            self::SayItAloud => __('A listen-and-repeat drill: letters, numbers, times or short answers.'),
            self::Conversation => __('A short situation and dialogue with the guest, then practice.'),
            self::Blank => __('Only the closing step; add the steps you need.'),
        };
    }

    /** Suggested minutes, shown on the learner's lesson card. */
    public function minutes(): int
    {
        return match ($this) {
            self::Conversation => 10,
            self::Blank => 5,
            default => 6,
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string, minutes: int, steps: list<array{type: string, label: string}>}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $template): array => [
            'value' => $template->value,
            'label' => $template->label(),
            'description' => $template->description(),
            'minutes' => $template->minutes(),
            'steps' => array_map(static fn (BlockType $type): array => ['type' => $type->value, 'label' => $type->stepLabel()], $template->blocks()),
        ], self::cases());
    }
}
