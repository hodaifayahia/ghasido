<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\HotelPortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_the_super_admin_can_visit_the_dashboard()
    {
        // Only the platform owner gets the admin dashboard; everyone else
        // lands on the placeholder (spec 0001, AC-7).
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_renders_every_panel_from_the_seeded_portfolio()
    {
        $this->seed(HotelPortfolioSeeder::class);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats', 6)
                // The seven catalogue departments, in mockup order.
                ->has('trainingOverview.departments', 7)
                ->where('trainingOverview.departments.0.name', 'Reception')
                ->where('trainingOverview.departments.6.name', 'Technical Services')
                ->has('departmentProgress', 7)
                ->has('needsAttention', 3)
                ->where('needsAttention.0.key', 'inactive')
                ->where('needsAttention.1.key', 'notStarted')
                ->where('needsAttention.2.key', 'pretestFinished')
                ->has('recentActivity')
            );
    }

    public function test_training_totals_add_up_across_departments()
    {
        $this->seed(HotelPortfolioSeeder::class);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(function (Assert $page): void {
                /** @var array{completed: int, inProgress: int, notStarted: int} $all */
                $all = $page->toArray()['props']['trainingOverview']['all'];
                /** @var list<array{breakdown: array{completed: int, inProgress: int, notStarted: int}}> $departments */
                $departments = $page->toArray()['props']['trainingOverview']['departments'];
                /** @var list<array{key: string, value: int}> $stats */
                $stats = $page->toArray()['props']['stats'];

                // Every active employee of the portfolio's seven non archived
                // hotels sits in exactly one segment of the donut (176 seats
                // used, the Hotels screen's figure).
                $this->assertSame(176, $stats[2]['value']);
                $this->assertSame(176, $all['completed'] + $all['inProgress'] + $all['notStarted']);
                $this->assertSame($all['completed'] + $all['inProgress'], $stats[3]['value']);
                $this->assertSame($all['completed'], $stats[4]['value']);

                foreach (['completed', 'inProgress', 'notStarted'] as $segment) {
                    $this->assertSame($all[$segment], array_sum(array_column(array_column($departments, 'breakdown'), $segment)));
                }
            });
    }
}
