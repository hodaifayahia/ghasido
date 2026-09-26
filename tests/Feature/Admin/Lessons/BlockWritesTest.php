<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Department;
use App\Models\LexiconItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Add, edit, reorder, toggle, duplicate and delete blocks (BLD-02, BLD-03,
 * BLD-07, LESSON-02; spec 0003 B.10), each with its audit row (SEC-06).
 */
class BlockWritesTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_adding_a_block_from_the_palette_inserts_it_before_the_closing_step()
    {
        $this->actingAs($this->owner)
            ->post(route('blocks.store', $this->lesson), ['type' => 'note'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $types = $this->lesson->blocks()->pluck('type')->map(fn (BlockType $type): string => $type->value)->all();

        $this->assertSame(10, count($types));
        $this->assertSame('note', $types[8]);
        $this->assertSame('complete', $types[9]);
        $this->assertSame(range(1, 10), $this->lesson->blocks()->pluck('position')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'block.created')->count());
    }

    public function test_an_unknown_block_type_is_rejected()
    {
        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->post(route('blocks.store', $this->lesson), ['type' => 'download'])
            ->assertSessionHasErrors('type');
    }

    public function test_reorder_persists_the_new_order_and_refuses_a_foreign_block()
    {
        $ids = $this->lesson->blocks()->pluck('id')->all();
        $order = array_merge([$ids[1], $ids[0]], array_slice($ids, 2));

        $this->actingAs($this->owner)
            ->put(route('blocks.reorder', $this->lesson), ['order' => $order])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($order, $this->lesson->blocks()->pluck('id')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'blocks.reordered')->count());

        $foreign = Block::factory()->create();

        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->put(route('blocks.reorder', $this->lesson), ['order' => [...array_slice($order, 0, 8), $foreign->id]])
            ->assertSessionHasErrors('order');
    }

    public function test_toggle_hides_and_shows_a_block()
    {
        $block = $this->lesson->blocks()->firstOrFail();

        $this->actingAs($this->owner)->post(route('blocks.toggle', $block))->assertRedirect();
        $this->assertFalse($block->fresh()?->is_visible);

        $this->actingAs($this->owner)->post(route('blocks.toggle', $block));
        $this->assertTrue($block->fresh()?->is_visible);
    }

    public function test_duplicate_copies_the_block_right_after_the_original_with_its_attachments()
    {
        $vocabulary = $this->lesson->blocks()->where('type', BlockType::Vocabulary->value)->firstOrFail();
        $item = LexiconItem::factory()->create();
        $vocabulary->lexiconItems()->attach($item->id, ['position' => 1]);

        $this->actingAs($this->owner)->post(route('blocks.duplicate', $vocabulary))->assertRedirect();

        $types = $this->lesson->blocks()->pluck('type')->map(fn (BlockType $type): string => $type->value)->all();

        $this->assertSame(['situation', 'vocabulary', 'vocabulary', 'expressions'], array_slice($types, 0, 4));
        $copy = $this->lesson->blocks()->get()[2];
        $this->assertSame([$item->id], $copy->lexiconItems()->pluck('lexicon_items.id')->all());
    }

    public function test_delete_removes_the_block_and_renumbers_the_rest()
    {
        $block = $this->lesson->blocks()->get()[3];

        $this->actingAs($this->owner)->delete(route('blocks.destroy', $block))->assertRedirect();

        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
        $this->assertSame(range(1, 8), $this->lesson->blocks()->pluck('position')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'block.deleted')->count());
    }

    public function test_updating_a_block_replaces_its_settings_and_syncs_its_lists()
    {
        $practice = $this->lesson->blocks()->where('type', BlockType::Practice->value)->firstOrFail();
        $activity = Activity::factory()->create();
        $other = Activity::factory()->create();

        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $practice), [
                'title' => 'Practice time',
                'settings' => ['subtitle' => 'Pick one.', 'motto' => 'Go!'],
                'activity_ids' => [$other->id, $activity->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $practice->refresh();

        $this->assertSame('Practice time', $practice->title);
        $this->assertEquals(['subtitle' => 'Pick one.', 'motto' => 'Go!'], $practice->settings);
        $this->assertSame([$other->id, $activity->id], $practice->placements()->pluck('activity_id')->all());

        $roleplay = $this->lesson->blocks()->where('type', BlockType::AiRoleplay->value)->firstOrFail();
        $scenarios = AiScenario::factory()->count(2)->create(['department_id' => $this->department->id]);
        $ids = [$scenarios[1]->id, $scenarios[0]->id];

        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $roleplay), ['scenario_ids' => $ids])
            ->assertSessionHasNoErrors();

        $this->assertSame($ids, $roleplay->fresh()?->scenarioIds());
        $this->assertSame($ids, $roleplay->fresh()?->scenarios()->pluck('ai_scenarios.id')->all());
        $this->assertDatabaseHas('block_ai_scenario', [
            'block_id' => $roleplay->id,
            'ai_scenario_id' => $ids[0],
            'position' => 1,
        ]);

        $otherDepartment = Department::factory()->create();
        $foreignScenario = AiScenario::factory()->create(['department_id' => $otherDepartment->id]);

        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->patch(route('blocks.update', $roleplay), ['scenario_ids' => [$foreignScenario->id]])
            ->assertSessionHasErrors('scenario_ids');

        $this->assertSame($ids, $roleplay->fresh()?->scenarioIds());
    }
}
