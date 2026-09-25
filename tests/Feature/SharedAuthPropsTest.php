<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The role and the permission list reach Vue (spec 0001, AC-5, AC-7, AC-9).
 */
class SharedAuthPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_authenticated_response_carries_the_role_and_permissions()
    {
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', RoleEnum::Manager->value)
                ->where('auth.permissions', fn (Collection $permissions): bool => $permissions->sort()->values()->all()
                    === collect(RoleEnum::Manager->permissionNames())->sort()->values()->all())
            );
    }

    public function test_the_shared_user_carries_only_whitelisted_fields()
    {
        // No loaded roles/permissions relation and no research columns on
        // every response (PRIV-03; spec 0005 §1.9).
        $this->actingAs(User::factory()->employee()->create(['participant_code' => 'P-SECRET']))
            ->get(route('help'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', RoleEnum::Employee->value)
                ->missing('auth.user.roles')
                ->missing('auth.user.participant_code')
                ->missing('auth.user.cohort')
                ->has('auth.user.hotel_name')
                ->has('auth.user.department_name')
                ->etc()
            );
    }

    public function test_a_user_with_no_role_carries_a_null_role_and_no_permissions()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', null)
                ->where('auth.permissions', [])
            );
    }

    public function test_a_guest_page_still_renders_with_a_null_role_and_no_permissions()
    {
        // share() runs for guests too, so both reads are null safe and both
        // keys are always present (invariant 8).
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('auth.permissions', [])
            );
    }

    public function test_the_super_admin_sees_the_admin_dashboard()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->has('trainingOverview')
            );
    }

    public function test_a_manager_gets_the_placeholder_without_the_admin_figures()
    {
        // Absent from the payload, not hidden in the UI: a prop the page never
        // renders is still shipped to the browser (AC-7).
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Placeholder')
                ->missing('stats')
                ->missing('trainingOverview')
                ->missing('needsAttention')
                ->missing('recentActivity')
                ->has('title')
                ->has('body')
            );
    }

    public function test_a_user_with_no_role_can_sign_in_and_lands_on_the_placeholder()
    {
        // AC-9: no role is not a locked account, it is an empty one.
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Placeholder'));
    }
}
