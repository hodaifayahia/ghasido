<?php

namespace Tests\Feature\Security;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): a deactivated account keeps no way in
 * (AUTH-08), and the password re-check before 2FA and passkey changes is
 * bound to the signed-in user.
 */
class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_super_admin_session_cannot_reach_platform_settings()
    {
        $admin = User::factory()->superAdmin()->create(['status' => AccountStatus::Inactive]);

        foreach (['mail-settings.edit', 'ai-models.edit', 'learning-settings.edit'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertRedirect(route('login'));
            $this->assertGuest();
        }

        $this->actingAs($admin)
            ->patch(route('mail-settings.update'), ['host' => 'evil.test'])
            ->assertRedirect(route('login'));
    }

    public function test_an_active_super_admin_still_reaches_platform_settings()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('mail-settings.edit'))->assertOk();
    }

    public function test_a_passkey_cannot_sign_in_a_deactivated_account()
    {
        $active = User::factory()->employee()->create();
        $inactive = User::factory()->employee()->create(['status' => AccountStatus::Inactive]);

        $this->assertTrue(Passkeys::allowsLogin(Request::create('/'), $this->passkeyFor($active)));
        $this->assertFalse(Passkeys::allowsLogin(Request::create('/'), $this->passkeyFor($inactive)));
    }

    public function test_deactivating_an_account_ends_its_sessions_and_remember_token()
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->employee()->create(['remember_token' => 'old-token']);
        $other = User::factory()->employee()->create();

        foreach ([$user, $other] as $index => $owner) {
            DB::table('sessions')->insert([
                'id' => 'session-'.$index,
                'user_id' => $owner->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => '',
                'last_activity' => time(),
            ]);
        }

        $user->update(['status' => AccountStatus::Inactive]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        $this->assertNotSame('old-token', $user->fresh()?->getRememberToken());
    }

    public function test_password_confirmation_checks_the_signed_in_user_even_without_an_email()
    {
        $first = User::factory()->employee()->create(['email' => null, 'password' => 'first-password']);
        $victim = User::factory()->employee()->create(['email' => null, 'password' => 'victim-password']);

        $this->actingAs($victim)
            ->post(route('password.confirm.store'), ['password' => 'first-password'])
            ->assertSessionHasErrors('password');

        $this->actingAs($victim)
            ->post(route('password.confirm.store'), ['password' => 'victim-password'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($first->id);
    }

    private function passkeyFor(User $user): Passkey
    {
        $passkey = new Passkey;
        $passkey->setRelation('user', $user);

        return $passkey;
    }
}
