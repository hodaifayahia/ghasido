<?php

namespace Database\Factories;

use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use App\Models\AudioClip;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioClip>
 */
class AudioClipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->unique()->sentence(5);

        return [
            'text' => $text,
            'text_hash' => AudioClip::hashFor($text),
            'voice' => 'guesvia-en',
            'speed' => AudioSpeed::Normal,
            'media_asset_id' => null,
            'status' => GenerationStatus::Pending,
            'failed_reason' => null,
            'provider' => null,
            'generated_at' => null,
        ];
    }

    public function slow(): static
    {
        return $this->state(fn (array $attributes) => ['speed' => AudioSpeed::Slow]);
    }

    /** Generated: the file exists and the speaker button plays it. */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_asset_id' => MediaAsset::factory()->audio(),
            'status' => GenerationStatus::Done,
            'provider' => 'fake',
            'generated_at' => now(),
        ]);
    }

    public function failed(string $reason = 'Provider returned 500.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GenerationStatus::Failed,
            'failed_reason' => $reason,
        ]);
    }
}
