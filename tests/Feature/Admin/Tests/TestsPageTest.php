<?php

namespace Tests\Feature\Admin\Tests;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\TestType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Department;
use App\Models\Hotel;
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

    /**
     * The Question Bank / Results & Analytics / Settings tabs were removed
     * from the page (client request 2026-09-29): their props are gone, and
     * the builder still carries what it needs to save a test.
     */
    public function test_the_page_no_longer_ships_the_removed_tabs(): void
    {
        $department = Department::factory()->create(['name' => 'Reception']);
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();
        $activity = Activity::factory()->create(['department_id' => $department->id]);
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        $this->actingAs($admin)
            ->get(route('tests', ['tab' => 'question-bank', 'test' => $test]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Tests')
                ->missing('tabs')
                ->missing('activeTab')
                ->missing('questionBank')
                ->missing('settings')
                ->missing('results.rows')
                ->has('results.stats', 2)
                ->where('builderOpen', true)
                ->where('editor.id', $test->id)
                ->where('editor.type', TestType::Pre->value)
                ->where('editor.department', (string) $department->id)
                ->where('editor.attemptCount', 0)
                ->has('editor.settings.show_meaning')
            );
    }

    public function test_an_admin_can_delete_a_test_nobody_has_taken(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->draft()->create(['department_id' => $department->id, 'title' => 'Old draft']);
        $post = Test::factory()->create(['department_id' => $department->id, 'type' => TestType::Post, 'paired_test_id' => $test->id]);
        $activity = Activity::factory()->create(['department_id' => $department->id]);
        $placement = $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('tests'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('list.items.0.id', (string) $test->id)
                ->where('list.items.0.attemptCount', 0));

        $this->actingAs($admin)
            ->delete(route('tests.destroy', $test))
            ->assertRedirect(route('tests'))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($test);
        $this->assertModelMissing($placement);
        // The versioned question stays: a lesson may place it too (DATA-11).
        $this->assertModelExists($activity);
        // A paired Post-test is unpaired, never deleted with it.
        $this->assertNull($post->refresh()->paired_test_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.deleted',
            'actor_id' => $admin->id,
            'auditable_type' => $test->getMorphClass(),
            'auditable_id' => $test->id,
        ]);
    }

    /**
     * A test someone has sat is refused, not cascaded: its attempts hold
     * learners' answers (DATA-10).
     */
    public function test_a_test_with_attempts_is_never_deleted(): void
    {
        $test = Test::factory()->pre()->create();
        $sitting = TestAttempt::factory()->submitted()->create(['test_id' => $test->id]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('tests', ['test' => $test]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('list.items.0.attemptCount', 1)
                ->where('editor.attemptCount', 1));

        $this->actingAs($admin)
            ->from(route('tests'))
            ->delete(route('tests.destroy', $test))
            ->assertRedirect(route('tests'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($test);
        $this->assertModelExists($sitting);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'test.deleted']);
    }

    public function test_deleting_a_test_needs_tests_manage_and_the_same_hotel(): void
    {
        $test = Test::factory()->pre()->create();
        $employee = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($employee)
            ->delete(route('tests.destroy', $test))
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('tests.destroy', $test))
            ->assertForbidden();

        // Holding tests.manage is not enough for another hotel's test (ROLE-02).
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $foreign = Test::factory()->pre()->create(['hotel_id' => $hotelB->id]);
        $author = User::factory()->manager()->create(['hotel_id' => $hotelA->id]);
        $author->givePermissionTo(Permission::TestsView->value, Permission::TestsManage->value);

        $this->actingAs($author)
            ->delete(route('tests.destroy', $foreign))
            ->assertForbidden();

        $this->assertModelExists($test);
        $this->assertModelExists($foreign);

        // ...while the same author may delete their own hotel's test.
        $own = Test::factory()->pre()->create(['hotel_id' => $hotelA->id]);

        $this->actingAs($author)
            ->delete(route('tests.destroy', $own))
            ->assertRedirect(route('tests'));

        $this->assertModelMissing($own);
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
