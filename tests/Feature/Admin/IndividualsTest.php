<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\IndividualSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Individual subscribers: learners with no hotel, on their own access
 * window (user request 2026-09-25; wiring restored 2026-09-26).
 */
class IndividualsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_super_admin_sees_the_individuals_page(): void
    {
        $individual = User::factory()->employee()->create(['hotel_id' => null]);
        IndividualSubscription::factory()->for($individual)->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('individuals'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Individuals')
                ->has('individuals', 1)
                ->where('individuals.0.id', $individual->id));
    }

    public function test_the_super_admin_adds_an_individual_with_several_departments(): void
    {
        $main = Department::factory()->create();
        $second = Department::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('individuals.store'), [
                'name' => 'Samira Benali',
                'username' => 'samira.b',
                'password' => 'a-strong-password',
                'department_ids' => [$main->id, $second->id],
                'status' => 'active',
                'ai_points_allocated' => 3000,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addMonth()->toDateString(),
                'ai_enabled' => true,
                'voice_enabled' => false,
                'ai_action_points' => 50,
                'voice_points_per_10_minutes' => 100,
                'price_usd' => 24.5,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::query()->where('username', 'samira.b')->firstOrFail();
        $this->assertSame(24.5, $user->individualSubscription?->price_usd);

        $this->assertNull($user->hotel_id);
        $this->assertSame($main->id, $user->department_id);
        $this->assertTrue($user->isIndividual());
        $this->assertSame([$main->id, $second->id], $user->individualSubscription?->departmentIds());
    }

    public function test_a_manager_may_not_open_or_add_individuals(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('individuals'))->assertForbidden();
        $this->actingAs($manager)->post(route('individuals.store'), [])->assertForbidden();
    }

    public function test_a_hotel_employee_cannot_be_edited_as_an_individual(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('individuals.toggle', $employee))
            ->assertNotFound();
    }

    public function test_an_individual_outside_their_window_cannot_sign_in_and_keeps_their_data(): void
    {
        $individual = User::factory()->employee()->create(['hotel_id' => null, 'username' => 'ended.one']);
        IndividualSubscription::factory()->for($individual)->ended()->create();

        $this->post(route('login.store'), ['email' => 'ended.one', 'password' => 'password'])
            ->assertSessionHasErrors();

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $individual->id]);
    }

    public function test_an_individual_inside_their_window_signs_in(): void
    {
        $individual = User::factory()->employee()->create(['hotel_id' => null, 'username' => 'active.one']);
        IndividualSubscription::factory()->for($individual)->create();

        $this->post(route('login.store'), ['email' => 'active.one', 'password' => 'password']);

        $this->assertAuthenticatedAs($individual);
    }
}
