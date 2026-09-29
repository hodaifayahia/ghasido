<?php

namespace Tests\Feature\Admin\Tests;

use App\Enums\ActivityType;
use App\Enums\ContentStatus;
use App\Enums\TestType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\Department;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The admin assessment builder is backed by the learner's real content rows
 * (TEST-01..10, TSTM-01..05, DATA-11).
 */
class TestsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_library_reads_tests_from_the_database(): void
    {
        $department = Department::factory()->create(['name' => 'Reception']);
        $test = Test::factory()->pre()->create([
            'department_id' => $department->id,
            'title' => 'Reception baseline',
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('tests'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Tests')
                ->where('builderOpen', false)
                ->where('list.items.0.id', (string) $test->id)
                ->where('list.items.0.title', 'Reception baseline')
                ->where('stats.0.value', 1)
            );
    }

    public function test_an_admin_can_create_and_open_a_draft_test(): void
    {
        $department = Department::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('tests.store'), [
                'title' => 'Housekeeping baseline',
                'type' => TestType::Pre->value,
                'department_id' => $department->id,
                'time_limit_minutes' => 25,
            ])
            ->assertRedirect();

        $test = Test::query()->where('title', 'Housekeeping baseline')->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $test->status);
        $this->assertSame(1500, $test->settings['time_limit_seconds']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.created',
            'auditable_type' => $test->getMorphClass(),
            'auditable_id' => $test->id,
        ]);

        $this->actingAs($admin)
            ->get(route('tests', ['test' => $test]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('builderOpen', true)
                ->where('editor.id', $test->id)
                ->where('editor.title', 'Housekeeping baseline')
            );
    }

    public function test_questions_are_saved_as_versioned_activities_and_can_be_published(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->draft()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('tests.questions.store', $test), [
                'kind' => 'multiple_choice',
                'text' => 'How do you welcome the guest?',
                'options' => [
                    ['id' => 'A', 'text' => 'Good morning, welcome.', 'correct' => true],
                    ['id' => 'B', 'text' => 'What do you want?', 'correct' => false],
                ],
            ])
            ->assertRedirect();

        $placement = ActivityPlacement::query()->where('placeable_id', $test->id)->firstOrFail();
        $activity = $placement->activity()->firstOrFail();

        $this->assertSame(1, $activity->current_version);
        $this->assertDatabaseHas('activity_versions', [
            'activity_id' => $activity->id,
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('tests.questions.update', [$test, $placement]), [
                'kind' => 'multiple_choice',
                'text' => 'How do you greet the guest?',
                'options' => [
                    ['id' => 'A', 'text' => 'Good afternoon, welcome.', 'correct' => true],
                    ['id' => 'B', 'text' => 'Please wait outside.', 'correct' => false],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $activity->refresh()->current_version);
        $this->assertDatabaseHas('activity_versions', [
            'activity_id' => $activity->id,
            'version' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('tests.publish', $test))
            ->assertRedirect();

        $this->assertSame(ContentStatus::Published, $test->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.published']);
    }

    public function test_users_without_test_management_cannot_open_or_write_the_builder(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('tests'))
            ->assertForbidden();
    }

    public function test_question_bank_results_and_settings_tabs_are_data_backed(): void
    {
        $department = Department::factory()->create(['name' => 'Reception']);
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();
        $activity = Activity::factory()->create(['department_id' => $department->id]);
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        $this->actingAs($admin)
            ->get(route('tests', ['tab' => 'question-bank']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'question-bank')
                ->where('questionBank.0.id', $activity->id));

        $this->actingAs($admin)
            ->get(route('tests', ['tab' => 'settings', 'test' => $test]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'settings')
                ->where('editor.id', $test->id)
                ->where('settings.toggles.0.key', 'shuffle_questions'));
    }

    public function test_each_result_row_shows_only_its_own_ai_judged_answers(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();
        $writing = Activity::factory()->ofType(ActivityType::Writing)->create(['department_id' => $department->id]);
        $choice = Activity::factory()->create(['department_id' => $department->id]);

        $earlier = TestAttempt::factory()->submitted()->create(['test_id' => $test->id, 'submitted_at' => now()->subHour()]);
        $later = TestAttempt::factory()->submitted()->create(['test_id' => $test->id]);
        Attempt::factory()->inTest($earlier)->create(['activity_id' => $writing->id, 'raw_answer' => ['i1' => ['text' => 'Welcome to our hotel.']]]);
        Attempt::factory()->inTest($earlier)->create(['activity_id' => $choice->id]);
        Attempt::factory()->inTest($later)->create(['activity_id' => $writing->id, 'raw_answer' => ['i1' => ['text' => 'Your room is ready.']]]);

        $this->actingAs($admin)
            ->get(route('tests', ['tab' => 'results']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.rows', 2)
                ->where('results.rows.0.id', $later->id)
                ->has('results.rows.0.answers', 1)
                ->where('results.rows.0.answers.0.answerText', 'Your room is ready.')
                ->where('results.rows.1.id', $earlier->id)
                // The multiple-choice answer is not AI-judged, so it is left out.
                ->has('results.rows.1.answers', 1)
                ->where('results.rows.1.answers.0.answerText', 'Welcome to our hotel.'));
    }

    public function test_question_media_is_attached_as_a_new_activity_version(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->draft()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();
        $activity = Activity::factory()->create(['department_id' => $department->id]);
        $placement = $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);
        $media = MediaAsset::factory()->seed('practice-guest-asking')->create();

        $this->actingAs($admin)
            ->patch(route('tests.questions.media', [$test, $placement]), [
                'kind' => 'image',
                'media_id' => $media->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $activity->refresh();
        $this->assertSame($media->id, $activity->items()[0]['media']['image']);
        $this->assertSame($media->id, $activity->items()[0]['image']);
        $this->assertSame(2, $activity->current_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.question.media.updated']);
    }
}
