<?php

namespace Database\Factories;

use App\Enums\Accent;
use App\Enums\GenerationStatus;
use App\Models\PronunciationAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PronunciationAttempt>
 */
class PronunciationAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'reference_text' => 'How can I help you?',
            'accent' => Accent::American,
            'attempt_no' => 1,
            'status' => GenerationStatus::Pending,
        ];
    }
}
