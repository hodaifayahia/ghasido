<?php

namespace Tests\Feature\Security;

use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): the automatic-reminder cards a hotel's
 * manager sees never name or identify another hotel or its departments
 * (ROLE-02, REM-07).
 */
class TenancyMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_sees_only_rules_reaching_their_hotel_without_other_hotels_details()
    {
        $this->withoutVite();
        $home = Hotel::factory()->create(['name' => 'Home Hotel']);
        $other = Hotel::factory()->create(['name' => 'Rival Palace']);
        $shared = Department::factory()->create(['name' => 'Reception', 'hotel_id' => null]);
        $rivalOnly = Department::factory()->create(['name' => 'Rival Spa', 'hotel_id' => $other->id]);
        $manager = User::factory()->manager()->forHotel($home, $shared)->create();

        $everywhere = AutomationRule::factory()->create(['name' => 'Everyone']);
        $both = AutomationRule::factory()->audience([$home->id, $other->id], [$shared->id, $rivalOnly->id])->create([
            'name' => 'Both hotels',
            'audience_label' => 'Home Hotel + Rival Palace · Reception + Rival Spa',
        ]);
        AutomationRule::factory()->audience([$other->id])->create([
            'name' => 'Rival only',
            'audience_label' => 'Rival Palace',
        ]);

        $this->actingAs($manager)
            ->get(route('messages-reminders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('automations', function ($rules) use ($everywhere, $both, $home, $shared): bool {
                    $rules = collect($rules)->keyBy('id');

                    return $rules->keys()->sort()->values()->all() === collect([$everywhere->id, $both->id])->sort()->values()->all()
                        && $rules[$both->id]['hotelIds'] === [$home->id]
                        && $rules[$both->id]['departmentIds'] === [$shared->id]
                        && ! str_contains((string) $rules[$both->id]['audience'], 'Rival');
                })
            );
    }

    public function test_the_super_admin_still_sees_every_rule_as_stored()
    {
        $this->withoutVite();
        $other = Hotel::factory()->create(['name' => 'Rival Palace']);
        $rule = AutomationRule::factory()->audience([$other->id])->create(['audience_label' => 'Rival Palace']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('messages-reminders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('automations.0.id', $rule->id)
                ->where('automations.0.audience', 'Rival Palace')
                ->where('automations.0.hotelIds', [$other->id])
            );
    }
}
