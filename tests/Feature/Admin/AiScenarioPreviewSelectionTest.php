<?php

namespace Tests\Feature\Admin;

use App\Models\AiScenario;
use App\Models\Department;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Test Scenario" opens Preview & Test on the scenario just created or
 * edited, not on the first one of the library (client report 2026-09-30).
 */
class AiScenarioPreviewSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_preview_starts_on_the_scenario_being_edited(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $department = Department::factory()->create(['hotel_id' => null, 'is_active' => true]);

        foreach (['Ask for Information', 'Zz Late checkout'] as $title) {
            $this->actingAs($admin)->post(route('ai-scenarios.store'), [
                'title' => $title,
                'department_id' => $department->id,
                'difficulty' => 'beginner',
            ])->assertSessionHasNoErrors();
        }

        $created = AiScenario::query()->where('title', 'Zz Late checkout')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('ai-scenarios', ['tab' => 'preview', 'scenario' => $created->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'preview')
                ->has('previewTest.scenarios', 2)
                ->where('previewTest.selected', (string) $created->id));

        // Without a scenario the first one is chosen, as before.
        $this->actingAs($admin)
            ->get(route('ai-scenarios', ['tab' => 'preview']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('previewTest.selected', fn ($id) => $id !== (string) $created->id));
    }

    public function test_a_test_started_in_the_editor_stays_in_the_editor(): void
    {
        $this->withoutVite();
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();
        $department = Department::factory()->create(['hotel_id' => null, 'is_active' => true]);
        $this->actingAs($admin)->post(route('ai-scenarios.store'), [
            'title' => 'Late checkout',
            'department_id' => $department->id,
            'difficulty' => 'beginner',
        ]);
        $scenario = AiScenario::query()->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('ai-scenarios.preview.store'), ['scenario' => $scenario->id, 'editor' => 1]);
        $attempt = RoleplayAttempt::query()->firstOrFail();
        $response->assertRedirect(route('ai-scenarios', ['tab' => 'scenarios', 'scenario' => $scenario->id, 'section' => 'test', 'preview' => $attempt->id]));

        $this->actingAs($admin)
            ->get(route('ai-scenarios', ['tab' => 'scenarios', 'scenario' => $scenario->id, 'section' => 'test', 'preview' => $attempt->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('builderOpen', true)
                ->where('editorSection', 'test')
                ->where('previewTest.attempt.id', $attempt->id));

        // From the Preview & Test page it goes back there, as before.
        $this->actingAs($admin)
            ->post(route('ai-scenarios.preview.store'), ['scenario' => $scenario->id])
            ->assertRedirect(route('ai-scenarios', ['tab' => 'preview', 'preview' => RoleplayAttempt::query()->latest('id')->value('id')]));
    }
}
