<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Employees\EmployeeService;
use App\Services\Hotels\HotelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Plan administration and employee caps (SUB-01, SUB-02, SUB-03, SUB-05). */
class SubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
    }

    public function test_the_three_seed_plans_have_the_requested_limits_prices_and_point_pool(): void
    {
        $plans = SubscriptionPlan::query()->forHotels()->orderBy('employee_limit')->get()->keyBy('slug');

        $this->assertSame(['standard', 'gold', 'diamond'], $plans->keys()->all());
        $this->assertSame([4, 7, 15], $plans->pluck('employee_limit')->all());
        $this->assertSame([12000, 20000, 40000], $plans->pluck('price_dzd')->all());
        $this->assertSame(2000, $plans['standard']->points_per_employee);
        $this->assertSame(1000, $plans['standard']->bonus_points_per_employee);
        $this->assertSame(100, $plans['standard']->voice_points_per_10_minutes);
        $this->assertSame(50, $plans['standard']->ai_action_points);
        $this->assertSame(12000, $plans['standard']->pointsPool());
        $this->assertSame(21000, $plans['gold']->pointsPool());
        $this->assertSame(45000, $plans['diamond']->pointsPool());
    }

    public function test_the_super_admin_can_view_and_edit_every_plan_setting_with_an_audit_record(): void
    {
        $plan = $this->plan('gold');

        $this->actingAs($this->owner)
            ->get(route('subscriptions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Subscriptions')
                // Three hotel plans first, then the three individual plans.
                ->has('plans', 6)
                ->where('plans.3.audience', 'individual')
                ->where('plans.1.name', 'Gold')
                ->where('plans.1.priceDzd', 20000)
                ->where('plans.1.pointPool', 21000));

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [
                ...$this->planInput($plan),
                'name' => 'Gold Plus',
                'employee_limit' => 8,
                'price_dzd' => 27500,
                'price_usd' => 199.5,
                'points_per_employee' => 2500,
                'bonus_points_per_employee' => 1250,
                'voice_points_per_10_minutes' => 125,
                'ai_action_points' => 75,
                'is_active' => true,
            ])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Gold Plus', $plan->name);
        $this->assertSame('gold', $plan->slug);
        $this->assertSame(8, $plan->employee_limit);
        $this->assertSame(27500, $plan->price_dzd);
        $this->assertSame(199.5, $plan->price_usd);
        $this->assertSame(2500, $plan->points_per_employee);
        $this->assertSame(1250, $plan->bonus_points_per_employee);
        $this->assertSame(125, $plan->voice_points_per_10_minutes);
        $this->assertSame(75, $plan->ai_action_points);

        $audit = AuditLog::query()->where('action', 'subscription.plan_updated')->sole();
        $this->assertSame($this->owner->id, $audit->actor_id);
        $this->assertSame(20000, $audit->changes['before']['price_dzd']);
        $this->assertSame(27500, $audit->changes['after']['price_dzd']);
        $this->assertSame(8, $audit->changes['after']['employee_limit']);
    }

    public function test_the_super_admin_sets_dzd_and_usd_prices_for_extra_points_and_seats(): void
    {
        $plan = $this->plan('gold');

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [
                ...$this->planInput($plan),
                'extra_points_price_dzd' => 1500,
                'extra_points_price_usd' => 9.99,
                'extra_seat_price_dzd' => 2500,
                'extra_seat_price_usd' => 18.5,
            ])
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(1500, $plan->extra_points_price_dzd);
        $this->assertSame(9.99, $plan->extra_points_price_usd);
        $this->assertSame(2500, $plan->extra_seat_price_dzd);
        $this->assertSame(18.5, $plan->extra_seat_price_usd);

        $this->actingAs($this->owner)
            ->get(route('subscriptions'))
            ->assertInertia(fn ($page) => $page
                ->where('plans.1.extraPointsPriceUsd', 9.99)
                ->where('plans.1.extraSeatPriceDzd', 2500));

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [
                ...$this->planInput($plan),
                'extra_points_price_usd' => -1,
                'extra_seat_price_usd' => 1.234,
            ])
            ->assertSessionHasErrors(['extra_points_price_usd', 'extra_seat_price_usd']);
    }

    public function test_invalid_plan_settings_are_rejected_without_changing_the_plan(): void
    {
        $plan = $this->plan('standard');

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [
                ...$this->planInput($plan),
                'employee_limit' => 0,
                'price_dzd' => -1,
            ])
            ->assertSessionHasErrors(['employee_limit', 'price_dzd']);

        $this->assertSame(4, $plan->fresh()->employee_limit);
        $this->assertSame(12000, $plan->fresh()->price_dzd);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'subscription.plan_updated']);
    }

    public function test_the_last_active_plan_cannot_be_disabled(): void
    {
        $plan = $this->plan('standard');
        SubscriptionPlan::query()->whereKeyNot($plan->id)->update(['is_active' => false]);

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [
                ...$this->planInput($plan),
                'is_active' => false,
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($plan->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'subscription.plan_updated']);
    }

    public function test_the_super_admin_can_assign_an_active_plan_to_a_hotel_and_the_change_is_audited(): void
    {
        $hotel = Hotel::factory()->create();
        $gold = $this->plan('gold');

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->put(route('subscriptions.hotel-plan'), [
                'hotel_id' => $hotel->id,
                'plan_id' => $gold->id,
            ])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $this->assertSame($gold->id, $hotel->fresh()->subscription_plan_id);
        $audit = AuditLog::query()->where('action', 'hotel.subscription_plan_changed')->sole();
        $this->assertSame($this->owner->id, $audit->actor_id);
        $this->assertSame($gold->id, $audit->changes['to_plan_id']);
        $this->assertSame(20000, $audit->changes['price_dzd']);
    }

    public function test_inactive_plans_cannot_be_assigned_and_managers_cannot_manage_the_plan_catalog(): void
    {
        $hotel = Hotel::factory()->create();
        $gold = $this->plan('gold');
        $gold->update(['is_active' => false]);

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->put(route('subscriptions.hotel-plan'), [
                'hotel_id' => $hotel->id,
                'plan_id' => $gold->id,
            ])
            ->assertSessionHasErrors('plan_id');

        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $this->actingAs($manager)->get(route('subscriptions'))->assertForbidden();
        $this->actingAs($manager)->patch(route('subscriptions.plans.update', $this->plan('standard')), $this->planInput($this->plan('standard')))->assertForbidden();

        $this->assertSame($this->plan('standard')->id, $hotel->fresh()->subscription_plan_id);
    }

    public function test_new_hotels_default_to_standard_and_new_employees_get_the_plan_base_points(): void
    {
        $standard = $this->plan('standard');
        $hotel = app(HotelService::class)->create([
            'name' => 'New Standard Hotel',
            'city' => 'Algiers',
            'manager_name' => 'Amina Benali',
            'manager_email' => 'amina@example.test',
            'contract_starts_on' => '2026-10-01',
            'contract_ends_on' => '2027-09-30',
        ]);

        $this->assertSame($standard->id, $hotel->subscription_plan_id);

        $standard->update(['points_per_employee' => 2750]);
        $department = Department::factory()->create();
        SeatQuota::factory()->create([
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'allowed_seats' => 2,
        ]);

        $employee = app(EmployeeService::class)->create([
            'name' => 'Samir Employee',
            'username' => 'samir.employee',
            'email' => null,
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'status' => 'active',
            'allow_reminder_emails' => false,
            'password' => 'Secret-Pass-12',
        ], $this->owner);

        $this->assertSame(2750, $employee->ai_points_allocated);
    }

    public function test_the_hotel_wide_employee_limit_is_enforced_when_an_employee_is_created(): void
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();
        SeatQuota::factory()->create([
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'allowed_seats' => 10,
        ]);
        User::factory()->employee()->count(4)->create([
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'status' => AccountStatus::Active,
        ]);

        $this->actingAs($this->owner)
            ->from(route('employees'))
            ->post(route('employees.store'), [
                'name' => 'Fifth Employee',
                'username' => 'fifth.employee',
                'password' => 'Secret-Pass-12',
                'email' => 'fifth@example.test',
                'hotel_id' => $hotel->id,
                'department_id' => $department->id,
                'status' => 'active',
                'allow_reminder_emails' => false,
            ])
            ->assertSessionHasErrors('department_id');

        $this->assertSame(4, User::query()->where('hotel_id', $hotel->id)->whereHas('roles', fn ($query) => $query->where('name', 'employee'))->count());
        $this->assertDatabaseMissing('users', ['username' => 'fifth.employee']);
    }

    private function plan(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function planInput(SubscriptionPlan $plan): array
    {
        return [
            'name' => $plan->name,
            'employee_limit' => $plan->employee_limit,
            'price_dzd' => $plan->price_dzd,
            'price_usd' => $plan->price_usd,
            'extra_points_price_dzd' => $plan->extra_points_price_dzd,
            'extra_points_price_usd' => $plan->extra_points_price_usd,
            'extra_seat_price_dzd' => $plan->extra_seat_price_dzd,
            'extra_seat_price_usd' => $plan->extra_seat_price_usd,
            'points_per_employee' => $plan->points_per_employee,
            'bonus_points_per_employee' => $plan->bonus_points_per_employee,
            'voice_points_per_10_minutes' => $plan->voice_points_per_10_minutes,
            'ai_action_points' => $plan->ai_action_points,
            'is_active' => $plan->is_active,
        ];
    }
}
