<?php

namespace Tests\Feature\Admin\Hotels;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The tenant boundary on every hotel route, by GET and by form POST
 * (spec 0002, AC-10; ROLE-02, SEC-01).
 *
 * A refusal is a 403, never a 404 and never a redirect, so a blocked user can
 * tell a boundary from a broken link.
 */
class HotelCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function writeRoutes(): array
    {
        return [
            'update' => ['patch', 'hotels.update', ['name' => 'X', 'city' => 'Y', 'manager_name' => 'Z', 'manager_email' => 'z@example.com', 'contract_starts_on' => '2026-10-01', 'contract_ends_on' => '2026-12-01']],
            'approve' => ['post', 'hotels.approve', []],
            'reject' => ['post', 'hotels.reject', ['reason' => 'no']],
            'archive' => ['post', 'hotels.archive', []],
            'contract' => ['patch', 'hotels.contract', ['contract_ends_on' => '2027-01-01']],
            'pause' => ['post', 'hotels.pause', []],
            'resume' => ['post', 'hotels.resume', []],
            'seat quotas' => ['put', 'hotels.seat-quotas', ['quotas' => []]],
        ];
    }

    public function test_a_manager_without_write_capability_can_read_but_not_change_hotels()
    {
        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->pending()->create();
        $manager = $this->managerOf($mine);

        $this->actingAs($manager)->get(route('hotels'))->assertOk();
        $this->actingAs($manager)->post(route('hotels.store'), [])->assertForbidden();

        foreach (self::writeRoutes() as [$verb, $name, $payload]) {
            $this->actingAs($manager)->{$verb}(route($name, $theirs), $payload)->assertForbidden();
            $this->actingAs($manager)->{$verb}(route($name, $mine), $payload)->assertForbidden();
        }

        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_manager_holding_the_capability_still_cannot_touch_another_hotel()
    {
        // Grant every hotel capability to the manager ROLE (never the user),
        // so the ownership branch of the policy is what refuses.
        RoleModel::findByName(Role::Manager->value)->givePermissionTo([
            Permission::HotelsView->value,
            Permission::HotelsManage->value,
            Permission::HotelsApprove->value,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mine = Hotel::factory()->create(['name' => 'Mine']);
        $theirs = Hotel::factory()->pending()->create(['name' => 'Theirs']);
        $manager = $this->managerOf($mine);

        // By direct URL: the directory shows only their own hotel, and the
        // other hotel's record is a 403, not a 404 (the route binding skips
        // the tenant scope so the policy gets to answer).
        $this->actingAs($manager)
            ->get(route('hotels'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('hotels', 1)
                ->where('hotels.0.id', $mine->id)
                ->where('stats.0.value', 1)
            );

        $this->actingAs($manager)
            ->get(route('hotels', ['hotel' => $theirs->id]))
            ->assertInertia(fn (Assert $page) => $page->where('overview.id', $mine->id));

        // By form POST: every write on the other hotel is refused.
        foreach (self::writeRoutes() as $label => [$verb, $name, $payload]) {
            $this->actingAs($manager)
                ->{$verb}(route($name, $theirs), $payload)
                ->assertForbidden();
        }

        $this->assertSame(0, AuditLog::count());
        $this->assertSame('Theirs', $theirs->fresh()?->name);

        // Their own hotel they may edit.
        $this->actingAs($manager)
            ->patch(route('hotels.update', $mine), self::writeRoutes()['update'][2])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('X', $mine->fresh()?->name);
    }

    public function test_an_employee_is_refused_every_hotel_route()
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);

        $this->actingAs($employee)->get(route('hotels'))->assertForbidden();

        foreach (self::writeRoutes() as [$verb, $name, $payload]) {
            $this->actingAs($employee)->{$verb}(route($name, $hotel), $payload)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login()
    {
        $hotel = Hotel::factory()->create();

        $this->post(route('hotels.approve', $hotel))->assertRedirect(route('login'));
    }

    private function managerOf(Hotel $hotel): User
    {
        return User::factory()->manager()->create([
            'hotel_id' => $hotel->id,
            'status' => AccountStatus::Active,
        ]);
    }
}
