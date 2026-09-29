<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\AiScenario;
use App\Models\Department;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The lesson's "AI Role-play" tab picks existing scenarios straight into
 * the lesson (RP-01), adding the role-play step when the lesson has none;
 * the preview shows what was picked, drafts included.
 */
class LessonScenariosTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_the_tab_assigns_existing_scenarios_to_the_lessons_roleplay_step()
    {
        $scenarios = AiScenario::factory()->count(2)->create(['department_id' => $this->department->id]);
        $ids = [$scenarios[1]->id, $scenarios[0]->id];

        $this->actingAs($this->owner)
            ->from(route('lessons.edit', $this->lesson))
            ->put(route('lessons.scenarios', $this->lesson), ['scenario_ids' => $ids])
            ->assertRedirect(route('lessons.edit', $this->lesson))
            ->assertSessionHasNoErrors();

        $block = $this->lesson->blocks()->where('type', BlockType::AiRoleplay->value)->firstOrFail();

        $this->assertSame($ids, $block->scenarioIds());
        $this->assertSame($ids, $block->scenarios()->pluck('ai_scenarios.id')->all());

        // Clearing keeps the step, empty.
        $this->actingAs($this->owner)
            ->put(route('lessons.scenarios', $this->lesson), ['scenario_ids' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $block->fresh()?->scenarioIds());
        $this->assertSame(1, $this->lesson->blocks()->where('type', BlockType::AiRoleplay->value)->count());
    }

    public function test_a_lesson_without_a_roleplay_step_gets_one_before_the_closing_step()
    {
        $lesson = Lesson::factory()->create(['unit_id' => $this->unit->id, 'title' => 'Generated lesson', 'position' => 2]);
        $lesson->blocks()->create(['type' => BlockType::Situation->value, 'position' => 1, 'settings' => [], 'is_visible' => true, 'layout' => 'full']);
        $lesson->blocks()->create(['type' => BlockType::Complete->value, 'position' => 2, 'settings' => [], 'is_visible' => true, 'layout' => 'full']);
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->owner)
            ->put(route('lessons.scenarios', $lesson), ['scenario_ids' => [$scenario->id]])
            ->assertSessionHasNoErrors();

        $types = $lesson->blocks()->orderBy('position')->pluck('type')->map(fn (BlockType $type): string => $type->value)->all();

        $this->assertSame(['situation', 'ai_roleplay', 'complete'], $types);
        $this->assertSame([$scenario->id], $lesson->blocks()->where('type', 'ai_roleplay')->firstOrFail()->scenarioIds());
    }

    public function test_a_scenario_from_another_department_is_refused()
    {
        $foreign = AiScenario::factory()->create(['department_id' => Department::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->from(route('lessons.edit', $this->lesson))
            ->put(route('lessons.scenarios', $this->lesson), ['scenario_ids' => [$foreign->id]])
            ->assertSessionHasErrors('scenario_ids');
    }

    public function test_a_manager_cannot_change_a_shared_lessons_scenarios()
    {
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->manager())
            ->put(route('lessons.scenarios', $this->lesson), ['scenario_ids' => [$scenario->id]])
            ->assertForbidden();
    }

    public function test_the_picker_lists_the_open_lessons_department_even_when_the_url_names_another()
    {
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id, 'title' => 'Late check-out']);
        $other = Department::factory()->create(['hotel_id' => null, 'position' => 0]);
        AiScenario::factory()->create(['department_id' => $other->id, 'title' => 'Towel request']);

        $this->actingAs($this->owner)
            ->get(route('lessons.edit', $this->lesson))
            ->assertInertia(fn (Assert $page) => $page
                ->where('scenarios', fn ($rows): bool => collect($rows)->pluck('id')->all() === [$scenario->id])
            );
    }

    public function test_the_admin_preview_shows_the_linked_scenarios_drafts_included()
    {
        $draft = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Draft,
            'title' => 'Noisy room complaint',
        ]);
        $block = $this->lesson->blocks()->where('type', BlockType::AiRoleplay->value)->firstOrFail();

        $this->actingAs($this->owner)
            ->put(route('lessons.scenarios', $this->lesson), ['scenario_ids' => [$draft->id]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('lessons.preview', ['lesson' => $this->lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.scenarios.0.title', 'Noisy room complaint')
                ->where('block.scenarios.0.url', route('ai-scenarios', ['scenario' => $draft->id]))
            );
    }
}
