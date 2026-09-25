<?php

namespace Tests\Feature\Admin\Hotels;

use App\Enums\HotelStatus;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Database\Seeders\HotelPortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The directory reads real rows: stat cards, rows, sidebar, search, both
 * derived filters and the pager (spec 0002, AC-1, AC-3, AC-4, AC-8, AC-11,
 * AC-18).
 */
class HotelDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // ---------------------------------------------------------- happy path

    public function test_every_figure_on_the_page_comes_from_the_seeded_portfolio()
    {
        $this->seed(HotelPortfolioSeeder::class);

        $this->index()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Hotels')
                // Eight seeded, one archived: 7 in the portfolio (AC-3).
                ->where('stats.0.value', 7)
                ->where('stats.1.key', 'activeContracts')
                ->where('stats.1.value', 2)
                ->where('stats.2.key', 'expiringSoon')
                ->where('stats.2.value', 2)
                ->where('stats.2.detail', 'Next 30 days')
                ->where('stats.3.key', 'pausedContracts')
                ->where('stats.3.value', 1)
                ->where('stats.4.key', 'usedSeats')
                ->where('stats.4.value', 176)
                ->where('stats.4.detail', '176 / 214 allocated')
                // Name order, id tiebreaker: the pending Marina Bay sits in the
                // middle, the archived Old Medina is left out (AC-3, AC-11).
                ->has('hotels', 7)
                ->where('hotels.0.name', 'Azure Resort & Spa')
                ->where('hotels.0.rank', 1)
                ->where('hotels.0.departments', 5)
                ->where('hotels.0.usedSeats', 18)
                ->where('hotels.0.totalSeats', 20)
                ->where('hotels.0.status', 'expiring')
                ->where('hotels.0.capacityState', 'available')
                ->where('hotels.1.name', 'Desert Bloom Suites')
                ->where('hotels.1.status', 'paused')
                ->where('hotels.1.contractEnd', 'Paused')
                ->where('hotels.1.daysRemaining', null)
                ->where('hotels.2.name', 'Hotel El Aurassi')
                ->where('hotels.2.capacityState', 'over')
                ->where('hotels.4.name', 'Marina Bay Algiers')
                ->where('hotels.4.status', 'pending')
                ->where('hotels.6.name', 'Sunrise Dunes Hotel')
                ->where('hotels.6.status', 'ended')
                ->where('hotels.6.daysRemaining', -18)
                ->where('pagination.from', 1)
                ->where('pagination.to', 7)
                ->where('pagination.total', 7)
                // The sidebar opens on the first row (AC-18).
                ->where('overview.name', 'Azure Resort & Spa')
                ->where('overview.employees', 18)
                ->where('overview.departments', 5)
                ->has('overview.quotas', 5)
                ->has('overview.seatCatalogue', 7)
            );
    }

    public function test_the_stat_buckets_sum_to_total_hotels()
    {
        $this->seed(HotelPortfolioSeeder::class);

        $stats = $this->index()->inertiaProps('stats');
        $byKey = collect($stats)->keyBy('key');

        $pending = Hotel::query()->withStatus(HotelStatus::Pending)->count();
        $ended = Hotel::query()->withStatus(HotelStatus::Ended)->count();

        $this->assertSame(
            $byKey['totalHotels']['value'],
            $pending + $byKey['activeContracts']['value'] + $byKey['expiringSoon']['value'] + $byKey['pausedContracts']['value'] + $ended,
        );
    }

    public function test_the_alert_sentences_follow_the_value_sourcing_rules()
    {
        $this->seed(HotelPortfolioSeeder::class);

        $gazelle = Hotel::query()->where('slug', 'la-gazelle-dor')->firstOrFail();
        $sheraton = Hotel::query()->where('slug', 'sheraton-club-des-pins')->firstOrFail();

        // Active, outside the window: no renewal sentence, but two departments
        // are full and one is over quota.
        $this->index(['hotel' => $gazelle->id])
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.name', "La Gazelle d'Or")
                ->where('overview.status', 'active')
                ->has('overview.alerts', 1)
                ->where('overview.alerts.0', 'Reception, Food Service and Marketing have reached their seat limits.')
            );

        // Expiring in 11 days: ceil(11 / 7) = 2 weeks, and every department is full.
        $this->index(['hotel' => $sheraton->id])
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.status', 'expiring')
                ->has('overview.alerts', 2)
                ->where('overview.alerts.0', 'Renewal follow-up recommended within the next 2 weeks.')
            );
    }

    public function test_the_expiring_caption_follows_the_configured_window()
    {
        config(['guesvia.hotels.expiring_within_days' => 14]);
        Hotel::factory()->active(20)->create();

        $this->index()->assertInertia(fn (Assert $page) => $page
            ->where('stats.1.value', 1)
            ->where('stats.2.value', 0)
            ->where('stats.2.detail', 'Next 14 days')
        );
    }

    // ---------------------------------------------------------- boundaries

    public function test_a_hotel_with_no_quotas_reads_as_available_showing_zero_over_zero()
    {
        Hotel::factory()->create(['name' => 'Empty Palace']);

        $this->index()->assertInertia(fn (Assert $page) => $page
            ->where('hotels.0.usedSeats', 0)
            ->where('hotels.0.totalSeats', 0)
            ->where('hotels.0.capacityState', 'available')
            ->where('hotels.0.departments', 0)
        );
    }

    public function test_an_empty_portfolio_renders_no_rows_and_no_overview()
    {
        $this->index()->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 0)
            ->where('overview', null)
            ->where('pagination.total', 0)
        );
    }

    // ------------------------------------------------------------- search

    public function test_search_covers_hotel_name_manager_name_and_city()
    {
        Hotel::factory()->create(['name' => 'Palm Court', 'manager_name' => 'Amel Zidane', 'city' => 'Annaba']);
        Hotel::factory()->create(['name' => 'Sea Breeze', 'manager_name' => 'Karim Ferhat', 'city' => 'Oran']);

        foreach (['Palm', 'Zidane', 'annaba'] as $term) {
            $this->index(['search' => $term])->assertInertia(fn (Assert $page) => $page
                ->has('hotels', 1)
                ->where('hotels.0.name', 'Palm Court')
                ->where('filters.search', $term)
            );
        }

        $this->index(['search' => 'nothing-matches'])->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 0)
            ->where('overview', null)
        );
    }

    // ------------------------------------------------------------ filters

    public function test_the_status_filter_selects_each_derived_status()
    {
        $active = Hotel::factory()->active(90)->create(['name' => 'A Active']);
        $expiring = Hotel::factory()->expiring(10)->create(['name' => 'B Expiring']);
        $ended = Hotel::factory()->ended(3)->create(['name' => 'C Ended']);
        $paused = Hotel::factory()->paused()->create(['name' => 'D Paused']);
        $pending = Hotel::factory()->pending()->create(['name' => 'E Pending']);
        $archived = Hotel::factory()->archived()->create(['name' => 'F Archived']);

        $expected = [
            'active' => $active,
            'expiring' => $expiring,
            'ended' => $ended,
            'paused' => $paused,
            'pending' => $pending,
            'archived' => $archived,
        ];

        foreach ($expected as $status => $hotel) {
            $this->index(['status' => $status])->assertInertia(fn (Assert $page) => $page
                ->has('hotels', 1)
                ->where('hotels.0.id', $hotel->id)
                ->where('hotels.0.status', $status)
                ->where('filters.status', $status)
            );
        }

        // The default view leaves archived hotels out (AC-3).
        $this->index()->assertInertia(fn (Assert $page) => $page->has('hotels', 5));
    }

    public function test_the_capacity_filter_compares_live_usage_against_the_summed_quota()
    {
        $department = Department::factory()->create();

        $available = $this->hotelWithSeats('Under', $department, allowed: 5, used: 3);
        $full = $this->hotelWithSeats('Full', $department, allowed: 4, used: 4);
        $over = $this->hotelWithSeats('Over', $department, allowed: 2, used: 3);
        $empty = Hotel::factory()->create(['name' => 'Empty']);

        $this->index(['capacity' => 'available'])->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 2)
            ->where('hotels.0.id', $empty->id)
            ->where('hotels.1.id', $available->id)
        );
        $this->index(['capacity' => 'full'])->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 1)
            ->where('hotels.0.id', $full->id)
        );
        $this->index(['capacity' => 'over'])->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 1)
            ->where('hotels.0.id', $over->id)
        );
    }

    public function test_search_filters_and_paging_compose()
    {
        config(['guesvia.hotels.per_page' => 2]);
        $department = Department::factory()->create();

        foreach (['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'] as $name) {
            $this->hotelWithSeats($name.' Riviera', $department, allowed: 4, used: 4);
        }
        $this->hotelWithSeats('Zeta Riviera', $department, allowed: 4, used: 1);
        Hotel::factory()->expiring(5)->create(['name' => 'Omega Riviera']);

        $this->index(['search' => 'Riviera', 'status' => 'active', 'capacity' => 'full', 'page' => 2])
            ->assertInertia(fn (Assert $page) => $page
                ->has('hotels', 2)
                ->where('hotels.0.name', 'Delta Riviera')
                ->where('hotels.0.rank', 3)
                ->where('pagination.total', 5)
                ->where('pagination.currentPage', 2)
                ->where('pagination.lastPage', 3)
                ->where('pagination.from', 3)
                ->where('pagination.to', 4)
                ->where('pagination.pages', [1, 2, 3])
                ->where('overview.name', 'Delta Riviera')
            );
    }

    // ------------------------------------------------------------ sidebar

    public function test_view_swaps_the_sidebar_to_the_requested_hotel()
    {
        Hotel::factory()->create(['name' => 'Aardvark Inn']);
        $second = Hotel::factory()->create(['name' => 'Zebra Lodge']);

        $this->index(['hotel' => $second->id])->assertInertia(fn (Assert $page) => $page
            ->where('overview.id', $second->id)
            ->where('overview.name', 'Zebra Lodge')
        );

        // An unknown id falls back to the first row rather than a blank card.
        $this->index(['hotel' => 999999])->assertInertia(fn (Assert $page) => $page
            ->where('overview.name', 'Aardvark Inn')
        );
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $query
     */
    private function index(array $query = []): TestResponse
    {
        return $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('hotels', $query));
    }

    private function hotelWithSeats(string $name, Department $department, int $allowed, int $used): Hotel
    {
        $hotel = Hotel::factory()->create(['name' => $name]);
        SeatQuota::factory()->create([
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
            'allowed_seats' => $allowed,
        ]);
        User::factory()->employee()->count($used)->forHotel($hotel, $department)->create();

        return $hotel;
    }
}
