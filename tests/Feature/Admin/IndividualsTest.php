<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\AiFeature;
use App\Enums\Role;
use App\Models\AiUsage;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\IndividualSubscription;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Individual subscribers (user request 2026-09-25): learners with no hotel,
 * each on their own access dates and AI configuration.
 */
class IndividualsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $this->department = Department::factory()->create(['name' => 'Reception', 'hotel_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Yasmine Kaci',
            'username' => 'yasmine.kaci',
            'email' => 'yasmine@example.com',
            'password' => 'Welcome2026!',
            'department_ids' => [$this->department->id],
            'status' => 'active',
            'ai_points_allocated' => 4000,
            'starts_on' => Date::today()->toDateString(),
            'ends_on' => Date::today()->addDays(30)->toDateString(),
            'ai_enabled' => true,
            'voice_enabled' => false,
            'daily_ai_turns' => 20,
            'ai_action_points' => 40,
            'voice_points_per_10_minutes' => 150,
            'price_dzd' => 6000,
            'price_usd' => 24.5,
            'payment_reference' => 'CCP 1234',
            'notes' => 'Front desk trainee',
            ...$overrides,
        ];
    }

    public function test_the_super_admin_adds_an_individual_with_their_own_configuration()
    {
        $this->actingAs($this->owner)
            ->from(route('individuals'))
            ->post(route('individuals.store'), $this->payload())
            ->assertRedirect(route('individuals'))
            ->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'yasmine.kaci')->firstOrFail();

        $this->assertNull($user->hotel_id);
        $this->assertSame($this->department->id, $user->department_id);
        $this->assertTrue($user->hasRole(Role::Employee->value));
        $this->assertSame(4000, $user->ai_points_allocated);
        $this->assertNotNull($user->participant_code);
        $this->assertTrue($user->isIndividual());

        $subscription = $user->individualSubscription;
        $this->assertNotNull($subscription);
        $this->assertFalse($subscription->voice_enabled);
        $this->assertSame(20, $subscription->daily_ai_turns);
        $this->assertSame(40, $subscription->ai_action_points);
        $this->assertSame(6000, $subscription->price_dzd);
        $this->assertSame(24.5, $subscription->price_usd);

        $this->actingAs($this->owner)
            ->get(route('individuals'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Individuals')
                ->where('stats.total', 1)
                ->where('individuals.0.username', 'yasmine.kaci')
                ->where('individuals.0.aiPoints', 4000)
                ->where('individuals.0.windowState', 'active')
            );
    }

    public function test_an_individual_is_edited_and_switched_off()
    {
        $user = $this->individual();

        $this->actingAs($this->owner)
            ->patch(route('individuals.update', $user), $this->payload([
                'username' => $user->username,
                'email' => null,
                'password' => '',
                'ai_points_allocated' => 9000,
                'ai_enabled' => false,
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(9000, $user->ai_points_allocated);
        $this->assertFalse($user->individualSubscription?->ai_enabled);

        $this->actingAs($this->owner)->post(route('individuals.toggle', $user))->assertSessionHasNoErrors();
        $this->assertSame(AccountStatus::Inactive, $user->fresh()?->status);
    }

    public function test_an_individual_studies_several_departments_and_switches_between_them()
    {
        $housekeeping = Department::factory()->create(['name' => 'Housekeeping', 'hotel_id' => null]);
        Course::factory()->published()->forDepartment($housekeeping->id)->create();
        Course::factory()->published()->forDepartment($this->department->id)->create();

        $this->actingAs($this->owner)
            ->post(route('individuals.store'), $this->payload(['department_ids' => [$housekeeping->id, $this->department->id]]))
            ->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'yasmine.kaci')->firstOrFail();
        $user->forceFill(['first_login_completed_at' => now()])->save();

        // The first one ticked is the main department.
        $this->assertSame($housekeeping->id, $user->department_id);
        $this->assertSame([$housekeeping->id, $this->department->id], $user->individualSubscription?->departmentIds());

        $this->actingAs($user)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trainingContext.currentDepartmentId', $housekeeping->id)
                ->has('trainingContext.departments', 2)
            );

        $this->actingAs($user)
            ->post(route('learn.training-department.update'), ['department_id' => $this->department->id])
            ->assertRedirect(route('learn.home'));

        $this->actingAs($user)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trainingContext.currentDepartmentId', $this->department->id)
            );

        // A department not on the subscription is refused.
        $other = Department::factory()->create(['hotel_id' => null]);
        $this->actingAs($user)
            ->post(route('learn.training-department.update'), ['department_id' => $other->id])
            ->assertSessionHasErrors('department_id');
    }

    public function test_a_single_department_individual_has_no_switcher()
    {
        $user = $this->individual();
        $user->forceFill(['first_login_completed_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page->where('trainingContext', null));

        $this->actingAs($user)
            ->post(route('learn.training-department.update'), ['department_id' => $this->department->id])
            ->assertForbidden();
    }

    public function test_the_department_must_be_from_the_shared_catalogue()
    {
        $hotelOnly = Department::factory()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->post(route('individuals.store'), $this->payload(['department_ids' => [$hotelOnly->id]]))
            ->assertSessionHasErrors('department_ids.0');

        $this->actingAs($this->owner)
            ->post(route('individuals.store'), $this->payload(['department_ids' => []]))
            ->assertSessionHasErrors('department_ids');
    }

    public function test_a_hotel_employee_is_not_editable_as_an_individual()
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->forHotel($hotel, $this->department)->create(['username' => 'hotel.employee']);

        $this->actingAs($this->owner)
            ->patch(route('individuals.update', $employee), $this->payload(['username' => $employee->username]))
            ->assertNotFound();
    }

    public function test_only_the_subscriptions_capability_reaches_the_page()
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->forHotel($hotel, $this->department)->create();

        $this->actingAs($manager)->get(route('individuals'))->assertForbidden();
        $this->actingAs($manager)->post(route('individuals.store'), $this->payload())->assertForbidden();
    }

    public function test_an_ended_subscription_blocks_the_session_and_keeps_the_account()
    {
        $user = $this->individual(fn (IndividualSubscription $subscription) => $subscription->forceFill([
            'starts_on' => Date::today()->subDays(40),
            'ends_on' => Date::today()->subDay(),
        ])->save());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors();

        $this->assertNotNull($user->fresh());
    }

    public function test_an_individual_outside_their_window_cannot_sign_in_and_keeps_their_data()
    {
        $individual = User::factory()->employee()->create(['hotel_id' => null, 'username' => 'ended.one']);
        IndividualSubscription::factory()->for($individual)->ended()->create();

        $this->post(route('login.store'), ['email' => 'ended.one', 'password' => 'password'])
            ->assertSessionHasErrors();

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $individual->id]);
    }

    public function test_an_individual_inside_their_window_signs_in()
    {
        $individual = User::factory()->employee()->create(['hotel_id' => null, 'username' => 'active.one']);
        IndividualSubscription::factory()->for($individual)->create();

        $this->post(route('login.store'), ['email' => 'active.one', 'password' => 'password']);

        $this->assertAuthenticatedAs($individual);
    }

    public function test_ai_left_out_of_the_plan_is_refused_with_a_clear_message()
    {
        $user = $this->individual(fn (IndividualSubscription $subscription) => $subscription->forceFill(['ai_enabled' => false])->save());

        $this->expectException(AiLimitReached::class);
        $this->expectExceptionMessage('AI practice is not included in your subscription.');

        app(UsageMeter::class)->assertWithinLimits($user, AiFeature::RoleplayTurn);
    }

    public function test_an_individuals_points_and_daily_turns_are_their_own()
    {
        $user = $this->individual(fn (IndividualSubscription $subscription) => $subscription->forceFill([
            'daily_ai_turns' => 2,
            'ai_action_points' => 30,
        ])->save());
        $meter = app(UsageMeter::class);

        $this->assertSame(30, $meter->aiActionCost($user));

        AiUsage::factory()->count(2)->create([
            'user_id' => $user->id,
            'hotel_id' => null,
            'feature' => AiFeature::RoleplayTurn,
            'occurred_at' => Date::now(),
            'points_charged' => 0,
        ]);

        $this->assertFalse($meter->isWithinLimits($user->fresh() ?? $user, AiFeature::RoleplayTurn));
    }

    public function test_an_individual_out_of_points_is_told_to_contact_support()
    {
        $user = $this->individual();
        $user->forceFill(['ai_points_allocated' => 10])->save();

        $this->expectException(AiLimitReached::class);
        $this->expectExceptionMessage('Contact GHASIDO support');

        app(UsageMeter::class)->assertWithinLimits($user->fresh() ?? $user, AiFeature::RoleplayTurn);
    }

    /**
     * @param  (callable(IndividualSubscription): mixed)|null  $tweak
     */
    private function individual(?callable $tweak = null): User
    {
        $user = User::factory()->employee()->create([
            'username' => 'amel.individual',
            'hotel_id' => null,
            'department_id' => $this->department->id,
            'ai_points_allocated' => 3000,
        ]);
        $subscription = IndividualSubscription::factory()->create(['user_id' => $user->id]);
        $subscription->departments()->sync([$this->department->id => ['position' => 1]]);

        if ($tweak !== null) {
            $tweak($subscription);
        }

        return $user->fresh() ?? $user;
    }
}
