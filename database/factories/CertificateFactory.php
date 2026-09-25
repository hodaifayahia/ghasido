<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'course_id' => Course::factory(),
            'type' => CertificateType::Completion,
            'verification_id' => (string) Str::uuid(),
            'issued_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function participation(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CertificateType::Participation]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now()]);
    }
}
