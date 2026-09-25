<?php

namespace Database\Factories;

use App\Enums\ContentGenerationType;
use App\Enums\GenerationStatus;
use App\Models\ContentGeneration;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentGeneration>
 */
class ContentGenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->superAdmin(),
            'type' => ContentGenerationType::Lesson,
            'prompt' => 'Welcoming a guest at check-in',
            'department_id' => Department::factory(),
            'hotel_id' => null,
            'level' => 'beginner',
            'options' => ['images' => false, 'audio' => false, 'lesson_count' => 1],
            'status' => GenerationStatus::Pending,
            'lessons_total' => 1,
        ];
    }

    public function course(int $lessons = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ContentGenerationType::Course,
            'options' => ['images' => false, 'audio' => false, 'lesson_count' => $lessons],
            'lessons_total' => $lessons,
        ]);
    }

    public function failed(string $reason = 'provider down'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GenerationStatus::Failed,
            'failed_reason' => $reason,
        ]);
    }
}
