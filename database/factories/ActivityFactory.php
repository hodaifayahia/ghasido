<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Enums\ContentStatus;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ActivityType::MultipleChoice,
            'skill_label' => 'Situation',
            'title' => null,
            'prompt' => 'Look at the situation and choose the best response.',
            'prompt_arabic' => null,
            'payload' => self::payloadFor(ActivityType::MultipleChoice),
            'scoring' => null,
            'time_limit_seconds' => null,
            'attempts_allowed' => 0,
            'show_meaning_enabled' => true,
            'department_id' => null,
            'hotel_id' => null,
            'status' => ContentStatus::Published,
            'created_by' => null,
        ];
    }

    /**
     * An activity of one type with the spec 0003 B.9 sample payload for it.
     */
    public function ofType(ActivityType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'skill_label' => $type->label(),
            'prompt' => $type->hubDescription(),
            'payload' => self::payloadFor($type),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContentStatus::Draft]);
    }

    /**
     * The payload shape for each type, one item each, matching the contracts
     * in spec 0003 B.9. Media ids are placeholders: a real payload points at
     * media_assets rows.
     *
     * @return array<string, mixed>
     */
    public static function payloadFor(ActivityType $type): array
    {
        $item = match ($type) {
            ActivityType::ListenChoose => [
                'id' => 'i1',
                'audio_text' => 'Towel',
                'options' => [
                    ['id' => 'a', 'label' => 'Towel', 'image' => null],
                    ['id' => 'b', 'label' => 'Pillow', 'image' => null],
                    ['id' => 'c', 'label' => 'Room key', 'image' => null],
                ],
                'correct' => 'a',
            ],
            ActivityType::LookListen => [
                'id' => 'i1',
                'image' => null,
                'options' => [
                    ['id' => 'a', 'audio_text' => 'Room key'],
                    ['id' => 'b', 'audio_text' => 'Towel'],
                    ['id' => 'c', 'audio_text' => 'Slippers'],
                ],
                'correct' => 'a',
            ],
            ActivityType::BestResponse => [
                'id' => 'i1',
                'situation' => 'A guest is asking if there are any available rooms.',
                'guest_audio_text' => 'Do you have any rooms available tonight?',
                'options' => [
                    ['id' => 'a', 'text' => "Of course. I'll check for you right away.", 'image' => null],
                    ['id' => 'b', 'text' => "No, we don't have any rooms available.", 'image' => null],
                    ['id' => 'c', 'text' => 'You can come back later.', 'image' => null],
                ],
                'correct' => 'a',
            ],
            ActivityType::ListenMatch => [
                'id' => 'i1',
                'prompts' => [
                    ['id' => '1', 'audio_text' => 'Towel'],
                    ['id' => '2', 'audio_text' => 'Pillow'],
                    ['id' => '3', 'audio_text' => 'Hair dryer'],
                ],
                'targets' => [
                    ['id' => 'a', 'label' => 'Towel', 'image' => null],
                    ['id' => 'b', 'label' => 'Pillow', 'image' => null],
                    ['id' => 'c', 'label' => 'Hair dryer', 'image' => null],
                    ['id' => 'd', 'label' => 'Room key', 'image' => null],
                ],
                'pairs' => ['1' => 'a', '2' => 'b', '3' => 'c'],
            ],
            ActivityType::WatchRespond => [
                'id' => 'i1',
                'video' => null,
                'poster' => null,
                'subtitle' => "Guest: I'm sorry, but my room isn't clean yet.",
                'question' => 'What should you say now?',
                'hint' => 'Choose the best response.',
                'options' => [
                    ['id' => 'a', 'text' => "I'm very sorry for the inconvenience. Let me check this for you right away."],
                    ['id' => 'b', 'text' => "I don't know. Maybe later."],
                    ['id' => 'c', 'text' => 'You can wait in the lobby.'],
                ],
                'correct' => 'a',
            ],
            ActivityType::WordsSentences => [
                'id' => 'i1',
                'sentence' => 'May I see your ____, please?',
                'audio_text' => 'May I see your passport, please?',
                'image' => null,
                'options' => [
                    ['id' => 'a', 'label' => 'passport', 'image' => null],
                    ['id' => 'b', 'label' => 'towel', 'image' => null],
                    ['id' => 'c', 'label' => 'credit card', 'image' => null],
                ],
                'correct' => 'a',
            ],
            ActivityType::DialogueOrder => [
                'id' => 'i1',
                'audio_text' => "Good afternoon. Welcome to La Gazelle d'Or. I have a reservation under the name Ben Ali. Here is your key. Your room is 215. Thank you very much. Enjoy your stay.",
                'sentences' => [
                    ['id' => 's1', 'text' => 'Here is your key. Your room is 215.'],
                    ['id' => 's2', 'text' => "Good afternoon. Welcome to La Gazelle d'Or."],
                    ['id' => 's3', 'text' => 'Thank you very much.'],
                    ['id' => 's4', 'text' => 'I have a reservation under the name Ben Ali.'],
                    ['id' => 's5', 'text' => 'Enjoy your stay.'],
                ],
                'order' => ['s2', 's4', 's1', 's3', 's5'],
            ],
            ActivityType::PictureOrder => [
                'id' => 'i1',
                'context' => 'A guest is asking for information about breakfast.',
                'cards' => [
                    ['id' => 'A', 'image' => null, 'caption' => 'What time is breakfast served?'],
                    ['id' => 'B', 'image' => null, 'caption' => 'It is served from 7 a.m. to 10 a.m.'],
                    ['id' => 'C', 'image' => null, 'caption' => 'Great, thank you!'],
                    ['id' => 'D', 'image' => null, 'caption' => "You're welcome!"],
                ],
                'order' => ['A', 'B', 'C', 'D'],
            ],
            ActivityType::MultipleChoice => [
                'id' => 'i1',
                'question' => 'A guest is at the reception desk with a large suitcase. What would you say?',
                'subtitle' => null,
                'image' => null,
                'passage' => null,
                'layout' => 'side',
                'options' => [
                    ['id' => 'A', 'text' => 'Good evening! Can I help you with your luggage?'],
                    ['id' => 'B', 'text' => 'The restaurant is over there.'],
                    ['id' => 'C', 'text' => 'Your room is on the first floor.'],
                    ['id' => 'D', 'text' => 'Please wait outside.'],
                ],
                'correct' => 'A',
            ],
            ActivityType::Speaking => [
                'id' => 'i1',
                'question' => 'What would you say in this situation?',
                'situation' => 'A guest is not happy because the room is not clean.',
                'instruction' => 'Record a short and polite response.',
                'image' => null,
                'max_seconds' => 20,
            ],
            ActivityType::Writing => [
                'id' => 'i1',
                'scenario' => 'A guest emails to ask about room rates for two nights in July.',
                'request_text' => 'Hello, could you tell me the price of a double room for 12–14 July, including breakfast? Thank you.',
                'information' => [
                    'Double room: 12,000 DZD per night',
                    'Breakfast: included',
                    'Tax: 19% VAT included',
                ],
                'min_words' => 20,
            ],
        };

        return ['items' => [$item]];
    }
}
