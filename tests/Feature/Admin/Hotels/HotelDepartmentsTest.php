<?php

namespace Tests\Feature\Admin\Hotels;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Hotel row action "Departments" (SUB-01, ORG-02; restored 2026-09-26). */
class HotelDepartmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_adds_a_catalogue_department_with_seats(): void
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('hotels.departments.store', $hotel), [
                'department_id' => $department->id,
                'allowed_seats' => 6,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(6, SeatQuota::query()
            ->where('hotel_id', $hotel->id)
            ->where('department_id', $department->id)
            ->value('allowed_seats'));
    }

    public function test_a_manager_may_not_add_departments_to_a_hotel(): void
    {
        $hotel = Hotel::factory()->create();

        $this->actingAs(User::factory()->manager()->create())
            ->post(route('hotels.departments.store', $hotel), [
                'department_id' => Department::factory()->create()->id,
                'allowed_seats' => 2,
            ])
            ->assertForbidden();
    }
}
