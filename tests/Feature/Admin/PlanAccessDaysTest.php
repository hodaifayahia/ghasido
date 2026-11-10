<?php

namespace Tests\Feature\Admin;

use App\Enums\HotelAccessState;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\IndividualSubscription;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Each plan sells a number of days of access, counted from approval, for the
 * whole price (client request 2026-11-10; SUB-04, SUB-06). AI points stay
 * monthly.
 */
class PlanAccessDaysTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SubscriptionPaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();
        Date::setTestNow(Date::parse('2026-11-10 09:00:00'));

        $this->owner = User::factory()->superAdmin()->create();
        $this->method = SubscriptionPaymentMethod::query()->where('name', 'BaridiMob')->firstOrFail();
        $this->method->forceFill(['is_active' => true, 'account_reference' => '1234 5678'])->save();
    }

    public function test_every_existing_plan_starts_at_sixty_days(): void
    {
        $this->assertSame(
            [60],
            SubscriptionPlan::query()->pluck('access_days')->unique()->values()->all(),
        );
    }

    public function test_the_super_admin_sets_the_access_days_of_a_plan(): void
    {
        $plan = $this->plan('gold');

        $this->actingAs($this->owner)
            ->get(route('subscriptions'))
            ->assertInertia(fn (Assert $page) => $page->where('plans.1.accessDays', 60));

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [...$this->planInput($plan), 'access_days' => 90])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $this->assertSame(90, $plan->fresh()?->access_days);
        $audit = AuditLog::query()->where('action', 'subscription.plan_updated')->sole();
        $this->assertSame(60, $audit->changes['before']['access_days']);
        $this->assertSame(90, $audit->changes['after']['access_days']);

        $this->actingAs($this->owner)
            ->get(route('subscriptions'))
            ->assertInertia(fn (Assert $page) => $page->where('plans.1.accessDays', 90));
    }

    public function test_an_individual_plan_takes_access_days_too(): void
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->where('slug', 'individual-plus')->firstOrFail();

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), [...$this->planInput($plan), 'access_days' => 30])
            ->assertSessionHasNoErrors();

        $this->assertSame(30, $plan->fresh()?->access_days);
    }

    public function test_access_days_must_be_between_one_and_3650(): void
    {
        $plan = $this->plan('gold');

        foreach ([0, 3651, -5, 'abc', null] as $value) {
            $this->actingAs($this->owner)
                ->from(route('subscriptions'))
                ->patch(route('subscriptions.plans.update', $plan), [...$this->planInput($plan), 'access_days' => $value])
                ->assertSessionHasErrors('access_days');
        }

        $input = $this->planInput($plan);
        unset($input['access_days']);
        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.plans.update', $plan), $input)
            ->assertSessionHasErrors('access_days');

        $this->assertSame(60, $plan->fresh()?->access_days);

        foreach ([1, 3650] as $value) {
            $this->actingAs($this->owner)
                ->from(route('subscriptions'))
                ->patch(route('subscriptions.plans.update', $plan), [...$this->planInput($plan), 'access_days' => $value])
                ->assertSessionHasNoErrors();
            $this->assertSame($value, $plan->fresh()?->access_days);
        }
    }

    public function test_the_landing_page_and_checkout_carry_the_access_days(): void
    {
        $gold = $this->plan('gold');
        $gold->forceFill(['access_days' => 75])->save();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('plans.1.slug', 'gold')
                ->where('plans.1.accessDays', 75)
                ->where('plans.0.accessDays', 60)
                ->where('individualPlans.0.accessDays', 60));

        $this->get(route('checkout.show', $gold))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout')
                ->where('plan.accessDays', 75));
    }

    public function test_approving_an_individual_opens_the_account_for_the_plans_access_days(): void
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->where('slug', 'individual-plus')->firstOrFail();
        $plan->forceFill(['access_days' => 45])->save();
        $subscription = $this->individualCheckout($plan);

        $this->assertNull($subscription->ends_on);

        $this->actingAs($this->owner)
            ->post(route('individuals.approve', $subscription->user_id))
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame('2026-11-10', $subscription->starts_on?->toDateString());
        // 45 days counting the first day: 10 November to 24 December.
        $this->assertSame('2026-12-24', $subscription->ends_on?->toDateString());
    }

    public function test_approving_an_individual_on_the_default_plan_gives_sixty_days(): void
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->where('slug', 'individual-plus')->firstOrFail();
        $subscription = $this->individualCheckout($plan);

        $this->actingAs($this->owner)
            ->post(route('individuals.approve', $subscription->user_id))
            ->assertRedirect();

        $this->assertSame('2027-01-08', $subscription->fresh()?->ends_on?->toDateString());
    }

    public function test_approving_a_checkout_hotel_sets_its_contract_from_the_plans_access_days(): void
    {
        $gold = $this->plan('gold');
        $gold->forceFill(['access_days' => 90])->save();
        $hotel = $this->hotelCheckout($gold);

        $this->assertNull($hotel->contract_starts_on);
        $this->assertNull($hotel->contract_ends_on);

        $this->actingAs($this->owner)
            ->post(route('hotels.approve', $hotel))
            ->assertRedirect();

        $hotel->refresh();
        $this->assertSame(HotelAccessState::Active, $hotel->access_state);
        $this->assertSame('2026-11-10', $hotel->contract_starts_on?->toDateString());
        // 90 days counting the first day: 10 November to 7 February.
        $this->assertSame('2027-02-07', $hotel->contract_ends_on?->toDateString());
    }

    public function test_approving_a_hotel_keeps_an_end_date_the_admin_already_set(): void
    {
        $hotel = Hotel::factory()->pending()->create([
            'subscription_plan_id' => $this->plan('gold')->id,
            'contract_starts_on' => null,
            'contract_ends_on' => '2027-06-30',
        ]);

        $this->actingAs($this->owner)
            ->post(route('hotels.approve', $hotel))
            ->assertRedirect();

        $this->assertSame('2027-06-30', $hotel->fresh()?->contract_ends_on?->toDateString());
    }

    public function test_extending_a_contract_still_sets_the_chosen_end_date(): void
    {
        $hotel = $this->hotelCheckout($this->plan('gold'));
        $this->actingAs($this->owner)->post(route('hotels.approve', $hotel))->assertRedirect();

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->patch(route('hotels.contract', $hotel), ['contract_ends_on' => '2027-12-31'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2027-12-31', $hotel->fresh()?->contract_ends_on?->toDateString());
    }

    private function plan(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function planInput(SubscriptionPlan $plan): array
    {
        return [
            'name' => $plan->name,
            'employee_limit' => $plan->employee_limit,
            'price_dzd' => $plan->price_dzd,
            'price_usd' => $plan->price_usd,
            'extra_points_price_dzd' => $plan->extra_points_price_dzd,
            'extra_points_price_usd' => $plan->extra_points_price_usd,
            'extra_seat_price_dzd' => $plan->extra_seat_price_dzd,
            'extra_seat_price_usd' => $plan->extra_seat_price_usd,
            'points_per_employee' => $plan->points_per_employee,
            'bonus_points_per_employee' => $plan->bonus_points_per_employee,
            'voice_points_per_10_minutes' => $plan->voice_points_per_10_minutes,
            'ai_action_points' => $plan->ai_action_points,
            'access_days' => $plan->access_days,
            'is_active' => $plan->is_active,
        ];
    }

    private function hotelCheckout(SubscriptionPlan $plan): Hotel
    {
        $this->post(route('checkout.store', $plan), [
            'name' => 'Blue Coast Hotel',
            'city' => 'Oran',
            'manager_name' => 'Nassim Benali',
            'manager_email' => 'manager@bluecoast.test',
            'manager_username' => 'blue.coast.manager',
            'phone' => '+213 555 12 34 56',
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
            'payment_method_id' => $this->method->id,
            'reference' => 'GHASIDO001',
            'proof' => UploadedFile::fake()->image('receipt.jpg', 600, 900)->size(240),
        ])->assertSessionHasNoErrors();

        return Hotel::query()->withoutGlobalScopes()->where('name', 'Blue Coast Hotel')->sole();
    }

    private function individualCheckout(SubscriptionPlan $plan): IndividualSubscription
    {
        $department = Department::factory()->create(['hotel_id' => null, 'is_active' => true]);

        $this->post(route('checkout.store', $plan), [
            'name' => 'Samia Haddad',
            'email' => 'samia@example.test',
            'username' => 'samia.h',
            'phone' => '0555 11 22 33',
            'department_id' => $department->id,
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
            'payment_method_id' => $this->method->id,
            'reference' => 'R1',
        ])->assertSessionHasNoErrors();

        return IndividualSubscription::query()->sole();
    }
}
