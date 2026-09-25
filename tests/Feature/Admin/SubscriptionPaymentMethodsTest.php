<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\SubscriptionPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Editable public subscription payment instructions (SUB-01, ADM-02, SEC-01). */
class SubscriptionPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_add_customize_and_deactivate_public_payment_methods(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subscriptions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Subscriptions')
                ->has('paymentMethods', 2)
                ->where('paymentMethods.0.name', 'BaridiMob')
                ->where('paymentMethods.0.isActive', false)
                ->where('paymentMethods.1.name', 'RedotPay'));

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->post(route('subscriptions.payment-methods.store'), [
                'name' => 'Bank transfer',
                'recipient_name' => 'Guesvia SARL',
                'account_reference' => 'DZ00 1234 5678',
                'instructions' => 'Include your hotel name in the transfer note.',
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $method = SubscriptionPaymentMethod::query()->where('name', 'Bank transfer')->sole();
        $this->assertSame('Guesvia SARL', $method->recipient_name);
        $this->assertTrue($method->is_active);
        $createdAudit = AuditLog::query()->where('action', 'subscription.payment_method_created')->sole();
        $this->assertSame($this->owner->id, $createdAudit->actor_id);

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.payment-methods.update', $method), [
                'name' => 'Bank transfer Algeria',
                'recipient_name' => 'Guesvia DZ',
                'account_reference' => 'DZ00 9876 5432',
                'instructions' => 'Contact support after paying.',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $method->refresh();
        $this->assertSame('Bank transfer Algeria', $method->name);
        $this->assertSame('DZ00 9876 5432', $method->account_reference);
        $this->assertSame(1, $method->sort_order);
        $updatedAudit = AuditLog::query()->where('action', 'subscription.payment_method_updated')->sole();
        $this->assertSame($this->owner->id, $updatedAudit->actor_id);

        $this->actingAs($this->owner)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->has('paymentMethods', 1)
                ->where('paymentMethods.0.name', 'Bank transfer Algeria')
                ->where('paymentMethods.0.accountReference', 'DZ00 9876 5432')
                ->where('paymentMethods.0.recipientName', 'Guesvia DZ'));

        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->patch(route('subscriptions.payment-methods.update', $method), [
                'name' => $method->name,
                'recipient_name' => $method->recipient_name,
                'account_reference' => $method->account_reference,
                'instructions' => $method->instructions,
                'sort_order' => $method->sort_order,
                'is_active' => false,
            ])
            ->assertRedirect(route('subscriptions'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get('/')
            ->assertInertia(fn ($page) => $page->component('Welcome')->has('paymentMethods', 0));
    }

    public function test_active_payment_method_requires_public_account_details(): void
    {
        $this->actingAs($this->owner)
            ->from(route('subscriptions'))
            ->post(route('subscriptions.payment-methods.store'), [
                'name' => 'New wallet',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('account_reference');

        $this->assertDatabaseMissing('subscription_payment_methods', ['name' => 'New wallet']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'subscription.payment_method_created']);
    }

    public function test_unconfigured_methods_are_never_exposed_on_the_public_page(): void
    {
        SubscriptionPaymentMethod::query()->where('name', 'BaridiMob')->update([
            'is_active' => true,
            'account_reference' => '   ',
        ]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page->component('Welcome')->has('paymentMethods', 0));
    }

    public function test_hotel_managers_cannot_manage_payment_methods(): void
    {
        $manager = User::factory()->manager()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($manager)
            ->post(route('subscriptions.payment-methods.store'), [
                'name' => 'Private method',
                'account_reference' => 'secret',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('subscription_payment_methods', ['name' => 'Private method']);
    }
}
