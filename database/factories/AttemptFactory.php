<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityVersion;
use App\Models\Attempt;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            // Activity::booted() defaults current_version to 1 on create and
            // writes the version 1 row itself (spec 0003 B.5).
            'activity_id' => Activity::factory(),
            // The version the answer refers to (TEST-09). Reuses the latest
            // version the activity already has, since creating an activity
            // writes version 1 (spec 0003 B.5); otherwise writes one.
            'activity_version_id' => function (array $attributes): int {
                $activityId = (int) $attributes['activity_id'];

                $version = ActivityVersion::query()
                    ->where('activity_id', $activityId)
                    ->orderByDesc('version')
                    ->first();

                if ($version !== null) {
                    return $version->getKey();
                }

                return ActivityVersion::factory()
                    ->create(['activity_id' => $activityId, 'version' => 1])
                    ->getKey();
            },
            'placement_id' => null,
            'test_attempt_id' => null,
            'lesson_id' => null,
            'block_id' => null,
            'attempt_no' => 1,
            'raw_answer' => ['i1' => 'a'],
            'answer_history' => null,
            'response_media_id' => null,
            'transcript' => null,
            'is_correct' => true,
            'score' => 1,
            'max_score' => 1,
            'ai_feedback' => null,
            'ai_status' => null,
            'started_at' => now()->subSeconds(12),
            'submitted_at' => now(),
            'time_taken_ms' => 12000,
        ];
    }

    /** An answer given inside a test sitting rather than lesson practice. */
    public function inTest(?TestAttempt $testAttempt = null): static
    {
        return $this->state(fn (array $attributes) => $testAttempt === null
            ? ['test_attempt_id' => TestAttempt::factory()]
            : ['test_attempt_id' => $testAttempt->id, 'user_id' => $testAttempt->user_id]);
    }

    public function wrong(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => false,
            'score' => 0,
        ]);
    }

    /** Not yet answered: served but no submission. */
    public function unanswered(): static
    {
        return $this->state(fn (array $attributes) => [
            'raw_answer' => null,
            'is_correct' => null,
            'score' => null,
            'max_score' => null,
            'submitted_at' => null,
            'time_taken_ms' => null,
        ]);
    }
}
