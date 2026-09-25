<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockCompletion>
 */
class BlockCompletionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'lesson_id' => Lesson::factory(),
            // The block belongs to the same lesson the completion names.
            'block_id' => fn (array $attributes): int => Block::factory()
                ->create(['lesson_id' => $attributes['lesson_id']])
                ->getKey(),
            'completed_at' => now(),
        ];
    }
}
