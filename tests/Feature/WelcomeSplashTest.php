<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The one-time welcome animation after a user's first sign-in (client
 * request 2026-09-26): shown until the server records it, then never again.
 */
class WelcomeSplashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_new_user_is_welcomed_once()
    {
        $user = User::factory()->superAdmin()->create(['welcomed_at' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.show_welcome', true));

        $this->actingAs($user)
            ->postJson(route('welcome.seen'))
            ->assertOk();

        $this->assertNotNull($user->fresh()?->welcomed_at);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.show_welcome', false));
    }

    public function test_marking_it_seen_twice_keeps_the_first_time()
    {
        $first = now()->subDay()->startOfSecond();
        $user = User::factory()->superAdmin()->create(['welcomed_at' => $first]);

        $this->actingAs($user)->postJson(route('welcome.seen'))->assertOk();

        $this->assertTrue($first->equalTo($user->fresh()?->welcomed_at));
    }

    public function test_a_guest_cannot_mark_it_seen()
    {
        $this->postJson(route('welcome.seen'))->assertUnauthorized();
    }
}
