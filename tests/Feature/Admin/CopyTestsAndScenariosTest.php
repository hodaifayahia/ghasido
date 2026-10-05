<?php

namespace Tests\Feature\Admin;

use App\Enums\ContentStatus;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\RoleplayAttempt;
use App\Models\Test as Assessment;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\VoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Copy a Pre/Post-test or an AI scenario to start a similar one, and take a
 * published scenario back to draft while editing it (client request
 * 2026-10-05). A copy is a draft that owns its questions; learner data is
 * never copied and never touched (DATA-10, DATA-11, TEST-09).
 */
class CopyTestsAndScenariosTest extends TestCase
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

    public function test_a_test_is_copied_with_its_settings_and_its_own_questions()
    {
        $test = Assessment::factory()->post()->create([
            'title' => 'Reception Post-test',
            'department_id' => $this->department->id,
        ]);
        $first = Activity::factory()->create(['prompt' => 'Question one']);
        $second = Activity::factory()->create(['prompt' => 'Question two']);
        ActivityPlacement::factory()->in($test)->at(1)->create(['activity_id' => $first->id]);
        ActivityPlacement::factory()->in($test)->at(2)->create(['activity_id' => $second->id]);
        TestAttempt::factory()->create(['test_id' => $test->id]);

        $this->actingAs($this->owner)
            ->post(route('tests.duplicate', $test))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success');

        $copy = Assessment::query()->where('title', 'Reception Post-test (copy)')->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $copy->status);
        $this->assertSame($test->type, $copy->type);
        $this->assertSame($test->settings, $copy->settings);
        $this->assertSame(0, $copy->attempts()->count());

        $prompts = $copy->questions()->with('activity')->get()->map(fn (ActivityPlacement $placement): string => $placement->activity->prompt ?? '')->all();
        $this->assertSame(['Question one', 'Question two'], $prompts);
        $this->assertNotContains($first->id, $copy->questions()->pluck('activity_id')->all());
        $this->assertSame(2, $test->questions()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'test.duplicated')->count());
    }

    public function test_a_scenario_is_copied_with_its_settings_as_a_draft()
    {
        $scenario = AiScenario::factory()->published()->create([
            'title' => 'Checking In a Guest',
            'department_id' => $this->department->id,
            'situation' => 'A guest arrives late at night.',
            'attempts_allowed' => 5,
        ]);
        VoiceLine::factory()->create(['ai_scenario_id' => $scenario->id]);
        RoleplayAttempt::factory()->create(['ai_scenario_id' => $scenario->id]);

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.duplicate', $scenario))
            ->assertRedirect();

        $copy = AiScenario::query()->where('title', 'Checking In a Guest (copy)')->firstOrFail();

        $this->assertSame(ContentStatus::Draft, $copy->status);
        $this->assertSame('A guest arrives late at night.', $copy->situation);
        $this->assertSame(5, $copy->attempts_allowed);
        $this->assertNotSame($scenario->slug, $copy->slug);
        $this->assertSame(1, VoiceLine::query()->where('ai_scenario_id', $copy->id)->count());
        $this->assertSame(0, RoleplayAttempt::query()->where('ai_scenario_id', $copy->id)->count());
    }

    public function test_a_published_scenario_can_go_back_to_draft()
    {
        $scenario = AiScenario::factory()->published()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->owner)
            ->get(route('ai-scenarios', ['scenario' => $scenario->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.published', true)
                ->where('editor.unpublishUrl', route('ai-scenarios.unpublish', $scenario))
            );

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.unpublish', $scenario))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'info');

        $this->assertSame(ContentStatus::Draft, $scenario->fresh()?->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'scenario.unpublished')->count());

        $this->actingAs($this->owner)->post(route('ai-scenarios.publish', $scenario))->assertRedirect();
        $this->assertSame(ContentStatus::Published, $scenario->fresh()?->status);
    }

    public function test_a_user_without_the_manage_capabilities_cannot_copy_or_unpublish()
    {
        $test = Assessment::factory()->create(['department_id' => $this->department->id]);
        $scenario = AiScenario::factory()->published()->create(['department_id' => $this->department->id]);
        $employee = User::factory()->create();

        $this->actingAs($employee)->post(route('tests.duplicate', $test))->assertForbidden();
        $this->actingAs($employee)->post(route('ai-scenarios.duplicate', $scenario))->assertForbidden();
        $this->actingAs($employee)->post(route('ai-scenarios.unpublish', $scenario))->assertForbidden();

        $this->assertSame(1, Assessment::query()->count());
        $this->assertSame(1, AiScenario::query()->count());
        $this->assertSame(ContentStatus::Published, $scenario->fresh()?->status);
    }
}
