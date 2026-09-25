<?php

namespace Tests\Feature\Learn;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Username login (AUTH-01, AUTH-02; spec 0003 B.1).
 *
 * The posted field is still called `email`; it takes a username or an
 * email address, case-insensitively. Registration is gone.
 */
class LoginByUsernameTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_an_employee_signs_in_with_their_username()
    {
        $learner = $this->learner();

        $response = $this->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($learner);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($learner->fresh()?->last_login_at);
    }

    public function test_the_username_is_matched_case_insensitively()
    {
        $learner = $this->learner();

        $this->post(route('login.store'), [
            'email' => '  SAMIRA ',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($learner);
    }

    public function test_an_email_address_still_signs_in()
    {
        $learner = $this->learner(['email' => 'samira@example.test']);

        $this->post(route('login.store'), [
            'email' => 'Samira@Example.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($learner);
    }

    public function test_a_wrong_password_is_refused_with_the_generic_message()
    {
        $this->learner();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'not-the-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_an_employee_landing_on_the_dashboard_is_sent_to_the_learner_home()
    {
        $this->actingAs($this->learner())
            ->get(route('dashboard'))
            ->assertRedirect(route('learn.home'));
    }

    public function test_self_registration_no_longer_exists()
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));
        $this->assertFalse(Route::has('verification.notice'));

        $this->get('/register')->assertNotFound();
    }

    public function test_a_super_admin_still_signs_in_by_email()
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'owner@example.test']);

        $this->post(route('login.store'), [
            'email' => 'owner@example.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
    }
}
