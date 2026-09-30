<?php

namespace App\Services\Content;

use App\Enums\BlockType;

/**
 * The settings a new block starts with, per type (spec 0003 B.10), and the
 * default step pipeline of a new lesson (LESSON-02, BLD-07).
 *
 * This lives in app/, not in the model factories: production installs
 * Composer with `--no-dev`, so Faker (a dev dependency) is absent there and
 * a factory helper that calls `fake()` answers "500 Server Error" the moment
 * an admin adds a Note, Text, Image or Audio block (client report
 * 2026-09-29). Nothing here may depend on a dev package.
 */
final class BlockDefaults
{
    /**
     * The seed template of a new lesson: a default order, never a hard-coded
     * pipeline (LESSON-02). The admin adds, removes and reorders freely.
     *
     * @var list<BlockType>
     */
    public const array DEFAULT_BLOCKS = [
        BlockType::Situation,
        BlockType::Vocabulary,
        BlockType::Expressions,
        BlockType::ListenRepeat,
        BlockType::Dialogue,
        BlockType::Video,
        BlockType::Practice,
        BlockType::AiRoleplay,
        BlockType::Complete,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function settings(BlockType $type): array
    {
        return match ($type) {
            BlockType::Situation => [
                'quote' => '“A calm and polite response can turn a problem into a positive experience.”',
                'objectives' => [
                    ['icon' => 'chat', 'text' => 'Understand the situation'],
                    ['icon' => 'people', 'text' => 'Learn useful language'],
                    ['icon' => 'check', 'text' => 'Practice and be ready for real situations'],
                ],
            ],
            BlockType::Vocabulary => [
                'featured_lexicon_item_id' => null,
                'tip' => "Tap the speaker to hear the pronunciation.\nTap the eye icon to see the meaning.",
                'side_title' => 'Related Words',
            ],
            BlockType::Expressions => [
                'featured_lexicon_item_id' => null,
                'subtitle' => 'Learn and practise common phrases for this situation.',
                'tip' => "Tap the speaker to hear the pronunciation.\nTap the eye icon to see the meaning.",
                'side_title' => 'More Useful Expressions',
            ],
            BlockType::ListenRepeat => [
                'subtitle' => 'Listen to the sentence, then repeat it. Try to sound like the native speaker.',
                'tip' => 'Try to speak clearly and at a similar speed.',
                'items' => [['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟', 'image' => null]],
            ],
            BlockType::Dialogue => [
                'subtitle' => 'Listen to the conversation. Take turns and follow the dialogue step by step.',
                'image' => null,
                'situation_caption' => 'Situation: Check-in at the hotel',
                'tip' => 'Listen carefully, then repeat. Focus on pronunciation and intonation.',
                'lines' => [
                    ['speaker' => 'staff', 'text' => "Good afternoon.\nHow can I help you?", 'arabic' => null],
                    ['speaker' => 'guest', 'text' => 'Hello. I have a reservation.', 'arabic' => null],
                ],
            ],
            BlockType::Video => [
                'subtitle' => 'Watch the short video and see how the conversation happens in real life.',
                'video' => null,
                'poster' => null,
                'controls_note' => 'Watch as many times as you need.',
                'example' => ['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟', 'note' => 'This is one of the sentences from the video.'],
                'tip' => 'Watch, listen and notice the language, gestures and tone of voice.',
            ],
            BlockType::Practice => [
                'subtitle' => 'Choose a practice activity to improve your skills.',
                'motto' => 'Complete the activities and take a step closer to real conversations!',
            ],
            BlockType::Quiz => [
                'subtitle' => 'Answer the questions to check what you have learned.',
                'motto' => 'Take your time and read each question carefully.',
            ],
            BlockType::AiRoleplay => [
                'subtitle' => 'Choose a scenario and practice with our AI guest.',
                'scenario_ids' => [],
                'tip' => 'Choose a situation that is relevant to your job. You can try each scenario up to 3 times.',
            ],
            BlockType::Complete => [
                'subtitle' => 'Great job! You have finished this lesson.',
                'image' => null,
                'quote' => "“Small steps\nmake a big difference!”",
                'closing_quote' => '“Better communication creates happier guests.”',
                'encouragement' => "Today you practiced\na real-life situation.\nWith more practice, you will feel more confident in speaking English with guests.",
            ],
            // The generic contract starts empty: the admin writes the body in
            // the block editor, and a learner never sees placeholder copy.
            BlockType::Text,
            BlockType::Note,
            BlockType::Image,
            BlockType::Audio,
            BlockType::EmailActivity,
            BlockType::PhoneActivity => [
                'body' => '',
                'arabic' => null,
                'image' => null,
                'audio_text' => null,
            ],
        };
    }
}
