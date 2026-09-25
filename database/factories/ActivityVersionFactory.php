<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\ActivityVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rarely needed directly: Activity writes its own versions on save. This
 * exists for tests that want a detached snapshot to score.
 *
 * @extends Factory<ActivityVersion>
 */
class ActivityVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            // Version 1 is written by the activity itself on create, so a
            // factory made row is the next one.
            'version' => 2,
            'payload' => ActivityFactory::payloadFor(ActivityType::MultipleChoice),
            'scoring' => null,
        ];
    }

    public function ofType(ActivityType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_id' => Activity::factory()->ofType($type),
            'payload' => ActivityFactory::payloadFor($type),
        ]);
    }
}
