<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountStatus;
use App\Enums\ApprovalState;
use App\Enums\HotelAccessState;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Mail\PaymentSubmittedMail;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\PaymentSubmission;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Buying a plan online with a manual payment (client request 2026-09-27):
 * hotels and individuals send the method, a receipt and/or a reference,
 * and wait for approval.
 */
class CheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();

        $this->method = SubscriptionPaymentMethod::query()->where('name', 'BaridiMob')->firstOrFail();
        $this->method->forceFill([
            'is_active' => true,
            'recipient_name' => 'Feriel Khenane',
            'account_reference' => '1234 5678 9012',
            'instructions' => "Open BaridiMob\nSend the amount",
        ])->save();
    }

    public function test_a_hotel_pays_with_a_receipt_and_waits_for_approval(): void
    {
        $plan = $this->plan('gold');
        $admin = User::factory()->superAdmin()->create();

        $response = $this->post(route('checkout.store', $plan), [
            ...$this->hotelFields(),
            'payment_method_id' => $this->method->id,
            'reference' => 'GHASIDO001',
            'proof' => UploadedFile::fake()->image('receipt.jpg', 600, 900)->size(240),
        ]);

        $submission = PaymentSubmission::query()->sole();
        $response->assertRedirect(route('checkout.submitted', ['submission' => $submission->public_id]));

        $hotel = Hotel::query()->withoutGlobalScopes()->where('name', 'Blue Coast Hotel')->firstOrFail();
        $manager = User::query()->where('username', 'blue.coast.manager')->firstOrFail();

        $this->assertSame(HotelAccessState::Pending, $hotel->access_state);
        $this->assertSame(AccountStatus::Inactive, $manager->status);
        $this->assertSame('+213 555 12 34 56', $manager->phone);
        $this->assertSame($hotel->id, $submission->hotel_id);
        $this->assertSame(PaymentStatus::Pending, $submission->status);
        $this->assertSame(20000.0, $submission->amount);
        $this->assertSame('DZD', $submission->currency);
        $this->assertSame('BaridiMob', $submission->payment_method_name);
        $this->assertSame('GHASIDO001', $submission->reference);
        $this->assertSame('receipt.jpg', $submission->proof_name);
        $this->assertNotNull($submission->proof_path);
        // Kept private, under a random name.
        Storage::disk('local')->assertExists((string) $submission->proof_path);
        $this->assertStringNotContainsString('receipt', basename((string) $submission->proof_path));

        Mail::assertQueued(PaymentSubmittedMail::class, fn (PaymentSubmittedMail $mail) => $mail->hasTo($admin->email));

        $this->get(route('checkout.submitted', ['submission' => $submission->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CheckoutSubmitted')
                ->where('payment.planName', 'Gold')
                ->where('payment.status', 'pending')
                ->where('payment.hasReceipt', true));
    }

    public function test_the_international_price_is_charged_in_dollars(): void
    {
        $plan = $this->plan('gold');
        $plan->forceFill(['price_usd' => 149.5])->save();

        $this->post(route('checkout.store', $plan), [
            ...$this->hotelFields(),
            'region' => 'intl',
            'payment_method_id' => $this->method->id,
            'reference' => 'WIRE-77',
        ])->assertSessionHasNoErrors();

        $submission = PaymentSubmission::query()->sole();
        $this->assertSame('USD', $submission->currency);
        $this->assertSame(149.5, $submission->amount);
        $this->assertNull($submission->proof_path);
    }

    public function test_a_receipt_or_a_reference_is_required_and_the_file_is_checked(): void
    {
        $plan = $this->plan('gold');

        $this->post(route('checkout.store', $plan), [
            ...$this->hotelFields(),
            'payment_method_id' => $this->method->id,
        ])->assertSessionHasErrors(['proof', 'reference']);

        $this->post(route('checkout.store', $plan), [
            ...$this->hotelFields(),
            'payment_method_id' => $this->method->id,
            'proof' => UploadedFile::fake()->create('notes.exe', 20, 'application/octet-stream'),
        ])->assertSessionHasErrors('proof');

        $this->post(route('checkout.store', $plan), [
            ...$this->hotelFields(),
            'payment_method_id' => $this->method->id,
            'proof' => UploadedFile::fake()->create('big.pdf', 6 * 1024, 'application/pdf'),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payment_submissions', 0);
        $this->assertFalse(Hotel::query()->withoutGlobalScopes()->where('name', 'Blue Coast Hotel')->exists());
    }

    public function test_a_hidden_payment_method_cannot_be_used(): void
    {
        $hidden = SubscriptionPaymentMethod::query()->where('name', 'RedotPay')->firstOrFail();

        $this->post(route('checkout.store', $this->plan('gold')), [
            ...$this->hotelFields(),
            'payment_method_id' => $hidden->id,
            'reference' => 'X1',
        ])->assertSessionHasErrors('payment_method_id');
    }

    public function test_an_individual_buys_ai_points_and_waits_for_approval(): void
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->where('slug', 'individual-plus')->firstOrFail();
        $department = Department::factory()->create(['hotel_id' => null, 'is_active' => true]);

        $this->get(route('checkout.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('plan.audience', 'individual')
                ->where('plan.pointsPool', $plan->points_per_employee)
                ->has('departments'));

        $this->withUnencryptedCookie('locale', 'ar')->post(route('checkout.store', $plan), [
            'name' => 'Samia Haddad',
            'email' => 'samia@example.test',
            'username' => 'samia.h',
            'phone' => '0555 11 22 33',
            'department_id' => $department->id,
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
            'payment_method_id' => $this->method->id,
            'proof' => UploadedFile::fake()->create('receipt.pdf', 300, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'samia.h')->firstOrFail();
        $subscription = $user->individualSubscription;

        $this->assertNull($user->hotel_id);
        $this->assertSame(AccountStatus::Inactive, $user->status);
        $this->assertTrue($user->hasRole(Role::Employee->value));
        // AI points, never seats.
        $this->assertSame($plan->points_per_employee, $user->ai_points_allocated);
        // Emails reach them in the language they bought in.
        $this->assertSame('ar', $user->locale);
        $this->assertNotNull($subscription);
        $this->assertSame(ApprovalState::Pending, $subscription->approval_state);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertSame([$department->id], $subscription->departmentIds());

        $submission = PaymentSubmission::query()->sole();
        $this->assertSame($subscription->id, $submission->individual_subscription_id);
        $this->assertSame('application/pdf', $submission->proof_mime);
    }

    public function test_an_individual_must_pick_a_shared_department(): void
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->firstOrFail();
        $hotelOnly = Department::factory()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->post(route('checkout.store', $plan), [
            'name' => 'Samia Haddad',
            'email' => 'samia@example.test',
            'username' => 'samia.h',
            'phone' => '0555 11 22 33',
            'department_id' => $hotelOnly->id,
            'password' => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
            'payment_method_id' => $this->method->id,
            'reference' => 'R1',
        ])->assertSessionHasErrors('department_id');
    }

    public function test_a_pending_individual_cannot_sign_in_yet(): void
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->firstOrFail();
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
        ]);

        $this->post(route('login.store'), ['username' => 'samia.h', 'password' => 'SecretPass123']);
        $this->assertGuest();
    }

    public function test_hotel_screens_never_offer_individual_plans(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('subscriptions'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 6)
                ->has('activePlans', 3)
                ->where('activePlans.0.name', 'Standard'));

        $individualPlan = SubscriptionPlan::query()->forIndividuals()->firstOrFail();
        $hotel = Hotel::factory()->create();

        $this->actingAs($admin)
            ->put(route('subscriptions.hotel-plan'), ['hotel_id' => $hotel->id, 'plan_id' => $individualPlan->id])
            ->assertSessionHasErrors('plan_id');
    }

    private function plan(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();
    }

    /** @return array<string, string> */
    private function hotelFields(): array
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
