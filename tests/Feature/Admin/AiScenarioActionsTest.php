<?php

namespace Tests\Feature\Admin;

use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Models\AiScenario;
use App\Models\AiScenarioConfiguration;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\Ai\RoleplayPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI scenario builder writes real content rows and keeps Qwen output as
 * an explicit draft until the author applies and publishes it (RP-01, GEN-01,
 * GEN-03, GEN-04, DATA-10).
 */
class AiScenarioActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('services.ai.provider', 'fake');
        $this->owner = User::factory()->superAdmin()->create();
        $this->department = Department::factory()->create(['name' => 'Reception']);
    }

    public function test_create_generate_apply_and_publish_are_separate_authoring_steps(): void
    {
        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.store'), [
                'title' => 'Guest check-in',
                'department_id' => $this->department->id,
                'difficulty' => 'beginner',
            ])
            ->assertRedirect();

        $scenario = AiScenario::query()->firstOrFail();
        $this->assertSame(ContentStatus::Draft, $scenario->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'scenario.created')->count());

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                'title' => 'Guest check-in',
                'department_id' => $this->department->id,
                'difficulty' => 'beginner',
                'description' => 'A guest arrives at the desk.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.generate', $scenario))
            ->assertRedirect();

        $scenario->refresh();
        $this->assertSame(GenerationStatus::Done, $scenario->ai_status);
        $this->assertNotNull($scenario->ai_draft);
        $this->assertSame('A guest arrives at the desk.', $scenario->description);

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.apply-draft', $scenario))
            ->assertRedirect();

        $scenario->refresh();
        $this->assertNull($scenario->ai_draft);
        $this->assertNull($scenario->ai_status);
        $this->assertSame(ContentStatus::Draft, $scenario->status);
        $this->assertNotSame('', $scenario->description);

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.publish', $scenario))
            ->assertRedirect();

        $this->assertSame(ContentStatus::Published, $scenario->fresh()?->status);
        $this->assertSame(1, AuditLog::query()
            ->where('actor_id', $this->owner->id)
            ->where('action', 'scenario.published')
            ->count());
    }

    public function test_authoring_tabs_and_scenario_settings_persist_their_content(): void
    {
        $scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.config.categories'), [
                'items' => [[
                    'id' => 'front-desk',
                    'name' => 'Front Desk',
                    'description' => 'Reception conversations.',
                    'department' => 'Reception',
                    'scenarioCount' => 3,
                    'status' => 'published',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.config.instructions'), [
                'systemPrompt' => 'Stay in character as {{ai_role}}.',
                'tone' => 'encouraging',
                'strictness' => 'communicative',
                'guardrails' => [['id' => 'short', 'text' => 'Use short sentences.']],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.config.feedback'), [
                'sections' => ['What you did well'],
                'templates' => [[
                    'id' => 'standard',
                    'name' => 'Standard',
                    'description' => 'Everyday coaching.',
                    'tone' => 'Encouraging',
                    'status' => 'published',
                    'isDefault' => true,
                    'criteria' => [['label' => 'Fluency', 'weight' => 100]],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                'title' => $scenario->title,
                'department_id' => $this->department->id,
                'difficulty' => $scenario->difficulty->value,
                'description' => $scenario->description,
                'settings' => [
                    'attempts_allowed' => 5,
                    'feedback_style' => 'balanced',
                    'focus_areas' => [['label' => 'Fluency', 'checked' => true]],
                    'allow_hints' => false,
                    'show_suggestions' => true,
                    'tags' => ['front-desk'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $scenario->fresh()?->attempts_allowed);
        $this->assertSame('balanced', $scenario->fresh()?->settings['feedback_style']);
        $this->assertSame(
            'Stay in character as {{ai_role}}.',
            AiScenarioConfiguration::query()->where('key', 'instructions')->firstOrFail()->value['systemPrompt'],
        );
    }

    public function test_the_editor_saves_the_scene_the_roles_and_the_first_line()
    {
        // Client report 2026-10-02: the roles could not be written, so
        // every conversation fell back to the same check-in.
        $scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'settings' => ['feedback_style' => 'encouraging'],
        ]);

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                'title' => 'Broken air conditioning',
                'department_id' => $this->department->id,
                'difficulty' => 'intermediate',
                'description' => 'A night call about room 305.',
                'situation' => 'A guest calls reception at 11 pm: the air conditioning in room 305 is not working.',
                'ai_role' => 'A tired, impatient business guest who wants a fast solution.',
                'employee_role' => 'The night receptionist who calms the guest and offers a solution.',
                'objective' => 'Solve the problem and keep the guest happy.',
                'opening_line' => 'Hello? My air conditioning is not working!',
            ])
            ->assertSessionHasNoErrors();

        $scenario->refresh();
        $this->assertStringContainsString('room 305', $scenario->situation);
        $this->assertStringContainsString('impatient', $scenario->ai_role);
        $this->assertSame('Solve the problem and keep the guest happy.', $scenario->objective);
        $this->assertSame('Hello? My air conditioning is not working!', $scenario->settings['voice_agent']['greeting'] ?? null);
        // The feedback panel's settings are kept.
        $this->assertSame('encouraging', $scenario->settings['feedback_style'] ?? null);

        $brief = RoleplayPrompt::brief($scenario);
        $this->assertStringContainsString('Situation: A guest calls reception at 11 pm', $brief);
        $this->assertStringContainsString('impatient business guest', $brief);
    }

    public function test_the_description_stands_in_for_an_empty_situation()
    {
        $scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'situation' => '',
            'description' => 'A guest asks for a late checkout.',
        ]);

        $this->assertStringContainsString('Situation: A guest asks for a late checkout.', RoleplayPrompt::brief($scenario));
    }

    public function test_a_scenario_can_have_a_difficult_guest()
    {
        // Client request 2026-10-02: "the characters are all nice people".
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                'title' => 'Noisy room',
                'department_id' => $this->department->id,
                'difficulty' => 'advanced',
                'guest_mood' => 'angry',
            ])
            ->assertSessionHasNoErrors();

        $scenario->refresh();
        $this->assertSame('angry', $scenario->settings['guest_mood'] ?? null);

        $brief = RoleplayPrompt::brief($scenario);
        $this->assertStringContainsString('upset and angry', $brief);
        $this->assertStringContainsString('never insult', $brief);

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), ['title' => 'Noisy room', 'department_id' => $this->department->id, 'difficulty' => 'advanced', 'guest_mood' => 'furious'])
            ->assertSessionHasErrors('guest_mood');
    }
}
