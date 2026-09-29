<?php

namespace Tests\Feature\Admin;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Delete" in the AI Scenarios table (CMS-01). A scenario no learner
 * practised is deleted; one with learner attempts leaves the library and
 * learners while every transcript and score stays (RP-11, DATA-10). It
 * always leaves the lesson steps that offered it (RP-14).
 */
class AiScenarioDeleteTest extends TestCase
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

    public function test_a_scenario_without_learner_attempts_is_deleted_and_detached_from_lessons()
    {
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id]);
        $block = Block::factory()->create();
        $block->scenarios()->attach($scenario->id, ['position' => 1]);
        $preview = RoleplayAttempt::factory()->preview()->create(['ai_scenario_id' => $scenario->id]);

        $this->actingAs($this->owner)
            ->delete(route('ai-scenarios.destroy', $scenario))
            ->assertRedirect(route('ai-scenarios'))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertNull($scenario->fresh());
        $this->assertNull($preview->fresh());
        $this->assertSame([], $block->fresh()?->scenarioIds());
        $this->assertSame(1, AuditLog::query()->where('action', 'scenario.deleted')->count());
    }

    public function test_a_scenario_with_learner_attempts_is_removed_but_its_attempts_are_kept()
    {
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id, 'status' => ContentStatus::Published]);
        $other = AiScenario::factory()->create(['department_id' => $this->department->id]);
        $block = Block::factory()->create();
        $block->scenarios()->attach($scenario->id, ['position' => 1]);
        $attempt = RoleplayAttempt::factory()->create(['ai_scenario_id' => $scenario->id]);

        $this->actingAs($this->owner)
            ->get(route('ai-scenarios'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('library.scenarios', fn ($rows) => collect($rows)->contains(
                    fn (array $row): bool => $row['id'] === (string) $scenario->id
                        && $row['learnerAttempts'] === 1
                        && $row['lessonSteps'] === 1
                        && $row['canDelete'] === true,
                ))
            );

        $this->actingAs($this->owner)
            ->delete(route('ai-scenarios.destroy', $scenario))
            ->assertRedirect(route('ai-scenarios'));

        $scenario->refresh();
        $this->assertNotNull($scenario->archived_at);
        $this->assertSame(ContentStatus::Draft, $scenario->status);
        $this->assertSame($scenario->id, RoleplayAttempt::query()->findOrFail($attempt->id)->ai_scenario_id);
        $this->assertSame([], $block->fresh()?->scenarioIds());
        $this->assertSame(1, AuditLog::query()->where('action', 'scenario.removed')->count());
        $this->assertSame(0, AiScenario::query()->published()->whereKey($scenario->id)->count());

        $this->actingAs($this->owner)
            ->get(route('ai-scenarios'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('library.scenarios', 1)
                ->where('library.scenarios.0.id', (string) $other->id)
            );
    }

    public function test_users_without_the_manage_capability_are_refused()
    {
        $scenario = AiScenario::factory()->create(['department_id' => $this->department->id]);
        $manager = User::factory()->manager()->forHotel(Hotel::factory()->create(), $this->department)->create();

        $this->actingAs($manager)->delete(route('ai-scenarios.destroy', $scenario))->assertForbidden();
        $this->assertNotNull($scenario->fresh());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_hotel_admin_may_delete_their_hotels_scenario_but_not_shared_or_another_hotels()
    {
        RoleModel::findByName(Role::Manager->value)->givePermissionTo([Permission::ScenariosView->value, Permission::ScenariosManage->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->forHotel($hotel, $this->department)->create();

        $shared = AiScenario::factory()->create(['department_id' => $this->department->id, 'hotel_id' => null]);
        $theirs = AiScenario::factory()->create(['department_id' => $this->department->id, 'hotel_id' => Hotel::factory()->create()->id]);
        $own = AiScenario::factory()->create(['department_id' => $this->department->id, 'hotel_id' => $hotel->id]);

        $this->actingAs($manager)->delete(route('ai-scenarios.destroy', $shared))->assertForbidden();
        $this->actingAs($manager)->delete(route('ai-scenarios.destroy', $theirs))->assertForbidden();
        $this->assertNotNull($shared->fresh());
        $this->assertNotNull($theirs->fresh());

        $this->actingAs($manager)->delete(route('ai-scenarios.destroy', $own))->assertRedirect();
        $this->assertNull($own->fresh());
    }
}
