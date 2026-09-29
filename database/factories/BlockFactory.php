<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'type' => BlockType::Text,
            'title' => null,
            'position' => 1,
            'layout' => 'full',
            'settings' => ['body' => fake()->sentence(10)],
            'is_visible' => true,
        ];
    }

    /**
     * A block of one type with a minimal settings payload that follows the
     * spec 0003 B.10 contract for it.
     */
    public function ofType(BlockType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'settings' => self::settingsFor($type),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_visible' => false]);
    }

    public function at(int $position): static
    {
        return $this->state(fn (array $attributes) => ['position' => $position]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsFor(BlockType $type): array
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
            BlockType::Text,
            BlockType::Note,
            BlockType::Image,
            BlockType::Audio,
            BlockType::EmailActivity,
            BlockType::PhoneActivity => [
                // Block creation uses this at runtime (BLD-02). Faker is a
                // development dependency, so production defaults stay plain.
                'body' => '',
                'arabic' => null,
                'image' => null,
                'audio_text' => null,
            ],
        };
    }
}
