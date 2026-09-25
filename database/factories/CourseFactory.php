<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(3), '.'));

        return [
            'department_id' => Department::factory(),
            // Null means shared across every hotel (ORG-04).
            'hotel_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'tone' => 'brand',
            'position' => 0,
            'status' => ContentStatus::Draft,
            'published_at' => null,
            'cover_media_id' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }

    /** A course only this one hotel has. */
    public function forHotel(int $hotelId): static
    {
        return $this->state(fn (array $attributes) => ['hotel_id' => $hotelId]);
    }

    public function forDepartment(int $departmentId): static
    {
        return $this->state(fn (array $attributes) => ['department_id' => $departmentId]);
    }

    public function tone(string $tone): static
    {
        return $this->state(fn (array $attributes) => ['tone' => $tone]);
    }
}
