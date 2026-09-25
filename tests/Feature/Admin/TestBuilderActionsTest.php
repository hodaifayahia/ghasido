<?php

namespace Tests\Feature\Admin;

use App\Enums\ContentStatus;
use App\Models\ActivityPlacement;
use App\Models\Department;
use App\Models\Test as Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Pre/Post-test screen is backed by the same versioned activities the
 * learner runner reads (TEST-01..10, TEST-09, TSTM-01..05, DATA-11).
 */
class TestBuilderActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $this->department = Department::factory()->create(['name' => 'Reception']);
    }

    public function test_create_edit_question_version_and_publish_flow_is_persisted(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tests.store'), [
                'title' => 'Reception Pre-test',
                'type' => 'pre',
                'department_id' => $this->department->id,
                'time_limit_minutes' => 20,
            ])
            ->assertRedirect();

        $test = Assessment::query()->firstOrFail();
        $this->assertSame(ContentStatus::Draft, $test->status);
        $this->assertSame(1200, $test->timeLimitSeconds());

        $this->actingAs($this->owner)
            ->patch(route('tests.update', $test), [
                'title' => 'Reception Pre-test Updated',
                'type' => 'pre',
                'department_id' => $this->department->id,
                'description' => 'A short baseline assessment.',
                'time_limit_minutes' => 15,
                'question_count' => 1,
                'results_visibility' => 'hidden',
                'shuffle_questions' => false,
                'shuffle_options' => false,
                'single_attempt' => true,
                'show_answers' => false,
                'motivational_message' => true,
                'pass_mark' => 70,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $test), [
                'kind' => 'multiple_choice',
                'text' => 'How do you welcome a guest?',
                'options' => [
                    ['id' => 'A', 'text' => 'Good afternoon. Welcome.', 'correct' => true],
                    ['id' => 'B', 'text' => 'Wait there.', 'correct' => false],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $placement = $test->questions()->firstOrFail();
        $activity = $placement->activity()->firstOrFail();
        $this->assertSame(1, $activity->current_version);

        $this->actingAs($this->owner)
            ->patch(route('tests.questions.update', [$test, $placement]), [
                'kind' => 'multiple_choice',
                'text' => 'How do you welcome the guest?',
                'options' => [
                    ['id' => 'A', 'text' => 'Good afternoon. Welcome.', 'correct' => true],
                    ['id' => 'B', 'text' => 'Wait there.', 'correct' => false],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $activity->fresh()?->current_version);

        $this->actingAs($this->owner)
            ->delete(route('tests.questions.destroy', [$test, $placement]))
            ->assertRedirect();

        $this->assertDatabaseMissing('activity_placements', ['id' => $placement->id]);

        $this->actingAs($this->owner)
            ->post(route('tests.publish', $test))
            ->assertRedirect();

        $this->assertSame(ContentStatus::Published, $test->fresh()?->status);
        $this->assertCount(0, ActivityPlacement::query()->where('placeable_id', $test->id)->get());
    }
}
