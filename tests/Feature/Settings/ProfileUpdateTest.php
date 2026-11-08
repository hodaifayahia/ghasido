<?php

namespace Tests\Feature\Settings;

use App\Models\Attempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_the_super_admin_removes_their_own_account_and_it_is_anonymised_not_deleted()
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        // Anonymised and kept, never hard-deleted (DATA-10).
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->removed_at);
        $this->assertNull($fresh->email);
    }

    public function test_an_employee_cannot_delete_their_own_account_and_their_answers_stay()
    {
        // Security audit 2026-11-08: a learner's self-delete cascaded
        // through every answer and recording (DATA-10, PROG-06).
        $employee = User::factory()->employee()->create();
        $attempt = Attempt::factory()->create(['user_id' => $employee->id]);

        $this->actingAs($employee)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertForbidden();

        $this->assertNotNull($employee->fresh());
        $this->assertNotNull($attempt->fresh());
    }

    public function test_a_manager_cannot_delete_their_own_account()
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertForbidden();

        $this->assertNull($manager->fresh()?->removed_at);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
