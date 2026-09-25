<?php

namespace Tests\Feature\Admin\Employees;

use App\Enums\AccountStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\ReminderTemplate;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The tenant boundary on every employee route, by GET and by form POST
 * (ROLE-02, SEC-01, SUB-02, REP-08; spec 0003 Part D).
 *
 * A manager reads and edits their own hotel's employees only, never another
 * hotel's, never a manager or a Super Admin, never past the seat quota. A
 * refusal is a 403, never a 404 and never a redirect.
 */
class EmployeeCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $mine;

    private Hotel $theirs;

    private Department $reception;

    private User $manager;

    private User $myEmployee;

    private User $theirEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Queue::fake();

        $this->mine = Hotel::factory()->create(['name' => 'Mine']);
        $this->theirs = Hotel::factory()->create(['name' => 'Theirs']);
        $this->reception = Department::factory()->create(['name' => 'Reception']);
        SeatQuota::factory()->create(['hotel_id' => $this->mine->id, 'department_id' => $this->reception->id, 'allowed_seats' => 2]);
        SeatQuota::factory()->create(['hotel_id' => $this->theirs->id, 'department_id' => $this->reception->id, 'allowed_seats' => 5]);
        ReminderTemplate::factory()->create(['name' => 'Training comeback reminder']);

        $this->manager = User::factory()->manager()->create(['hotel_id' => $this->mine->id, 'status' => AccountStatus::Active]);
        $this->myEmployee = $this->employeeOf($this->mine, 'mine.one');
        $this->theirEmployee = $this->employeeOf($this->theirs, 'theirs.one');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function rowRoutes(): array
    {
        return [
            'update' => ['patch', 'employees.update', ['name' => 'X', 'username' => 'x.name', 'status' => 'active']],
            'activate' => ['post', 'employees.activate', []],
            'deactivate' => ['post', 'employees.deactivate', []],
            'reset password' => ['post', 'employees.reset-password', []],
        ];
    }

    public function test_a_manager_sees_only_their_own_hotel()
    {
        $this->actingAs($this->manager)
            ->get(route('employees'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 1)
                ->where('employees.0.id', $this->myEmployee->id)
                ->where('stats.0.value', 1)
                ->has('filters.hotels', 2)
                ->where('filters.hotels.1.value', (string) $this->mine->id)
                ->has('createForm.hotels', 1)
            );

        // Asking for the other hotel by query string is a boundary, not a
        // filter (ROLE-02).
        $this->actingAs($this->manager)
            ->get(route('employees', ['hotel' => $this->theirs->id]))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('employees.export', ['format' => 'csv', 'hotel' => $this->theirs->id]))
            ->assertForbidden();

        // Their own export never contains the other hotel's staff.
        $csv = $this->actingAs($this->manager)
            ->get(route('employees.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('mine.one', $csv);
        $this->assertStringNotContainsString('theirs.one', $csv);
    }

    public function test_a_manager_cannot_touch_another_hotels_employee()
    {
        foreach (self::rowRoutes() as [$verb, $name, $payload]) {
            $this->actingAs($this->manager)
                ->{$verb}(route($name, $this->theirEmployee), $this->payload($payload, $this->theirs))
                ->assertForbidden();
        }

        $this->actingAs($this->manager)
            ->post(route('employees.bulk'), ['action' => 'deactivate', 'ids' => [$this->myEmployee->id, $this->theirEmployee->id]])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('employees.remind'), ['ids' => [$this->theirEmployee->id]])
            ->assertForbidden();

        $this->assertSame(0, AuditLog::count());
        $this->assertSame(AccountStatus::Active, $this->myEmployee->fresh()?->status);
        $this->assertSame('theirs.one', $this->theirEmployee->fresh()?->username);
    }

    public function test_a_manager_cannot_create_or_move_an_account_into_another_hotel()
    {
        $this->actingAs($this->manager)
            ->post(route('employees.store'), $this->payload([
                'name' => 'New', 'username' => 'new.one', 'password' => 'Secret-Pass-12', 'status' => 'active',
            ], $this->theirs))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->patch(route('employees.update', $this->myEmployee), $this->payload([
                'name' => 'Moved', 'username' => 'mine.one', 'status' => 'active',
            ], $this->theirs))
            ->assertForbidden();

        $this->assertSame($this->mine->id, $this->myEmployee->fresh()?->hotel_id);
    }

    public function test_a_manager_cannot_touch_managers_or_super_admins()
    {
        $otherManager = User::factory()->manager()->create(['hotel_id' => $this->mine->id]);
        $owner = User::factory()->superAdmin()->create(['hotel_id' => $this->mine->id]);

        foreach ([$otherManager, $owner, $this->manager] as $target) {
            foreach (self::rowRoutes() as [$verb, $name, $payload]) {
                $this->actingAs($this->manager)
                    ->{$verb}(route($name, $target), $this->payload($payload, $this->mine))
                    ->assertForbidden();
            }
        }

        $this->actingAs($this->manager)
            ->get(route('employees'))
            ->assertInertia(fn (Assert $page) => $page->has('employees', 1));
    }

    public function test_a_manager_cannot_add_employees_at_all()
    {
        // Adding an account is EmployeesCreate, which a manager does not hold
        // (client decision narrowing SUB-02): creating even in their own hotel
        // and quota is a 403, before any seat check. The seat limit itself is
        // covered for the Super Admin in EmployeeActionsTest.
        $this->actingAs($this->manager)
            ->post(route('employees.store'), $this->payload([
                'name' => 'Third', 'username' => 'mine.three', 'password' => 'Secret-Pass-12', 'status' => 'active',
            ], $this->mine))
            ->assertForbidden();

        $this->assertFalse(User::query()->where('username', 'mine.three')->exists());
    }

    public function test_a_manager_may_manage_their_own_employees()
    {
        $this->actingAs($this->manager)
            ->patch(route('employees.update', $this->myEmployee), $this->payload([
                'name' => 'Renamed', 'username' => 'mine.one', 'status' => 'active',
            ], $this->mine))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $this->myEmployee->fresh()?->name);

        $this->actingAs($this->manager)->post(route('employees.deactivate', $this->myEmployee))->assertRedirect();
        $this->assertSame(AccountStatus::Inactive, $this->myEmployee->fresh()?->status);

        $this->actingAs($this->manager)->post(route('employees.remind'), ['ids' => [$this->myEmployee->id]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, AuditLog::count());
    }

    public function test_a_guest_is_sent_to_login()
    {
        $this->post(route('employees.deactivate', $this->myEmployee))->assertRedirect(route('login'));
    }

    public function test_an_employee_is_refused_every_employee_route()
    {
        $this->actingAs($this->myEmployee)->get(route('employees'))->assertForbidden();
        $this->actingAs($this->myEmployee)->post(route('employees.store'), [])->assertForbidden();
        $this->actingAs($this->myEmployee)->get(route('employees.export', ['format' => 'csv']))->assertForbidden();

        foreach (self::rowRoutes() as [$verb, $name, $payload]) {
            $this->actingAs($this->myEmployee)->{$verb}(route($name, $this->myEmployee), $payload)->assertForbidden();
        }
    }

    // -------------------------------------------------------------- helpers

    private function employeeOf(Hotel $hotel, string $username): User
    {
        return User::factory()->employee()->create([
            'username' => $username,
            'email' => $username.'@hotel.dz',
            'hotel_id' => $hotel->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
            'email_consent_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payload(array $payload, Hotel $hotel): array
    {
        return $payload + ['hotel_id' => $hotel->id, 'department_id' => $this->reception->id];
    }
}
