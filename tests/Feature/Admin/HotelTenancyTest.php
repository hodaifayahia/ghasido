<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use App\Policies\HotelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The tracer thread and the tenant boundary (spec 0002, AC-1, AC-10).
 *
 * One number read from the database proves every layer end to end:
 * migration, model, scope, policy, controller, Inertia prop. The rest of the
 * page stays sample data until the directory work lands.
 */
class HotelTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The page response is asserted as Inertia data; the asset manifest
        // is irrelevant to it and must not fail the test when Vite is down.
        $this->withoutVite();
    }

    // ------------------------------------------------------------ the thread

    public function test_total_hotels_is_read_from_the_database(): void
    {
        Hotel::factory()->count(3)->create();
        Hotel::factory()->pending()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('hotels'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Hotels')
                ->where('stats.0.key', 'totalHotels')
                ->where('stats.0.value', 4)
            );
    }

    public function test_archived_hotels_are_left_out_of_the_total(): void
    {
        Hotel::factory()->count(2)->create();
        Hotel::factory()->archived()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('hotels'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.0.value', 2));
    }

    // --------------------------------------------------------- the scope

    /**
     * The failure mode of a global scope that forgets the Super Admin is an
     * empty page rather than an error, so it is checked first and directly.
     */
    public function test_the_super_admin_sees_every_hotel_through_the_scope(): void
    {
        Hotel::factory()->count(3)->create();

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertSame(3, Hotel::count());
    }

    public function test_a_manager_sees_only_their_own_hotel_through_the_scope(): void
    {
        $mine = Hotel::factory()->create();
        Hotel::factory()->count(2)->create();

        $this->actingAs($this->managerOf($mine));

        $this->assertSame([$mine->id], Hotel::pluck('id')->all());
    }

    public function test_a_user_with_no_hotel_sees_nothing_rather_than_everything(): void
    {
        Hotel::factory()->count(2)->create();

        $this->actingAs(User::factory()->manager()->create(['hotel_id' => null]));

        $this->assertSame(0, Hotel::count());
    }

    public function test_seat_quotas_are_scoped_to_the_signed_in_hotel_too(): void
    {
        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->create();
        SeatQuota::factory()->create(['hotel_id' => $mine->id]);
        SeatQuota::factory()->create(['hotel_id' => $theirs->id]);

        $this->actingAs($this->managerOf($mine));

        $this->assertSame([$mine->id], SeatQuota::pluck('hotel_id')->all());
    }

    // ------------------------------------------------------ the boundary

    public function test_a_manager_can_open_their_own_hotel_screen(): void
    {
        // Hotel access is tenant-scoped: a manager can open the hotel they
        // belong to, but never receives platform-wide hotel access.
        $this->actingAs($this->managerOf(Hotel::factory()->create()))
            ->get(route('hotels'))
            ->assertOk();
    }

    public function test_the_policy_refuses_a_manager_another_hotels_record(): void
    {
        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->create();

        $manager = $this->managerOf($mine);

        // Holding the capability is not enough: the hotel has to be theirs.
        $this->assertTrue(Gate::forUser($manager)->allows('view', $mine));
        $this->assertFalse(Gate::forUser($manager)->allows('view', $theirs));
    }

    public function test_the_policy_refuses_an_employee_every_hotel_record(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);

        $this->assertFalse(Gate::forUser($employee)->allows('view', $hotel));
        $this->assertFalse(Gate::forUser($employee)->allows('update', $hotel));
        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', Hotel::class));
    }

    public function test_the_super_admin_passes_the_policy_on_any_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        $owner = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $hotel));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $hotel));
    }

    public function test_approving_is_a_capability_the_platform_owner_holds(): void
    {
        $owner = User::factory()->superAdmin()->create();

        // Whether the hotel is pending is a state question the service
        // answers with a 409 (HotelActionsTest); the policy only asks who.
        $policy = new HotelPolicy;

        $this->assertTrue($owner->can(Permission::HotelsApprove->value));
        $this->assertTrue(Gate::forUser($owner)->allows('approve', Hotel::factory()->pending()->create()));
        $this->assertFalse($policy->approve(
            $this->managerOf(Hotel::factory()->create()),
            Hotel::factory()->pending()->create(),
        ));
    }

    private function managerOf(Hotel $hotel): User
    {
        return User::factory()->manager()->create([
            'hotel_id' => $hotel->id,
            'status' => AccountStatus::Active,
        ]);
    }
}
