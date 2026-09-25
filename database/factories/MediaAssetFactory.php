<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => MediaAsset::DISK_PUBLIC,
            'path' => 'content/images/2026/09/'.Str::uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime' => 'image/jpeg',
            'kind' => MediaKind::Image,
            'alt_text' => fake()->sentence(4),
            'width' => 1600,
            'height' => 1067,
            'duration_ms' => null,
            'size_bytes' => fake()->numberBetween(40_000, 900_000),
            'variants' => null,
            'uploaded_by' => null,
            'hotel_id' => null,
            'library' => MediaLibrary::GuesviaLibrary,
            'category' => null,
            'label' => null,
        ];
    }

    /** A mockup crop from spec 0003 Part F. */
    public function seed(string $name = 'situation-complaint'): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'content/seed/'.$name.'.jpg',
            'original_name' => $name.'.jpg',
            'library' => MediaLibrary::Seed,
            'label' => $name,
        ]);
    }

    /** Generated TTS output on the public disk. */
    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'content/audio/2026/09/'.Str::uuid().'.wav',
            'original_name' => null,
            'mime' => 'audio/wav',
            'kind' => MediaKind::Audio,
            'alt_text' => null,
            'width' => null,
            'height' => null,
            'duration_ms' => 600,
            'library' => MediaLibrary::Generated,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'content/video/2026/09/'.Str::uuid().'.mp4',
            'mime' => 'video/mp4',
            'kind' => MediaKind::Video,
            'alt_text' => null,
            'width' => 1280,
            'height' => 720,
            'duration_ms' => 42_000,
        ]);
    }

    /** A learner's voice recording: private disk, served through media.show. */
    public function recording(): static
    {
        return $this->state(fn (array $attributes) => [
            'disk' => MediaAsset::DISK_LOCAL,
            'path' => 'recordings/2026/09/'.Str::uuid().'.webm',
            'original_name' => null,
            'mime' => 'audio/webm',
            'kind' => MediaKind::Audio,
            'alt_text' => null,
            'width' => null,
            'height' => null,
            'duration_ms' => 5200,
            'library' => MediaLibrary::Recordings,
        ]);
    }

    public function forHotel(int $hotelId): static
    {
        return $this->state(fn (array $attributes) => ['hotel_id' => $hotelId]);
    }

    public function uploadedBy(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $userId,
            'library' => MediaLibrary::MyImages,
        ]);
    }
}
