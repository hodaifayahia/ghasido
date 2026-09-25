<?php

namespace Database\Factories;

use App\Models\LexiconItem;
use App\Models\PhrasebookItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhrasebookItem>
 */
class PhrasebookItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'lexicon_item_id' => LexiconItem::factory(),
            'custom_text' => null,
            'custom_arabic' => null,
            'custom_hash' => null,
            'source_lesson_id' => null,
            'saved_at' => now(),
        ];
    }

    /**
     * A free phrase saved from a dialogue line rather than a lexicon item.
     */
    public function custom(string $text = 'How can I help you?', ?string $arabic = 'كيف يمكنني مساعدتك؟'): static
    {
        return $this->state(fn (array $attributes) => [
            'lexicon_item_id' => null,
            'custom_text' => $text,
            'custom_arabic' => $arabic,
            'custom_hash' => PhrasebookItem::hashFor($text),
        ]);
    }
}
