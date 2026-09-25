<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonCompletion>
 */
class LessonCompletionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'lesson_id' => Lesson::factory(),
            'completed_at' => now(),
        ];
    }
}
