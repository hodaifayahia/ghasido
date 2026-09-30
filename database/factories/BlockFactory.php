<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Lesson;
use App\Services\Content\BlockDefaults;
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
     * The production defaults (App\Services\Content\BlockDefaults) with a
     * sample body on the generic types, so factory rows have text to render.
     *
     * @return array<string, mixed>
     */
    public static function settingsFor(BlockType $type): array
    {
        $settings = BlockDefaults::settings($type);

        if (array_key_exists('body', $settings)) {
            $settings['body'] = fake()->sentence(10);
        }

        return $settings;
    }
}
