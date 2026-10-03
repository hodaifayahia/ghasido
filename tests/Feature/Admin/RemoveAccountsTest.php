<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client request 2026-10-02: delete an employee or a hotel so the email
 * and username can be used again. Deleting anonymises; answers stay
 * (DATA-10).
 */
class RemoveAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_deletes_an_employee_and_frees_their_username_and_email()
    {
        $this->withoutVite();
        $owner = User::factory()->superAdmin()->create();
        $hotel = Hotel::factory()->active()->create();
        $employee = User::factory()->employee()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Amine Ben Ali',
            'username' => 'amine.benali',
            'email' => 'amine@hotel.dz',
        ]);
        $attempt = RoleplayAttempt::factory()->create(['user_id' => $employee->id]);

        $this->actingAs($owner)->delete(route('employees.remove', $employee))->assertRedirect();

        $employee->refresh();
        $this->assertNotNull($employee->removed_at);
        $this->assertSame(AccountStatus::Inactive, $employee->status);
        $this->assertNull($employee->email);
        $this->assertNotSame('amine.benali', $employee->username);
        $this->assertNotSame('Amine Ben Ali', $employee->name);
        // The learning record stays.
        $this->assertDatabaseHas('roleplay_attempts', ['id' => $attempt->id, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.removed']);

        // Gone from the list.
        $this->actingAs($owner)->get(route('employees'))
            ->assertInertia(fn (Assert $page) => $page->where('employees', fn ($rows) => collect($rows)->doesntContain(fn ($row) => $row['id'] === $employee->id)));

        // The username and email are free again.
        User::factory()->employee()->create(['username' => 'amine.benali', 'email' => 'amine@hotel.dz', 'hotel_id' => $hotel->id]);
        $this->assertSame(1, User::query()->where('username', 'amine.benali')->count());
    }

    public function test_only_the_super_admin_deletes()
    {
        $hotel = Hotel::factory()->active()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);

        $this->actingAs($manager)->delete(route('employees.remove', $employee))->assertForbidden();
        $this->assertNull($employee->refresh()->removed_at);
    }

    public function test_an_archived_hotel_is_deleted_with_its_accounts()
    {
        $owner = User::factory()->superAdmin()->create();
        $active = Hotel::factory()->active()->create();
        $archived = Hotel::factory()->archived()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $archived->id, 'email' => 'x@hotel.dz']);

        // An active hotel must be archived first.
        $this->actingAs($owner)->delete(route('hotels.remove', $active))->assertSessionHasErrors('hotel');
        $this->assertNull($active->refresh()->removed_at);

        $this->actingAs($owner)->delete(route('hotels.remove', $archived))->assertRedirect(route('hotels'));

        $this->assertNotNull($archived->refresh()->removed_at);
        $this->assertNotNull($employee->refresh()->removed_at);
        $this->assertNull($employee->email);
    }
}
