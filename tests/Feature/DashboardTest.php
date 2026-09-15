<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_renders_every_panel()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats', 6)
                ->has('trainingOverview.departments', 7)
                ->has('departmentProgress', 7)
                ->has('needsAttention', 3)
                ->has('recentActivity', 5)
            );
    }

    public function test_training_totals_add_up_across_departments()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trainingOverview.all', [
                    'completed' => 18,
                    'inProgress' => 44,
                    'notStarted' => 18,
                ])
                ->where('stats.2.value', 80)
                ->where('stats.3.value', 62)
                ->where('stats.3.detail', '77.5%')
            );
    }
}
