<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ScenarioDifficulty;
use App\Models\AiScenario;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiScenario>
 */
class AiScenarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(2), '.'));

        return [
            'department_id' => Department::factory(),
            'hotel_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(6),
            'difficulty' => ScenarioDifficulty::Beginner,
            'situation' => 'A guest arrives at the reception desk with a reservation.',
            'ai_role' => 'A hotel guest arriving for check-in.',
            'employee_role' => 'The receptionist welcoming the guest.',
            'objective' => 'Welcome the guest, confirm the reservation and explain breakfast.',
            'goals' => [
                'Greet the guest politely',
                "Ask for the guest's name",
                'Confirm the reservation',
                'Give the room information',
                'Explain the breakfast time',
            ],
            'useful_phrases' => [
                'Good afternoon, welcome to our hotel.',
                'May I have your name, please?',
                'Your room is on the fifth floor.',
            ],
            'feedback_criteria' => AiScenario::defaultFeedbackCriteria(),
            'attempts_allowed' => 3,
            'input_mode' => 'both',
            'min_turns' => 4,
            'max_turns' => 12,
            'thumbnail_media_id' => null,
            'icon' => 'bell',
            'quote' => '“Good communication creates great experiences.”',
            'tip' => 'Speak clearly and keep your sentences short.',
            'status' => ContentStatus::Draft,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContentStatus::Published]);
    }

    public function forHotel(int $hotelId): static
    {
        return $this->state(fn (array $attributes) => ['hotel_id' => $hotelId]);
    }

    public function forDepartment(int $departmentId): static
    {
        return $this->state(fn (array $attributes) => ['department_id' => $departmentId]);
    }

    public function difficulty(ScenarioDifficulty $difficulty): static
    {
        return $this->state(fn (array $attributes) => ['difficulty' => $difficulty]);
    }

    public function textOnly(): static
    {
        return $this->state(fn (array $attributes) => ['input_mode' => 'text']);
    }
}
