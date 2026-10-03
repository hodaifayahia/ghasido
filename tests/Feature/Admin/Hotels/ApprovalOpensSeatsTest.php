<?php

namespace Tests\Feature\Admin\Hotels;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Client report 2026-10-02: "I subscribed as a hotel, became the manager
 * and found no way to add an employee." Approval now opens the plan's
 * seats in every active shared department.
 */
class ApprovalOpensSeatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_hotels_manager_can_add_an_employee_at_once()
    {
        Mail::fake();
        $owner = User::factory()->superAdmin()->create();
        $reception = Department::factory()->create(['hotel_id' => null, 'is_active' => true, 'name' => 'Reception']);
        Department::factory()->create(['hotel_id' => null, 'is_active' => false, 'name' => 'Old']);

        $this->post(route('hotel-signup.store'), [
            'name' => 'Blue Coast Hotel',
            'city' => 'Oran',
            'manager_name' => 'Nassim Benali',
            'manager_email' => 'manager@bluecoast.test',
            'manager_username' => 'blue.coast.manager',
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
        ])->assertSessionHasNoErrors();

        $hotel = Hotel::query()->where('name', 'Blue Coast Hotel')->firstOrFail();
        $this->actingAs($owner)->post(route('hotels.approve', $hotel))->assertSessionHasNoErrors();

        $limit = (int) $hotel->subscriptionPlan()->value('employee_limit');
        $quotas = SeatQuota::query()->withoutGlobalScopes()->where('hotel_id', $hotel->id)->get();
        $this->assertSame([$reception->id], $quotas->pluck('department_id')->all());
        $this->assertSame($limit, $quotas->first()?->allowed_seats);

        $manager = User::query()->where('username', 'blue.coast.manager')->firstOrFail();

        $r = $this->actingAs($manager)
            ->post(route('employees.store'), [
                'name' => 'Amine Ben Ali',
                'username' => 'amine.benali',
                'password' => 'Secret-Pass-12',
                'email' => 'amine.benali@hotel.dz',
                'hotel_id' => $hotel->id,
                'department_id' => $reception->id,
                'status' => 'active',
                'allow_reminder_emails' => true,
            ]);
        $r->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'amine.benali', 'hotel_id' => $hotel->id]);
    }
}
