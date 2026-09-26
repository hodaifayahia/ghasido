<?php

namespace Tests\Feature\Admin\Departments;

use App\Enums\DepartmentStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every write on a department: create (shared or hotel scoped), edit,
 * archive and restore, each with exactly one audit row and nothing deleted
 * (spec 0003 Part D; SEC-06, ADM-03, DATA-10).
 */
class DepartmentActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
    }

    // -------------------------------------------------------------- create

    public function test_creating_a_shared_department_lands_in_the_catalogue_with_an_audit_row()
    {
        Department::factory()->create(['position' => 4]);

        $this->actingAs($this->owner)
            ->from(route('departments'))
            ->post(route('departments.store'), [
                'name' => 'Concierge',
                'focus' => 'Directions, bookings and local tips.',
                'scope' => 'shared',
                'status' => 'review',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $department = Department::query()->where('slug', 'concierge')->firstOrFail();

        $this->assertNull($department->hotel_id);
        $this->assertSame('Concierge', $department->name);
        $this->assertSame('Directions, bookings and local tips.', $department->focus);
        $this->assertSame(DepartmentStatus::Review, $department->status);
        $this->assertTrue($department->is_active);
        // Appended to the end of the shared catalogue.
        $this->assertSame(5, $department->position);
        $this->assertOneAuditRow($department, 'department.created');

        // The redirect selects the new row in the sidebar.
        $this->actingAs($this->owner)
            ->post(route('departments.store'), ['name' => 'Valet', 'scope' => 'shared', 'status' => 'active'])
            ->assertRedirect(route('departments', ['department' => Department::query()->where('slug', 'valet')->value('id')]));
    }

    public function test_creating_a_hotel_specific_department_binds_it_to_that_hotel()
    {
        $hotel = Hotel::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('departments.store'), [
                'name' => 'Beach Club',
                'scope' => 'hotel',
                'hotel_id' => $hotel->id,
                'status' => 'draft',
            ])
            ->assertSessionHasNoErrors();

        $department = Department::query()->where('slug', 'beach-club')->firstOrFail();

        $this->assertSame($hotel->id, $department->hotel_id);
        $this->assertSame(0, $department->position);
    }

    public function test_the_slug_is_unique_within_its_catalogue_only()
    {
        $hotel = Hotel::factory()->create();
        Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);

        // Same name in the shared catalogue: refused, with the error beside
        // the slug the name derives into.
        $this->actingAs($this->owner)
            ->from(route('departments'))
            ->post(route('departments.store'), ['name' => 'Reception', 'scope' => 'shared', 'status' => 'active'])
            ->assertRedirect(route('departments'))
            ->assertSessionHasErrors(['slug' => 'A department with this name already exists in that catalogue.']);

        // Same name inside one hotel: allowed (the composite unique holds).
        $this->actingAs($this->owner)
            ->post(route('departments.store'), ['name' => 'Reception', 'scope' => 'hotel', 'hotel_id' => $hotel->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Department::query()->where('slug', 'reception')->count());
    }

    public function test_bad_input_is_refused_with_errors_beside_the_fields()
    {
        $this->actingAs($this->owner)
            ->from(route('departments'))
            ->post(route('departments.store'), [
                'name' => '',
                'focus' => str_repeat('x', 256),
                'scope' => 'hotel',
                'status' => 'nope',
            ])
            ->assertRedirect(route('departments'))
            ->assertSessionHasErrors(['name', 'focus', 'hotel_id', 'status']);

        $this->assertSame(0, Department::count());
        $this->assertSame(0, AuditLog::count());
    }

    // ---------------------------------------------------------------- edit

    public function test_editing_writes_the_diff_to_the_audit_row_and_keeps_the_scope()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->forHotel($hotel->id)->create(['name' => 'Spa', 'slug' => 'spa', 'focus' => 'Old']);

        $this->actingAs($this->owner)
            ->patch(route('departments.update', $department), [
                'name' => 'Spa & Wellness',
                'focus' => 'Spa welcome, treatment briefing and product upsell.',
                'status' => 'review',
                // Ignored: the scope is fixed at creation.
                'scope' => 'shared',
                'hotel_id' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $department->refresh();

        $this->assertSame('Spa & Wellness', $department->name);
        $this->assertSame('spa-wellness', $department->slug);
        $this->assertSame(DepartmentStatus::Review, $department->status);
        $this->assertSame($hotel->id, $department->hotel_id);

        $audit = $this->assertOneAuditRow($department, 'department.updated');
        $this->assertEquals(['from' => 'Old', 'to' => 'Spa welcome, treatment briefing and product upsell.'], $audit->changes['attributes']['focus'] ?? null);
        $this->assertEquals(['from' => 'Spa', 'to' => 'Spa & Wellness'], $audit->changes['attributes']['name'] ?? null);
    }

    public function test_editing_a_department_to_its_own_name_is_not_a_duplicate()
    {
        $department = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);

        $this->actingAs($this->owner)
            ->patch(route('departments.update', $department), ['name' => 'Reception', 'focus' => 'x', 'status' => 'active'])
            ->assertSessionHasNoErrors();
    }

    // -------------------------------------------------------------- toggle

    public function test_archiving_switches_the_department_off_and_deletes_nothing()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception']);
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => $department->id, 'allowed_seats' => 4]);
        $employee = User::factory()->employee()->forHotel($hotel, $department)->create();

        $this->actingAs($this->owner)
            ->post(route('departments.toggle', $department))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $department->refresh();

        $this->assertFalse($department->is_active);
        $this->assertOneAuditRow($department, 'department.archived');
        $this->assertSame($department->id, $employee->fresh()?->department_id);
        $this->assertSame(1, SeatQuota::withoutGlobalScopes()->where('department_id', $department->id)->count());

        // And back.
        $this->actingAs($this->owner)
            ->post(route('departments.toggle', $department))
            ->assertRedirect();

        $this->assertTrue($department->fresh()?->is_active);
        $this->assertSame(1, AuditLog::query()->where('action', 'department.restored')->count());
        $this->assertSame(2, AuditLog::count());
    }

    // ------------------------------------------------------------- helpers

    private function assertOneAuditRow(Department $department, string $action): AuditLog
    {
        $rows = AuditLog::query()
            ->where('auditable_type', $department->getMorphClass())
            ->where('auditable_id', $department->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($action, $rows[0]?->action);
        $this->assertSame($this->owner->id, $rows[0]?->actor_id);

        return $rows[0];
    }
}
