<?php

namespace Tests\Feature\Learn;

use App\Enums\AccountStatus;
use App\Enums\HotelAccessState;
use App\Models\Hotel;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Access follows the hotel (AUTH-08, SUB-05, SUB-08, DATA-10; spec 0003
 * B.1, Part E).
 *
 * A contract that has ended, a paused or archived hotel and a deactivated
 * account all refuse to sign in with a message that says why, and an open
 * session is ended the same way. Nothing is ever deleted.
 */
class HotelAccessGateTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_login_is_refused_when_the_contract_has_ended_and_every_row_is_preserved()
    {
        $this->hotel = Hotel::factory()->ended()->create();
        $learner = $this->learner();
        $lesson = $this->publishedLesson();
        LessonCompletion::factory()->create(['user_id' => $learner->id, 'lesson_id' => $lesson->id]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => HotelAccessState::Active->blockedMessage()]);

        $this->assertDatabaseHas('users', ['id' => $learner->id, 'username' => 'samira']);
        $this->assertDatabaseHas('hotels', ['id' => $this->hotel->id]);
        $this->assertDatabaseHas('lesson_completions', ['user_id' => $learner->id, 'lesson_id' => $lesson->id]);
    }

    public function test_login_is_refused_for_a_paused_hotel_with_its_own_message()
    {
        $this->hotel = Hotel::factory()->paused()->create();
        $this->learner();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => HotelAccessState::Paused->blockedMessage()]);
    }

    public function test_login_is_refused_for_a_pending_hotel()
    {
        $this->hotel = Hotel::factory()->pending()->create();
        $this->learner();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => HotelAccessState::Pending->blockedMessage()]);

        $this->assertGuest();
    }

    public function test_login_is_refused_for_a_deactivated_account()
    {
        $this->learner(['status' => AccountStatus::Inactive]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('users', ['username' => 'samira', 'status' => AccountStatus::Inactive->value]);
    }

    public function test_a_wrong_password_on_a_blocked_hotel_gets_the_generic_error_not_the_reason()
    {
        // The hotel's state is only disclosed to someone who holds the
        // password; a stranger probing usernames learns nothing.
        $this->hotel = Hotel::factory()->paused()->create();
        $this->learner();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'samira',
            'password' => 'wrong',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringNotContainsString(
            HotelAccessState::Paused->blockedMessage(),
            (string) json_encode(session('errors')),
        );
    }

    public function test_an_open_session_is_ended_when_the_hotel_stops_allowing_access()
    {
        $learner = $this->learner();

        $this->actingAs($learner)->get(route('learn.home'))->assertOk();

        $this->hotel->forceFill(['access_state' => HotelAccessState::Paused, 'paused_at' => now()])->save();

        $response = $this->actingAs($learner->fresh())->get(route('learn.home'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => HotelAccessState::Paused->blockedMessage()]);
        $this->assertGuest();
    }

    public function test_a_deactivated_session_is_ended_too()
    {
        $learner = $this->learner();
        $learner->forceFill(['status' => AccountStatus::Inactive])->save();

        $this->actingAs($learner)->get(route('learn.home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_super_admin_without_a_hotel_is_never_blocked()
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'owner@example.test']);

        $this->post(route('login.store'), ['email' => 'owner@example.test', 'password' => 'password']);

        $this->assertAuthenticatedAs($admin);
    }
}
