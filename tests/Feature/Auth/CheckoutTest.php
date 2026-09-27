<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\HotelAccessState;
use App\Enums\Role;
use App\Models\Hotel;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Public plan request flow (AUTH-02, AUTH-08, SUB-01; user request 2026-09-23). */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_landing_lists_the_active_subscription_plans(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('plans', 3)
                // Individual plans are listed apart (client request 2026-09-27).
                ->has('individualPlans', 3)
                ->where('plans.1.slug', 'gold')
                ->where('plans.1.employeeLimit', 7)
                ->where('plans.1.priceDzd', 20000)
                ->has('content.roles.items', 3)
                ->where('content.roles.items.0.title', 'Hotel Admin'));
    }

    public function test_guest_can_open_an_active_plan_checkout(): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'gold')->firstOrFail();

        $this->get(route('checkout.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout')
                ->where('plan.slug', 'gold')
                ->where('plan.employeeLimit', 7)
                ->where('plan.priceDzd', 20000));
    }

    public function test_checkout_keeps_the_selected_plan_and_accounts_pending_for_approval(): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'gold')->firstOrFail();

        $this->post(route('checkout.store', $plan), $this->validRequest())
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $hotel = Hotel::query()->where('name', 'Blue Coast Hotel')->firstOrFail();
        $manager = User::query()->where('username', 'blue.coast.manager')->firstOrFail();

        $this->assertSame($plan->id, $hotel->subscription_plan_id);
        $this->assertSame(HotelAccessState::Pending, $hotel->access_state);
        $this->assertSame(AccountStatus::Inactive, $manager->status);
        $this->assertTrue($manager->hasRole(Role::Manager->value));
    }

    public function test_inactive_plans_cannot_be_requested(): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'gold')->firstOrFail();
        $plan->is_active = false;
        $plan->save();

        $this->get(route('checkout.show', $plan))->assertNotFound();
        $this->post(route('checkout.store', $plan), $this->validRequest())->assertNotFound();
    }

    /** @return array<string, string> */
    private function validRequest(): array
    {
        return [
            'name' => 'Blue Coast Hotel',
            'city' => 'Oran',
            'manager_name' => 'Nassim Benali',
            'manager_email' => 'manager@bluecoast.test',
            'manager_username' => 'blue.coast.manager',
            'phone' => '+213 555 12 34 56',
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
        ];
    }
}
