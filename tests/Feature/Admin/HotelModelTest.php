<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\CapacityState;
use App\Enums\HotelAccessState;
use App\Enums\HotelStatus;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * The model methods the pages call, and the rules they encode
 * (spec 0002, AC-2, AC-4, AC-6, AC-8, AC-20).
 *
 * Nothing here is stored. Every figure is computed on read, so these tests are
 * what stands between the page and a number that quietly lies.
 */
class HotelModelTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- derived status

    public function test_a_contract_outside_the_window_reads_as_active()
    {
        $hotel = Hotel::factory()->active(45)->create();

        $this->assertSame(HotelStatus::Active, $hotel->derivedStatus());
    }

    public function test_a_contract_inside_the_window_reads_as_expiring_with_nothing_stored()
    {
        $hotel = Hotel::factory()->expiring(11)->create();

        $this->assertSame(HotelStatus::Expiring, $hotel->derivedStatus());
        // Expiring is never written down: the stored state stays active.
        $this->assertSame(HotelAccessState::Active, $hotel->fresh()?->access_state);
    }

    public function test_a_contract_in_the_past_reads_as_ended_with_nothing_stored()
    {
        $hotel = Hotel::factory()->ended(18)->create();

        $this->assertSame(HotelStatus::Ended, $hotel->derivedStatus());
        $this->assertSame(-18, $hotel->daysRemaining());
        $this->assertSame(HotelAccessState::Active, $hotel->fresh()?->access_state);
    }

    public function test_the_window_boundary_is_inclusive()
    {
        $window = Hotel::expiringWithinDays();

        $this->assertSame(HotelStatus::Expiring, Hotel::factory()->active($window)->create()->derivedStatus());
        $this->assertSame(HotelStatus::Active, Hotel::factory()->active($window + 1)->create()->derivedStatus());
        $this->assertSame(HotelStatus::Expiring, Hotel::factory()->active(0)->create()->derivedStatus());
    }

    public function test_changing_the_configured_window_moves_the_status_with_no_code_change()
    {
        $hotel = Hotel::factory()->active(20)->create();

        config(['guesvia.hotels.expiring_within_days' => 30]);
        $this->assertSame(HotelStatus::Expiring, $hotel->derivedStatus());

        config(['guesvia.hotels.expiring_within_days' => 14]);
        $this->assertSame(HotelStatus::Active, $hotel->derivedStatus());
    }

    public function test_extending_an_ended_contract_brings_it_back_with_no_state_change()
    {
        $hotel = Hotel::factory()->ended(5)->create();
        $this->assertSame(HotelStatus::Ended, $hotel->derivedStatus());

        $hotel->contract_ends_on = Date::now()->addDays(90);

        $this->assertSame(HotelStatus::Active, $hotel->derivedStatus());
        $this->assertSame(HotelAccessState::Active, $hotel->access_state);
    }

    public function test_stored_states_pass_through_untouched()
    {
        $this->assertSame(HotelStatus::Pending, Hotel::factory()->pending()->create()->derivedStatus());
        $this->assertSame(HotelStatus::Paused, Hotel::factory()->paused()->create()->derivedStatus());
        $this->assertSame(HotelStatus::Archived, Hotel::factory()->archived()->create()->derivedStatus());
    }

    // ------------------------------------------------------- days remaining

    public function test_a_paused_hotel_has_no_days_remaining_because_the_clock_is_frozen()
    {
        $this->assertNull(Hotel::factory()->paused()->create()->daysRemaining());
    }

    public function test_days_remaining_counts_whole_calendar_days_regardless_of_time_of_day()
    {
        Date::setTestNow(Date::parse('2026-09-18 23:59:00'));
        $late = Hotel::factory()->create(['contract_ends_on' => '2026-09-28']);

        Date::setTestNow(Date::parse('2026-09-18 00:01:00'));
        $early = Hotel::factory()->create(['contract_ends_on' => '2026-09-28']);

        // Both sides go through startOfDay(), so the time of day never shifts
        // the count, and a test around midnight cannot flake (invariant 8).
        $this->assertSame(10, $late->daysRemaining());
        $this->assertSame(10, $early->daysRemaining());

        Date::setTestNow();
    }

    // ---------------------------------------------------------- seat counts

    public function test_only_active_employee_accounts_occupy_a_seat()
    {
        [$hotel, $quota] = $this->hotelWithQuota(allowed: 10);

        $this->employees($hotel, $quota, 3);
        $this->employees($hotel, $quota, 2, AccountStatus::Inactive);
        User::factory()->manager()->create([
            'hotel_id' => $hotel->id,
            'department_id' => $quota->department_id,
        ]);

        // Inactive staff free their seat; managers never took one.
        $this->assertSame(3, $hotel->usedSeats());
        $this->assertSame(3, $quota->usedSeats());
    }

    public function test_the_eager_loaded_counts_agree_with_the_live_ones()
    {
        [$hotel, $quota] = $this->hotelWithQuota(allowed: 10);
        $this->employees($hotel, $quota, 4);

        $loaded = Hotel::query()->withSeatCounts()->findOrFail($hotel->id);

        $this->assertSame(4, $loaded->usedSeats());
        $this->assertSame(10, $loaded->allowedSeats());
        $this->assertSame(1, $loaded->departmentCount());
    }

    public function test_department_count_is_the_number_of_quota_rows()
    {
        $hotel = Hotel::factory()->create();

        foreach (['Reception', 'Spa', 'Kitchen'] as $name) {
            SeatQuota::factory()->create([
                'hotel_id' => $hotel->id,
                'department_id' => Department::factory()->create(['name' => $name, 'slug' => strtolower($name)])->id,
            ]);
        }

        $this->assertSame(3, $hotel->departmentCount());
    }

    // ------------------------------------------------------- capacity state

    public function test_capacity_reads_available_full_and_over()
    {
        $this->assertSame(CapacityState::Available, CapacityState::compare(9, 10));
        $this->assertSame(CapacityState::Full, CapacityState::compare(10, 10));
        $this->assertSame(CapacityState::Over, CapacityState::compare(11, 10));
    }

    public function test_a_hotel_with_no_quotas_reads_as_available_not_full()
    {
        // 0 used and 0 allowed compare as equal. Without the guard a hotel
        // that simply has no departments yet would display as At Capacity.
        $hotel = Hotel::factory()->create();

        $this->assertSame(0, $hotel->allowedSeats());
        $this->assertSame(CapacityState::Available, $hotel->capacityState());
    }

    public function test_a_quota_lowered_below_usage_reads_as_over_and_touches_no_account()
    {
        [$hotel, $quota] = $this->hotelWithQuota(allowed: 10);
        $this->employees($hotel, $quota, 8);

        $quota->update(['allowed_seats' => 5]);

        $this->assertSame(CapacityState::Over, $quota->fresh()?->capacityState());
        // Lowering a quota never deactivates anybody (SUB-03).
        $this->assertSame(8, User::where('status', AccountStatus::Active)->where('hotel_id', $hotel->id)->count());
    }

    // --------------------------------------------------------------- access

    public function test_only_an_active_hotel_inside_its_contract_allows_access()
    {
        $this->assertTrue(Hotel::factory()->active(45)->create()->allowsAccess());
        $this->assertTrue(Hotel::factory()->expiring(3)->create()->allowsAccess());

        $this->assertFalse(Hotel::factory()->pending()->create()->allowsAccess());
        $this->assertFalse(Hotel::factory()->paused()->create()->allowsAccess());
        $this->assertFalse(Hotel::factory()->archived()->create()->allowsAccess());
        $this->assertFalse(Hotel::factory()->ended()->create()->allowsAccess());
    }

    // -------------------------------------------------------------- helpers

    /**
     * @return array{0: Hotel, 1: SeatQuota}
     */
    private function hotelWithQuota(int $allowed): array
    {
        $hotel = Hotel::factory()->create();
        $quota = SeatQuota::factory()->create([
            'hotel_id' => $hotel->id,
            'allowed_seats' => $allowed,
        ]);

        return [$hotel, $quota];
    }

    private function employees(Hotel $hotel, SeatQuota $quota, int $count, AccountStatus $status = AccountStatus::Active): void
    {
        User::factory()->employee()->count($count)->create([
            'hotel_id' => $hotel->id,
            'department_id' => $quota->department_id,
            'status' => $status,
        ]);
    }
}
