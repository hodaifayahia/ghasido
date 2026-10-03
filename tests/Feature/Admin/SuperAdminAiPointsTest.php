<?php

namespace Tests\Feature\Admin;

use App\Enums\AiFeature;
use App\Models\AiUsage;
use App\Models\Hotel;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client requests 2026-10-02: the Super Admin shares a hotel's AI points
 * like the manager does, and sees which account used points and where.
 */
class SuperAdminAiPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_super_admin_shares_any_hotels_points()
    {
        $owner = User::factory()->superAdmin()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'gold')->firstOrFail();
        $first = Hotel::factory()->active()->create(['name' => 'Aaa Hotel', 'subscription_plan_id' => $plan->id]);
        $second = Hotel::factory()->active()->create(['name' => 'Bbb Hotel', 'subscription_plan_id' => $plan->id]);
        $employee = User::factory()->employee()->create(['hotel_id' => $second->id, 'ai_points_allocated' => 1000]);

        $this->actingAs($owner)
            ->get(route('ai-points', ['hotel' => $second->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('manager/AiPoints')
                ->where('hotel.id', $second->id)
                ->where('hotelPicker.current', $second->id)
                ->has('hotelPicker.options', 2));

        $this->actingAs($owner)
            ->patch(route('ai-points.update', $employee), ['ai_points_allocated' => 1500])
            ->assertSessionHasNoErrors();

        $this->assertSame(1500, $employee->refresh()->ai_points_allocated);
        $this->assertNotNull($first);
    }

    public function test_a_manager_still_cannot_touch_another_hotels_points()
    {
        $plan = SubscriptionPlan::query()->where('slug', 'gold')->firstOrFail();
        $mine = Hotel::factory()->active()->create(['subscription_plan_id' => $plan->id]);
        $theirs = Hotel::factory()->active()->create(['subscription_plan_id' => $plan->id]);
        $manager = User::factory()->manager()->create(['hotel_id' => $mine->id]);
        $outsider = User::factory()->employee()->create(['hotel_id' => $theirs->id, 'ai_points_allocated' => 1000]);

        $this->actingAs($manager)
            ->patch(route('ai-points.update', $outsider), ['ai_points_allocated' => 5])
            ->assertForbidden();
    }

    public function test_ai_usage_shows_who_used_points_and_where()
    {
        $owner = User::factory()->superAdmin()->create();
        $hotel = Hotel::factory()->active()->create(['name' => 'Blue Coast']);
        $sidAli = User::factory()->employee()->create(['name' => 'Sid Ali', 'hotel_id' => $hotel->id]);

        AiUsage::factory()->create(['user_id' => $sidAli->id, 'hotel_id' => $hotel->id, 'feature' => AiFeature::RoleplayTurn, 'points_charged' => 60]);
        AiUsage::factory()->create(['user_id' => $sidAli->id, 'hotel_id' => $hotel->id, 'feature' => AiFeature::RoleplayTurn, 'points_charged' => 40]);

        $this->actingAs($owner)
            ->get(route('ai-usage.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.byUser.0.name', 'Sid Ali')
                ->where('report.byUser.0.hotel', 'Blue Coast')
                ->where('report.byUser.0.points', 100)
                ->where('report.byUser.0.where.0.points', 100));
    }
}
