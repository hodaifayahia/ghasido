<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * The default block set of a lesson, in order (AGENTS.md §1, lesson step
     * model). A seed template, never a hard coded pipeline: the admin may
     * change it per lesson (LESSON-02, BLD-07).
     *
     * @var list<BlockType>
     */
    public const array DEFAULT_BLOCKS = [
        BlockType::Situation,
        BlockType::Vocabulary,
        BlockType::Expressions,
        BlockType::ListenRepeat,
        BlockType::Dialogue,
        BlockType::Video,
        BlockType::Practice,
        BlockType::AiRoleplay,
        BlockType::Complete,
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(3), '.'));

        return [
            // course_id and hotel_id are derived from the unit on save.
            'unit_id' => Unit::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'introduction' => fake()->sentence(12),
            'objectives' => [
                'Understand the situation',
                'Learn useful language',
                'Practice and be ready for real situations',
            ],
            'cover_media_id' => null,
            'estimated_minutes' => fake()->numberBetween(8, 20),
            'completion_condition' => null,
            'position' => 0,
            'status' => ContentStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function at(int $position): static
    {
        return $this->state(fn (array $attributes) => ['position' => $position]);
    }

    /**
     * Give the lesson its blocks, in order: the nine default steps unless a
     * list is passed.
     *
     * @param  list<BlockType>|null  $types
     */
    public function withBlocks(?array $types = null): static
    {
        return $this->afterCreating(function (Lesson $lesson) use ($types): void {
            foreach ($types ?? self::DEFAULT_BLOCKS as $position => $type) {
                Block::factory()
                    ->ofType($type)
                    ->create(['lesson_id' => $lesson->id, 'position' => $position + 1]);
            }
        });
    }
}
