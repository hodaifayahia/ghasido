<?php

namespace Tests\Feature\Admin;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Services\Subscriptions\EmployeeAiPointsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/** Manager allocations, monthly balances and hotel isolation (ROLE-02, AIL-01, AIL-04). */
class EmployeeAiPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_a_manager_sees_only_their_hotel_employees_and_the_plan_point_pool(): void
    {
        $hotel = Hotel::factory()->create(['name' => 'Hotel One']);
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $department = Department::factory()->create(['name' => 'Reception']);
        $first = $this->employee($hotel, $department, 'Amina Employee');
        $second = $this->employee($hotel, $department, 'Karim Employee');

        $otherHotel = Hotel::factory()->create(['name' => 'Hotel Two']);
        $this->employee($otherHotel, $department, 'Foreign Employee');

        $response = $this->actingAs($manager)->get(route('ai-points'))->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('manager/AiPoints')
            ->where('hotel.id', $hotel->id)
            ->where('plan.monthlyPointPool', 12000)
            ->where('plan.pointsPerEmployee', 2000)
            ->where('plan.bonusPointsPerEmployee', 1000)
            ->where('plan.voicePointsPer10Minutes', 100)
            ->where('plan.aiActionPoints', 50)
            ->where('summary.employees', 2)
            ->where('summary.allocated', 4000)
            ->has('employees', 2)
            ->where('employees.0.id', $first->id)
            ->where('employees.1.id', $second->id));
    }

    public function test_a_manager_can_change_an_employee_allocation_within_the_pool_and_it_is_audited(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $employee = $this->employee($hotel, Department::factory()->create());

        $this->actingAs($manager)
            ->from(route('ai-points'))
            ->patch(route('ai-points.update', $employee), ['ai_points_allocated' => 5000])
            ->assertRedirect(route('ai-points'))
            ->assertSessionHasNoErrors();

        $this->assertSame(5000, $employee->fresh()->ai_points_allocated);
        $audit = AuditLog::query()->where('action', 'employee.ai_points_updated')->sole();
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertSame(2000, $audit->changes['from']);
        $this->assertSame(5000, $audit->changes['to']);
        $this->assertSame($hotel->id, $audit->changes['hotel_id']);
    }

    public function test_a_manager_cannot_allocate_more_than_the_hotel_monthly_pool(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $department = Department::factory()->create();
        $employees = collect(range(1, 4))->map(fn (int $index) => $this->employee($hotel, $department, "Employee {$index}"));
        $target = $employees->first();

        $this->actingAs($manager)
            ->from(route('ai-points'))
            ->patch(route('ai-points.update', $target), ['ai_points_allocated' => 6001])
            ->assertSessionHasErrors('ai_points_allocated');

        $this->assertSame(2000, $target->fresh()->ai_points_allocated);
        $this->assertSame(8000, $employees->sum(fn (User $employee): int => $employee->fresh()->ai_points_allocated));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'employee.ai_points_updated']);
    }

    public function test_a_manager_can_reduce_existing_allocations_after_a_plan_change_leaves_the_hotel_over_budget(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $department = Department::factory()->create();
        $employees = collect(range(1, 4))->map(fn (int $index) => $this->employee($hotel, $department, "Employee {$index}", 4000));
        $target = $employees->first();

        SubscriptionPlan::query()->whereKey($hotel->subscription_plan_id)->update([
            'points_per_employee' => 500,
            'bonus_points_per_employee' => 500,
        ]);

        $this->actingAs($manager)
            ->from(route('ai-points'))
            ->patch(route('ai-points.update', $target), ['ai_points_allocated' => 3000])
            ->assertRedirect(route('ai-points'))
            ->assertSessionHasNoErrors();

        $this->assertSame(3000, $target->fresh()->ai_points_allocated);
        $this->assertSame(15000, $employees->sum(fn (User $employee): int => $employee->fresh()->ai_points_allocated));
    }

    public function test_an_allocation_cannot_be_lowered_below_points_already_spent_this_month(): void
    {
        Date::setTestNow(Date::parse('2026-09-23 12:00:00'));
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $employee = $this->employee($hotel, Department::factory()->create());
        (new UsageMeter)->record($employee, AiFeature::WritingEval, AiUsageInfo::none());

        $this->actingAs($manager)
            ->from(route('ai-points'))
            ->patch(route('ai-points.update', $employee), ['ai_points_allocated' => 49])
            ->assertSessionHasErrors('ai_points_allocated');

        $this->assertSame(2000, $employee->fresh()->ai_points_allocated);
    }

    public function test_the_monthly_usage_summary_resets_at_the_start_of_a_new_calendar_month(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $employee = $this->employee($hotel, Department::factory()->create());
        $service = app(EmployeeAiPointsService::class);

        Date::setTestNow(Date::parse('2026-09-30 23:59:00'));
        (new UsageMeter)->record($employee, AiFeature::WritingEval, AiUsageInfo::none());
        $this->assertSame(50, $service->forHotel($hotel)['summary']['used']);

        Date::setTestNow(Date::parse('2026-10-01 00:00:00'));
        $this->assertSame(0, $service->forHotel($hotel)['summary']['used']);
        $this->assertSame(2000, $service->forHotel($hotel)['employees'][0]['remaining']);
    }

    public function test_the_employee_receives_their_remaining_monthly_points_in_shared_page_props(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = $this->employee($hotel, Department::factory()->create(), 'Balance Employee', 500);
        (new UsageMeter)->record($employee, AiFeature::WritingEval, AiUsageInfo::none());

        $this->actingAs($employee)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('aiPointBalance.role', 'employee')
                ->where('aiPointBalance.total', 500)
                ->where('aiPointBalance.used', 50)
                ->where('aiPointBalance.remaining', 450)
                ->where('aiPointBalance.percent', 90));
    }

    public function test_managers_cannot_read_or_change_ai_points_for_another_hotel_or_a_non_employee(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $otherHotel = Hotel::factory()->create();
        $foreignEmployee = $this->employee($otherHotel, Department::factory()->create());
        $sameHotelManager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $sameHotelEmployee = $this->employee($hotel, Department::factory()->create());

        $this->actingAs($manager)
            ->patch(route('ai-points.update', $foreignEmployee), ['ai_points_allocated' => 4000])
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('ai-points.update', $sameHotelManager), ['ai_points_allocated' => 4000])
            ->assertForbidden();

        $this->actingAs(User::factory()->employee()->create(['hotel_id' => $hotel->id]))
            ->get(route('ai-points'))
            ->assertForbidden();

        $this->assertSame(2000, $foreignEmployee->fresh()->ai_points_allocated);
        $this->assertSame(2000, $sameHotelEmployee->fresh()->ai_points_allocated);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'employee.ai_points_updated']);
    }

    private function employee(Hotel $hotel, Department $department, string $name = 'Employee', int $points = 2000): User
    {
        return User::factory()->employee()->create([
            'name' => $name,
            'username' => str($name)->slug('.')->toString().'.'.fake()->unique()->numberBetween(1, 999999),
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'ai_points_allocated' => $points,
        ]);
    }
}
