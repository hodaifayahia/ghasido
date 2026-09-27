<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountStatus;
use App\Enums\ApprovalState;
use App\Enums\HotelAccessState;
use App\Enums\PaymentStatus;
use App\Mail\AccountRejectedMail;
use App\Mail\CustomerMessageMail;
use App\Mail\HotelApprovedMail;
use App\Mail\IndividualApprovedMail;
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
 * The admin side of a manual payment (client request 2026-09-27): the
 * Payments list, the private receipt, writing to the customer, and the
 * payment following the hotel's or the individual's approval.
 */
class PaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPaymentMethod $method;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();

        $this->method = SubscriptionPaymentMethod::query()->where('name', 'BaridiMob')->firstOrFail();
        $this->method->forceFill(['is_active' => true, 'account_reference' => '1234 5678'])->save();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_the_payments_list_shows_pending_payments_and_the_selected_one(): void
    {
        $submission = $this->hotelCheckout();

        $this->actingAs($this->admin)
            ->get(route('payments', ['payment' => $submission->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Payments')
                ->has('payments', 1)
                ->where('payments.0.customer', 'Blue Coast Hotel')
                ->where('payments.0.status', 'pending')
                ->where('payments.0.type', 'hotel')
                ->where('counts.pending', 1)
                ->where('selected.id', $submission->id)
                ->where('pendingPayments', 1));

        $this->actingAs($this->admin)
            ->get(route('payments', ['status' => 'confirmed']))
            ->assertInertia(fn (Assert $page) => $page->has('payments', 0));
    }

    public function test_the_receipt_is_private_to_subscription_managers(): void
    {
        $submission = $this->hotelCheckout();
        $url = route('payments.receipt', $submission);

        $this->get($url)->assertRedirect(route('login'));

        $employee = User::factory()->employee()->create();
        $this->actingAs($employee)->get($url)->assertForbidden();

        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_admin_marks_a_payment_read_and_writes_to_the_customer(): void
    {
        $submission = $this->hotelCheckout();

        $this->actingAs($this->admin)
            ->post(route('payments.read', $submission))
            ->assertRedirect();
        $this->assertNotNull($submission->fresh()?->read_at);

        $this->actingAs($this->admin)
            ->post(route('payments.message', $submission), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);

        $this->actingAs($this->admin)
            ->post(route('payments.message', $submission), [
                'subject' => 'Your receipt',
                'body' => 'Could you send a clearer photo?',
            ])
            ->assertSessionHasNoErrors();

        Mail::assertQueued(CustomerMessageMail::class, fn (CustomerMessageMail $mail) => $mail->hasTo('manager@bluecoast.test'));
    }

    public function test_approving_the_hotel_confirms_its_payment(): void
    {
        $submission = $this->hotelCheckout();
        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($submission->hotel_id);

        $this->actingAs($this->admin)
            ->get(route('hotels.show', $hotel))
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval.pending', true)
                ->where('approval.canApprove', true)
                ->where('approval.requester.email', 'manager@bluecoast.test')
                ->has('approval.payments', 1));

        $this->actingAs($this->admin)->post(route('hotels.approve', $hotel))->assertRedirect();

        $submission->refresh();
        $this->assertSame(PaymentStatus::Confirmed, $submission->status);
        $this->assertSame($this->admin->id, $submission->reviewed_by);
        Mail::assertQueued(HotelApprovedMail::class);
    }

    public function test_rejecting_the_hotel_rejects_its_payment_and_tells_the_requester(): void
    {
        $submission = $this->hotelCheckout();
        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($submission->hotel_id);

        $this->actingAs($this->admin)
            ->post(route('hotels.reject', $hotel), ['reason' => 'The transfer never arrived.'])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame(PaymentStatus::Rejected, $submission->status);
        $this->assertSame('The transfer never arrived.', $submission->rejection_reason);
        $this->assertSame(HotelAccessState::Archived, $hotel->fresh()?->access_state);
        Mail::assertQueued(AccountRejectedMail::class, fn (AccountRejectedMail $mail) => $mail->hasTo('manager@bluecoast.test'));
    }

    public function test_approving_an_individual_activates_them_and_confirms_the_payment(): void
    {
        $submission = $this->individualCheckout();
        $subscription = $submission->individualSubscription;
        $this->assertNotNull($subscription);

        // Not switchable before approval.
        $this->actingAs($this->admin)
            ->post(route('individuals.toggle', $subscription->user_id))
            ->assertStatus(409);

        $this->actingAs($this->admin)
            ->get(route('individuals', ['state' => 'pending']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.pending', 1)
                ->where('individuals.0.approvalState', 'pending')
                ->where('individuals.0.payment.id', $submission->id));

        $this->actingAs($this->admin)
            ->post(route('individuals.approve', $subscription->user_id))
            ->assertRedirect();

        $subscription->refresh();
        $user = $subscription->user()->firstOrFail();
        $this->assertSame(ApprovalState::Approved, $subscription->approval_state);
        $this->assertNotNull($subscription->starts_on);
        $this->assertNotNull($subscription->ends_on);
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertSame(PaymentStatus::Confirmed, $submission->fresh()?->status);
        Mail::assertQueued(IndividualApprovedMail::class, fn (IndividualApprovedMail $mail) => $mail->hasTo('samia@example.test'));

        // Approving twice is refused.
        $this->actingAs($this->admin)
            ->post(route('individuals.approve', $subscription->user_id))
            ->assertSessionHasErrors();
    }

    public function test_rejecting_an_individual_keeps_them_out_and_rejects_the_payment(): void
    {
        $submission = $this->individualCheckout();
        $subscription = $submission->individualSubscription;
        $this->assertNotNull($subscription);

        $this->actingAs($this->admin)
            ->post(route('individuals.reject', $subscription->user_id), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)
            ->post(route('individuals.reject', $subscription->user_id), ['reason' => 'Amount does not match.'])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame(ApprovalState::Rejected, $subscription->approval_state);
        $this->assertSame(AccountStatus::Inactive, $subscription->user()->firstOrFail()->status);
        $this->assertSame(PaymentStatus::Rejected, $submission->fresh()?->status);
        Mail::assertQueued(AccountRejectedMail::class, fn (AccountRejectedMail $mail) => $mail->hasTo('samia@example.test'));
    }

    public function test_a_hotel_manager_cannot_review_payments(): void
    {
        $submission = $this->hotelCheckout();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('payments'))->assertForbidden();
        $this->actingAs($manager)->post(route('payments.message', $submission), ['subject' => 'x', 'body' => 'y'])->assertForbidden();
        Mail::assertNotQueued(CustomerMessageMail::class);
    }

    private function hotelCheckout(): PaymentSubmission
    {
        $plan = SubscriptionPlan::query()->where('slug', 'gold')->firstOrFail();

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

        return PaymentSubmission::query()->sole();
    }

    private function individualCheckout(): PaymentSubmission
    {
        $plan = SubscriptionPlan::query()->forIndividuals()->where('slug', 'individual-plus')->firstOrFail();
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

        return PaymentSubmission::query()->sole();
    }
}
