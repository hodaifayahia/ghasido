<?php

namespace Tests\Feature\Admin\Hotels;

use App\Enums\AccountStatus;
use App\Models\Attempt;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HotelDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_view_opens_a_hotel_detail_page_with_employee_activity(): void
    {
        $hotel = Hotel::factory()->active()->create(['name' => 'Palm Court']);
        $department = Department::factory()->create(['name' => 'Reception']);

        $active = User::factory()->employee()->forHotel($hotel, $department)->create([
            'name' => 'Active Learner',
            'last_activity_at' => now()->subDays(2),
            'training_started_at' => now()->subDay(),
        ]);
        User::factory()->employee()->forHotel($hotel, $department)->create([
            'name' => 'Inactive Learner',
            'status' => AccountStatus::Inactive,
            'last_activity_at' => now()->subDays(40),
        ]);

        Attempt::factory()->create([
            'user_id' => $active->id,
            'time_taken_ms' => 120000,
        ]);
        RoleplayAttempt::factory()->completed()->create([
            'user_id' => $active->id,
            'duration_ms' => 180000,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('hotels.show', $hotel))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/HotelDetails')
                ->where('hotel.name', 'Palm Court')
                ->where('summary.totalEmployees', 2)
                ->where('summary.activeUsers', 1)
                ->where('summary.totalTimeSpent', '5 min')
                ->where('activity.activeThisWeek', 1)
                ->where('activity.inactive', 1)
                ->has('employees', 2)
                ->where('employees.0.name', 'Active Learner')
                ->where('employees.0.activityStatus', 'activeThisWeek')
                ->where('employees.0.trainingStatus', 'in_progress')
                ->where('employees.0.timeSpent', '5 min')
                ->where('employees.1.activityStatus', 'inactive')
            );
    }

    public function test_a_manager_cannot_open_another_hotels_detail_page(): void
    {
        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $mine->id]);

        $this->actingAs($manager)
            ->get(route('hotels.show', $theirs))
            ->assertForbidden();
    }
}
