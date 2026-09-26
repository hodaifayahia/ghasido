<?php

namespace Tests\Feature\Admin;

use App\Models\Hotel;
use App\Models\HotelAiPointTopUp;
use App\Models\User;
use App\Services\Subscriptions\AiPointsBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/** Paid hotel point top-ups are Super Admin only, recorded and month-scoped (AIL-01, ROLE-02, ADM-03). */
class HotelAiPointTopUpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow(Date::parse('2026-09-25 12:00:00'));
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_a_super_admin_can_record_a_received_payment_and_add_points_for_this_month(): void
    {
        $hotel = Hotel::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);

        $this->actingAs($admin)
            ->from(route('subscriptions'))
            ->post(route('subscriptions.ai-point-topups.store'), [
                'hotel_id' => $hotel->id,
                'points' => 4200,
                'amount_dzd' => 9000,
                'payment_reference' => 'RCPT-2026-09-25',
                'payment_received' => true,
            ])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hotel_ai_point_top_ups', [
            'hotel_id' => $hotel->id,
            'points' => 4200,
            'currency' => 'DZD',
            'amount_dzd' => 9000,
            'amount_usd' => null,
            'payment_reference' => 'RCPT-2026-09-25',
            'received_by' => $admin->id,
        ]);
        $this->assertSame('2026-09-01', HotelAiPointTopUp::query()->sole()->month_start->toDateString());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'hotel.ai_points_topped_up',
        ]);

        $balance = app(AiPointsBalanceService::class)->forUser($manager);
        $this->assertSame(16200, $balance['total']);
        $this->assertSame(16200, $balance['remaining']);

        $this->actingAs($manager)
            ->get(route('ai-points'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('plan.monthlyPointPool', 16200)
                ->where('aiPointBalance.role', 'manager')
                ->where('aiPointBalance.total', 16200)
                ->where('aiPointBalance.remaining', 16200));
    }

    public function test_an_international_hotel_payment_is_recorded_in_usd(): void
    {
        $hotel = Hotel::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->from(route('subscriptions'))
            ->post(route('subscriptions.ai-point-topups.store'), [
                'hotel_id' => $hotel->id,
                'points' => 3000,
                'currency' => 'USD',
                'amount_usd' => 29.97,
                'payment_received' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hotel_ai_point_top_ups', [
            'hotel_id' => $hotel->id,
            'points' => 3000,
            'currency' => 'USD',
            'amount_dzd' => 0,
            'amount_usd' => 29.97,
        ]);
    }

    public function test_a_usd_payment_needs_a_usd_amount(): void
    {
        $hotel = Hotel::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->from(route('subscriptions'))
            ->post(route('subscriptions.ai-point-topups.store'), [
                'hotel_id' => $hotel->id,
                'points' => 3000,
                'currency' => 'USD',
                'amount_dzd' => 9000,
                'payment_received' => true,
            ])
            ->assertSessionHasErrors('amount_usd');

        $this->assertDatabaseCount('hotel_ai_point_top_ups', 0);
    }

    public function test_managers_and_employees_cannot_record_hotel_point_payments(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);
        $payment = [
            'hotel_id' => $hotel->id,
            'points' => 4200,
            'amount_dzd' => 9000,
            'payment_received' => true,
        ];

        $this->actingAs($manager)
            ->post(route('subscriptions.ai-point-topups.store'), $payment)
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('subscriptions.ai-point-topups.store'), $payment)
            ->assertForbidden();

        $this->assertDatabaseCount('hotel_ai_point_top_ups', 0);
    }

    public function test_points_are_not_added_until_payment_is_confirmed(): void
    {
        $hotel = Hotel::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->from(route('subscriptions'))
            ->post(route('subscriptions.ai-point-topups.store'), [
                'hotel_id' => $hotel->id,
                'points' => 4200,
                'amount_dzd' => 9000,
                'payment_received' => false,
            ])
            ->assertSessionHasErrors('payment_received');

        $this->assertDatabaseCount('hotel_ai_point_top_ups', 0);
    }
}
