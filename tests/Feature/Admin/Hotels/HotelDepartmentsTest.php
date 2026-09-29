<?php

namespace Tests\Feature\Admin\Hotels;

use App\Enums\AccountStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hotel row action "Departments": add a department to a hotel (from
 * the catalogue or new), or take one off it without touching any employee
 * (SUB-01, ORG-02, ORG-03, DATA-10).
 */
class HotelDepartmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $this->hotel = Hotel::factory()->create(['name' => 'Sofitel Algiers']);
    }

    public function test_a_shared_department_is_added_with_its_seats()
    {
        $department = Department::factory()->create(['name' => 'Housekeeping', 'hotel_id' => null]);

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->post(route('hotels.departments.store', $this->hotel), ['department_id' => $department->id, 'allowed_seats' => 0])
            ->assertRedirect(route('hotels'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seat_quotas', [
            'hotel_id' => $this->hotel->id,
            'department_id' => $department->id,
            'allowed_seats' => 0,
        ]);
        $this->assertSame(1, AuditLog::query()->where('action', 'hotel.department_added')->count());
    }

    public function test_a_new_department_is_created_for_the_hotel_and_linked()
    {
        $this->actingAs($this->owner)
            ->post(route('hotels.departments.store', $this->hotel), ['name' => 'Spa & Wellness', 'allowed_seats' => 4])
            ->assertSessionHasNoErrors();

        $department = Department::query()->where('name', 'Spa & Wellness')->firstOrFail();

        $this->assertSame($this->hotel->id, $department->hotel_id);
        $this->assertSame(4, SeatQuota::query()->where('hotel_id', $this->hotel->id)->where('department_id', $department->id)->value('allowed_seats'));
    }

    public function test_another_hotels_own_department_cannot_be_added()
    {
        $foreign = Department::factory()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->post(route('hotels.departments.store', $this->hotel), ['department_id' => $foreign->id, 'allowed_seats' => 2])
            ->assertSessionHasErrors('department_id');

        $this->assertSame(0, SeatQuota::query()->where('hotel_id', $this->hotel->id)->count());
    }

    public function test_a_department_without_active_employees_is_removed_and_its_people_kept()
    {
        $department = Department::factory()->create(['hotel_id' => null]);
        SeatQuota::factory()->create(['hotel_id' => $this->hotel->id, 'department_id' => $department->id, 'allowed_seats' => 5]);
        $former = User::factory()->employee()->forHotel($this->hotel, $department)->create(['status' => AccountStatus::Inactive]);

        $this->actingAs($this->owner)
            ->delete(route('hotels.departments.destroy', [$this->hotel, $department]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('seat_quotas', ['hotel_id' => $this->hotel->id, 'department_id' => $department->id]);
        $this->assertSame($department->id, $former->fresh()?->department_id);
        $this->assertNotNull($department->fresh());
        $this->assertSame(1, AuditLog::query()->where('action', 'hotel.department_removed')->count());
    }

    public function test_a_department_with_active_employees_is_not_removed()
    {
        $department = Department::factory()->create(['hotel_id' => null, 'name' => 'Reception']);
        SeatQuota::factory()->create(['hotel_id' => $this->hotel->id, 'department_id' => $department->id, 'allowed_seats' => 5]);
        User::factory()->employee()->forHotel($this->hotel, $department)->create(['status' => AccountStatus::Active]);

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->delete(route('hotels.departments.destroy', [$this->hotel, $department]))
            ->assertSessionHasErrors('department');

        $this->assertDatabaseHas('seat_quotas', ['hotel_id' => $this->hotel->id, 'department_id' => $department->id]);
    }

    public function test_an_employee_cannot_change_a_hotels_departments()
    {
        $department = Department::factory()->create(['hotel_id' => null]);
        $employee = User::factory()->employee()->forHotel($this->hotel, $department)->create();

        $this->actingAs($employee)
            ->post(route('hotels.departments.store', $this->hotel), ['department_id' => $department->id, 'allowed_seats' => 1])
            ->assertForbidden();
    }
}
