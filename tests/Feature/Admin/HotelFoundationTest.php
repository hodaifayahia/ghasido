<?php

namespace Tests\Feature\Admin;

use App\Concerns\DepartmentRules;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Database\Seeders\HotelPortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Tests\TestCase;

/**
 * The audit log, the department rule and the seeded portfolio
 * (spec 0002, AC-3, AC-9, AC-16, AC-19).
 */
class HotelFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ----------------------------------------------------------- audit log

    public function test_a_change_is_recorded_with_before_and_after_values()
    {
        $owner = User::factory()->superAdmin()->create();
        $this->actingAs($owner);

        $hotel = Hotel::factory()->create(['city' => 'Algiers']);
        $hotel->city = 'Oran';

        $log = AuditLog::record($hotel, 'hotel.updated');
        $hotel->save();

        $this->assertSame($owner->id, $log->actor_id);
        $this->assertSame('hotel.updated', $log->action);
        $this->assertSame($hotel->getMorphClass(), $log->auditable_type);
        $this->assertSame($hotel->id, $log->auditable_id);
        $this->assertSame(
            ['from' => 'Algiers', 'to' => 'Oran'],
            $log->changes['attributes']['city'] ?? null,
        );
    }

    public function test_extra_context_travels_with_the_diff()
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $hotel = Hotel::factory()->pending()->create();

        $log = AuditLog::record($hotel, 'hotel.rejected', ['reason' => 'Duplicate signup']);

        $this->assertSame('Duplicate signup', $log->changes['reason'] ?? null);
    }

    public function test_a_batch_quota_change_is_one_row_in_its_own_shape()
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $hotel = Hotel::factory()->create();

        $log = AuditLog::recordQuotaChange($hotel, 'hotel.seats_updated', [
            3 => ['from' => 8, 'to' => 6],
            5 => ['from' => 10, 'to' => 12],
        ]);

        $this->assertSame(1, AuditLog::count());
        $this->assertSame(['from' => 8, 'to' => 6], $log->changes['quotas'][3] ?? null);
        $this->assertSame(['from' => 10, 'to' => 12], $log->changes['quotas'][5] ?? null);
    }

    // ---------------------------------------------------- department rule

    public function test_a_global_department_slug_must_be_unique_among_global_rows()
    {
        Department::factory()->create(['slug' => 'reception', 'hotel_id' => null]);

        $this->assertTrue($this->slugFails('reception', null));
        $this->assertFalse($this->slugFails('spa', null));
    }

    public function test_a_hotel_may_reuse_a_global_slug_for_its_own_department()
    {
        Department::factory()->create(['slug' => 'reception', 'hotel_id' => null]);
        $hotel = Hotel::factory()->create();

        $this->assertFalse($this->slugFails('reception', $hotel->id));
    }

    public function test_a_slug_must_be_unique_within_one_hotel()
    {
        $hotel = Hotel::factory()->create();
        Department::factory()->forHotel($hotel->id)->create(['slug' => 'marina']);

        $this->assertTrue($this->slugFails('marina', $hotel->id));
        $this->assertFalse($this->slugFails('marina', Hotel::factory()->create()->id));
    }

    // ------------------------------------------------------------- seeder

    public function test_the_portfolio_seeds_every_state_with_real_seat_counts()
    {
        $this->seed(HotelPortfolioSeeder::class);

        $hotels = Hotel::withoutGlobalScopes()->withSeatCounts()->get()->keyBy('name');

        $this->assertCount(8, $hotels);

        // The six hotels the page shows today, with their real numbers.
        $this->assertSame([58, 64, 7], $this->figures($hotels["La Gazelle d'Or"]));
        $this->assertSame([50, 48, 6], $this->figures($hotels['Hotel El Aurassi']));
        $this->assertSame([36, 36, 6], $this->figures($hotels['Sheraton Club des Pins']));
        $this->assertSame([18, 20, 5], $this->figures($hotels['Azure Resort & Spa']));
        $this->assertSame([14, 18, 4], $this->figures($hotels['Desert Bloom Suites']));
        $this->assertSame([0, 12, 4], $this->figures($hotels['Sunrise Dunes Hotel']));

        // The seat counts are real accounts, not a stored number.
        $this->assertSame(176, User::whereNotNull('hotel_id')->count());
    }

    public function test_the_seeded_buckets_sum_to_total_hotels()
    {
        $this->seed(HotelPortfolioSeeder::class);

        $statuses = Hotel::withoutGlobalScopes()->notArchived()->get()
            ->countBy(fn (Hotel $hotel): string => $hotel->derivedStatus()->value);

        // Mutually exclusive buckets that add up to the total (AC-3).
        $this->assertSame(
            Hotel::withoutGlobalScopes()->notArchived()->count(),
            $statuses->sum(),
        );
        $this->assertSame(
            ['active' => 2, 'ended' => 1, 'expiring' => 2, 'paused' => 1, 'pending' => 1],
            $statuses->sortKeys()->all(),
        );
    }

    public function test_the_seeder_is_safe_to_run_twice()
    {
        $this->seed(HotelPortfolioSeeder::class);
        $this->seed(HotelPortfolioSeeder::class);

        $this->assertSame(8, Hotel::withoutGlobalScopes()->count());
        $this->assertSame(7, Department::count());
        $this->assertSame(176, User::whereNotNull('hotel_id')->count());

        // One quota row per hotel and department, not two: 7+6+6+5+4+4+3+2.
        // A plain count rather than COUNT(DISTINCT a, b), which is MySQL only
        // and fails on the SQLite CI runs.
        $this->assertSame(37, SeatQuota::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------- helpers

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function figures(Hotel $hotel): array
    {
        return [$hotel->usedSeats(), $hotel->allowedSeats(), $hotel->departmentCount()];
    }

    private function slugFails(string $slug, ?int $hotelId): bool
    {
        $rules = new class
        {
            use DepartmentRules;

            public function for(?int $hotelId): Unique
            {
                return $this->departmentSlugRule($hotelId);
            }
        };

        return Validator::make(
            ['slug' => $slug],
            ['slug' => [$rules->for($hotelId)]],
        )->fails();
    }
}
