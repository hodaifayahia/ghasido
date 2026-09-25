<?php

namespace Tests\Feature\Owner;

use App\Models\ApiCreditTopup;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The owner console's own login (spec 0007, D1): a separate guard that no
 * app user, the Super Admin included, can pass.
 */
class OwnerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_login_page_renders()
    {
        $this->get(route('owner.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('owner/Login'));
    }

    public function test_an_owner_signs_in_and_reaches_the_console()
    {
        $owner = Owner::factory()->create(['email' => 'owner@clickdz.test']);

        $this->post(route('owner.login.store'), ['email' => 'OWNER@clickdz.test', 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($owner, 'owner');
        $this->assertGuest('web');
        $this->assertNotNull($owner->refresh()->last_login_at);

        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('owner/Dashboard')
                ->where('owner.email', 'owner@clickdz.test')
                ->has('accounts', 2));
    }

    public function test_a_wrong_password_is_refused()
    {
        $owner = Owner::factory()->create();

        $this->post(route('owner.login.store'), ['email' => $owner->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('owner');
    }

    public function test_the_login_is_rate_limited()
    {
        $owner = Owner::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('owner.login.store'), ['email' => $owner->email, 'password' => 'wrong-password']);
        }

        // Even the right password is refused once the limit is hit.
        $this->post(route('owner.login.store'), ['email' => $owner->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('owner');
    }

    public function test_a_guest_is_sent_to_the_owner_login()
    {
        $this->get(route('owner.dashboard'))->assertRedirect(route('owner.login'));
    }

    public function test_an_app_page_visited_first_does_not_hijack_the_owner_sign_in()
    {
        // Seen live 2026-09-25: an app page opened while signed out left its
        // URL in `url.intended`, and the owner sign-in went there, then to
        // the app login.
        $owner = Owner::factory()->create();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('owner.login.store'), ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($owner, 'owner');
    }

    public function test_the_owner_returns_to_the_owner_page_that_asked_for_the_sign_in()
    {
        $owner = Owner::factory()->create();
        $this->get(route('owner.dashboard', ['tab' => 'prices']))->assertRedirect(route('owner.login'));

        $this->post(route('owner.login.store'), ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard', ['tab' => 'prices']));
    }

    public function test_an_owner_page_visited_first_does_not_hijack_the_app_sign_in()
    {
        $this->get(route('owner.dashboard'))->assertRedirect(route('owner.login'));

        $this->assertNull(session('url.intended'));
    }

    public function test_no_app_user_reaches_the_console_not_even_the_super_admin()
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('owner.dashboard'))
            ->assertRedirect(route('owner.login'));

        $this->actingAs($superAdmin)
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 1000])
            ->assertRedirect(route('owner.login'));

        $this->actingAs($superAdmin)
            ->put(route('owner.accounts.key.update', ['account' => 'qwen']), ['key' => 'sk-stolen-key-123'])
            ->assertRedirect(route('owner.login'));

        $this->assertSame(0, ApiCreditTopup::query()->count());
        $this->assertDatabaseCount('api_account_settings', 0);
    }

    public function test_an_owner_is_not_an_app_user()
    {
        // Signed in through the owner form (actingAs() would also switch
        // the default guard, which a real request never does).
        $owner = Owner::factory()->create();
        $this->post(route('owner.login.store'), ['email' => $owner->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($owner, 'owner');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('ai-usage.index'))->assertRedirect(route('login'));
    }

    public function test_the_owner_signs_out()
    {
        $this->actingAs(Owner::factory()->create(), 'owner')
            ->post(route('owner.logout'))
            ->assertRedirect(route('owner.login'));

        $this->assertGuest('owner');
    }

    public function test_the_signed_in_owner_skips_the_login_page()
    {
        $this->actingAs(Owner::factory()->create(), 'owner')
            ->get(route('owner.login'))
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_owner_create_makes_the_account_and_resets_its_password()
    {
        $this->artisan('owner:create', ['email' => 'Me@ClickDz.test', '--name' => 'Houdaifa', '--password' => 'a-long-secret-1'])
            ->assertSuccessful();

        $owner = Owner::query()->sole();
        $this->assertSame('me@clickdz.test', $owner->email);
        $this->assertSame('Houdaifa', $owner->name);
        $this->assertTrue(Hash::check('a-long-secret-1', $owner->password));

        $this->artisan('owner:create', ['email' => 'me@clickdz.test', '--password' => 'another-secret-2'])
            ->assertSuccessful();

        $this->assertSame(1, Owner::query()->count());
        $this->assertSame('Houdaifa', $owner->refresh()->name);
        $this->assertTrue(Hash::check('another-secret-2', $owner->password));
    }

    public function test_owner_create_refuses_a_short_password()
    {
        $this->artisan('owner:create', ['email' => 'me@clickdz.test', '--password' => 'short'])
            ->assertFailed();

        $this->assertSame(0, Owner::query()->count());
    }
}
