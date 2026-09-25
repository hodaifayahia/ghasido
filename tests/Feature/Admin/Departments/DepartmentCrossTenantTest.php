<?php

namespace Tests\Feature\Admin\Departments;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The tenant boundary on every department route, by GET and by form POST
 * (ROLE-02, SEC-01; spec 0003 Part D).
 *
 * A refusal is a 403, never a 404 and never a redirect, so a blocked user can
 * tell a boundary from a broken link.
 */
class DepartmentCrossTenantTest extends TestCase
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
            'update' => ['patch', 'departments.update', ['name' => 'X', 'focus' => 'Y', 'status' => 'active']],
            'toggle' => ['post', 'departments.toggle', []],
        ];
    }

    public function test_a_manager_may_read_but_never_write()
    {
        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->create();
        $shared = Department::factory()->create();
        $own = Department::factory()->forHotel($mine->id)->create();
        $foreign = Department::factory()->forHotel($theirs->id)->create();
        $manager = $this->managerOf($mine);

        $this->actingAs($manager)->get(route('departments'))->assertOk();

        $this->actingAs($manager)
            ->post(route('departments.store'), ['name' => 'New', 'scope' => 'hotel', 'hotel_id' => $mine->id, 'status' => 'active'])
            ->assertForbidden();

        foreach ([$shared, $own, $foreign] as $department) {
            foreach (self::writeRoutes() as [$verb, $name, $payload]) {
                $this->actingAs($manager)->{$verb}(route($name, $department), $payload)->assertForbidden();
            }
        }

        $this->assertSame(0, AuditLog::count());
        $this->assertSame(3, Department::count());
    }

    public function test_a_manager_holding_the_capability_still_cannot_touch_the_catalogue_or_another_hotel()
    {
        // Grant the capability to the manager ROLE (never the user), so the
        // ownership branch of the policy is what refuses.
        RoleModel::findByName(Role::Manager->value)->givePermissionTo(Permission::DepartmentsManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->create();
        $shared = Department::factory()->create(['name' => 'Shared']);
        $own = Department::factory()->forHotel($mine->id)->create(['name' => 'Own']);
        $foreign = Department::factory()->forHotel($theirs->id)->create(['name' => 'Foreign']);
        $manager = $this->managerOf($mine);

        foreach ([$shared, $foreign] as $department) {
            foreach (self::writeRoutes() as [$verb, $name, $payload]) {
                $this->actingAs($manager)->{$verb}(route($name, $department), $payload)->assertForbidden();
            }
        }

        // Adding to the shared catalogue, or to another hotel, is refused by
        // validation: the form never offered those choices.
        $this->actingAs($manager)
            ->from(route('departments'))
            ->post(route('departments.store'), ['name' => 'Sneaky', 'scope' => 'shared', 'status' => 'active'])
            ->assertRedirect(route('departments'))
            ->assertSessionHasErrors(['scope']);

        $this->actingAs($manager)
            ->from(route('departments'))
            ->post(route('departments.store'), ['name' => 'Sneaky', 'scope' => 'hotel', 'hotel_id' => $theirs->id, 'status' => 'active'])
            ->assertRedirect(route('departments'))
            ->assertSessionHasErrors(['hotel_id']);

        $this->assertSame(0, AuditLog::count());
        $this->assertSame('Shared', $shared->fresh()?->name);
        $this->assertSame('Foreign', $foreign->fresh()?->name);

        // Their own hotel's department they may edit, toggle and add to.
        $this->actingAs($manager)
            ->patch(route('departments.update', $own), self::writeRoutes()['update'][2])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('X', $own->fresh()?->name);

        $this->actingAs($manager)->post(route('departments.toggle', $own))->assertRedirect();
        $this->assertFalse($own->fresh()?->is_active);

        $this->actingAs($manager)
            ->post(route('departments.store'), ['name' => 'Pool Bar', 'scope' => 'hotel', 'hotel_id' => $mine->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertSame($mine->id, Department::query()->where('slug', 'pool-bar')->value('hotel_id'));
        $this->assertSame(3, AuditLog::count());
    }

    public function test_an_employee_is_refused_every_department_route()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();
        $employee = User::factory()->employee()->forHotel($hotel, $department)->create();

        $this->actingAs($employee)->get(route('departments'))->assertForbidden();
        $this->actingAs($employee)->post(route('departments.store'), [])->assertForbidden();

        foreach (self::writeRoutes() as [$verb, $name, $payload]) {
            $this->actingAs($employee)->{$verb}(route($name, $department), $payload)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login()
    {
        $department = Department::factory()->create();

        $this->post(route('departments.toggle', $department))->assertRedirect(route('login'));
    }

    private function managerOf(Hotel $hotel): User
    {
        return User::factory()->manager()->create(['hotel_id' => $hotel->id]);
    }
}
