<?php

namespace Database\Factories;

use App\Enums\LexiconKind;
use App\Models\LexiconItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LexiconItem>
 */
class LexiconItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => LexiconKind::Word,
            'english_text' => fake()->unique()->word(),
            'ipa' => null,
            'part_of_speech' => 'n',
            'arabic_meaning' => 'معنى',
            'simple_explanation' => fake()->sentence(8),
            'hotel_example' => fake()->sentence(8),
            'hotel_example_arabic' => null,
            'image_media_id' => null,
            'source' => LexiconItem::SOURCE_MANUAL,
            'ai_draft' => null,
            'ai_status' => null,
            'show_meaning_enabled' => true,
            'department_id' => null,
            'hotel_id' => null,
            'created_by' => null,
        ];
    }

    public function expression(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => LexiconKind::Expression,
            'english_text' => fake()->unique()->sentence(4),
            'part_of_speech' => 'phrase',
        ]);
    }

    /** The word without any meaning the admin has not written yet. */
    public function withoutMeaning(): static
    {
        return $this->state(fn (array $attributes) => [
            'arabic_meaning' => null,
            'simple_explanation' => null,
            'hotel_example' => null,
            'hotel_example_arabic' => null,
        ]);
    }

    public function showMeaningDisabled(): static
    {
        return $this->state(fn (array $attributes) => ['show_meaning_enabled' => false]);
    }
}
