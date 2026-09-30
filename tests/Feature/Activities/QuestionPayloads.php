<?php

namespace Tests\Feature\Activities;

/**
 * One valid item of each of the client's ten question types, as the shared
 * activity editor sends it (client report 2026-09-29, spec 0003 B.9).
 */
trait QuestionPayloads
{
    /**
     * @param  array{image?: int, audio?: int, video?: int}  $media
     * @return array<string, mixed>
     */
    protected static function itemFor(string $type, array $media = []): array
    {
        $options = [
            ['id' => 'a', 'text' => 'A towel', 'image' => null, 'audio_text' => 'A towel'],
            ['id' => 'b', 'text' => 'A taxi', 'image' => null],
            ['id' => 'c', 'text' => 'The menu', 'image' => null],
        ];

        return match ($type) {
            'multiple_choice' => ['id' => 'i1', 'question' => 'What does the guest need?', 'option_style' => 'text', 'options' => $options, 'correct' => 'a'],
            'audio_question' => ['id' => 'i1', 'question' => 'What does the guest ask for?', 'audio' => $media['audio'] ?? null, 'audio_text' => null, 'option_style' => 'text', 'options' => $options, 'correct' => 'a'],
            'image_question' => ['id' => 'i1', 'question' => 'What is this?', 'image' => $media['image'] ?? null, 'option_style' => 'image', 'options' => [
                ['id' => 'a', 'text' => 'Towel', 'image' => $media['image'] ?? null],
                ['id' => 'b', 'text' => 'Pillow', 'image' => $media['image'] ?? null],
            ], 'correct' => 'a'],
            'video_question' => ['id' => 'i1', 'question' => 'What should you say now?', 'video' => $media['video'] ?? null, 'poster' => null, 'option_style' => 'text', 'options' => $options, 'correct' => 'b'],
            'ordering' => ['id' => 'i1', 'question' => 'Put the check-in in order.', 'sentences' => [
                ['id' => 's2', 'text' => 'Ask for the booking name.'],
                ['id' => 's1', 'text' => 'Greet the guest.'],
                ['id' => 's3', 'text' => 'Give the key card.'],
            ], 'order' => ['s1', 's2', 's3']],
            'matching' => ['id' => 'i1', 'question' => 'Match the words.', 'prompts' => [
                ['id' => '1', 'text' => 'Towel', 'image' => null, 'audio' => null],
                ['id' => '2', 'text' => 'Pillow', 'image' => null, 'audio' => null],
            ], 'targets' => [
                ['id' => 'b', 'text' => 'Oreiller', 'image' => null],
                ['id' => 'a', 'text' => 'Serviette', 'image' => null],
            ], 'pairs' => ['1' => 'a', '2' => 'b']],
            'short_answer' => ['id' => 'i1', 'question' => 'What do you give a guest at check-in?', 'accepted' => ['key card', 'room key']],
            'fill_blank' => ['id' => 'i1', 'question' => 'Type the missing words.', 'sentence' => 'May I see your [[b1]], please? Here is your [[b2]].', 'blanks' => [
                ['id' => 'b1', 'accepted' => ['passport', 'ID']],
                ['id' => 'b2', 'accepted' => ['key']],
            ]],
            'speaking' => ['id' => 'i1', 'question' => 'Greet the guest.', 'expected_text' => 'Good morning, welcome to our hotel.', 'max_seconds' => 20],
            'writing' => ['id' => 'i1', 'scenario' => 'A guest asks about late checkout.', 'request_text' => 'Can I leave at 2 pm?', 'information' => ['Late checkout: 30 EUR'], 'min_words' => 10, 'criteria' => [
                ['key' => 'tone', 'label' => 'Polite tone'],
                ['key' => 'content', 'label' => 'Correct information'],
            ], 'model_answer' => 'Dear Guest, late checkout at 2 pm is possible for 30 EUR.'],
            default => ['id' => 'i1'],
        };
    }
}
