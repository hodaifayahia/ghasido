<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\User;
use Database\Factories\ActivityFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Activities are versioned content: an edit bumps the version and old
 * attempts keep pointing at the version they answered (PRAC-01..07,
 * DATA-11, TEST-09).
 */
class ActivityVersionTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_creating_an_activity_places_it_in_the_practice_block_at_version_one()
    {
        $practice = $this->lesson->blocks()->where('type', BlockType::Practice->value)->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('activities.store'), [
                'type' => 'listen_choose',
                'skill_label' => 'Vocabulary',
                'prompt' => 'Listen and choose the picture.',
                'payload' => ActivityFactory::payloadFor(ActivityType::ListenChoose),
                'attempts_allowed' => 3,
                'block_id' => $practice->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $activity = Activity::query()->where('prompt', 'Listen and choose the picture.')->firstOrFail();

        $this->assertSame(1, $activity->current_version);
        $this->assertSame(1, $activity->versions()->count());
        $this->assertSame(3, $activity->attempts_allowed);
        $this->assertSame([$activity->id], $practice->placements()->pluck('activity_id')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'activity.created')->count());
    }

    public function test_a_payload_without_items_is_rejected()
    {
        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->post(route('activities.store'), ['type' => 'writing', 'prompt' => 'Write.', 'payload' => ['items' => []]])
            ->assertSessionHasErrors(['payload.items']);
    }

    public function test_editing_the_payload_bumps_the_version_and_keeps_old_attempts_on_the_old_version()
    {
        $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
        $first = $activity->currentVersion()->firstOrFail();
        $learner = User::factory()->employee()->create();
        $attempt = Attempt::factory()->create([
            'user_id' => $learner->id,
            'activity_id' => $activity->id,
            'activity_version_id' => $first->id,
        ]);

        $payload = $activity->payload;
        $payload['items'][0]['correct'] = 'B';

        $this->actingAs($this->owner)
            ->patch(route('activities.update', $activity), ['payload' => $payload, 'prompt' => 'Updated prompt'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $activity->refresh();

        $this->assertSame(2, $activity->current_version);
        $this->assertSame(2, $activity->versions()->count());
        $this->assertSame('B', $activity->currentVersion()->firstOrFail()->items()[0]['correct']);
        $this->assertSame('A', $first->fresh()?->items()[0]['correct']);
        $this->assertSame($first->id, $attempt->fresh()?->activity_version_id);

        // A prompt-only edit is not a new version.
        $this->actingAs($this->owner)->patch(route('activities.update', $activity), ['prompt' => 'Again']);

        $this->assertSame(2, $activity->fresh()?->current_version);
    }
}
