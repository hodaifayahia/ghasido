<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Block;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<ActivityPlacement>
 */
class ActivityPlacementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'placeable_type' => (new Block)->getMorphClass(),
            'placeable_id' => Block::factory()->ofType(BlockType::Practice),
            'position' => 1,
            'overrides' => null,
        ];
    }

    /** Place the activity in one block or test. */
    public function in(Model $placeable): static
    {
        return $this->state(fn (array $attributes) => [
            'placeable_type' => $placeable->getMorphClass(),
            'placeable_id' => $placeable->getKey(),
        ]);
    }

    public function at(int $position): static
    {
        return $this->state(fn (array $attributes) => ['position' => $position]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function overriding(array $overrides): static
    {
        return $this->state(fn (array $attributes) => ['overrides' => $overrides]);
    }
}
