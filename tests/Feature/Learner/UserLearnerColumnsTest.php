<?php

namespace Tests\Feature\Learner;

use App\Enums\AccountStatus;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The learner columns on `users` and the journey helpers that read them
 * (spec 0003 B.1; AUTH-01, AUTH-04, PRIV-01, PRIV-02, REM-05, REP-07,
 * JOURNEY-01).
 */
class UserLearnerColumnsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------- schema

    public function test_the_learner_columns_exist()
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'username',
            'email_consent_at',
            'first_login_completed_at',
            'research_notice_acknowledged_at',
            'last_login_at',
            'last_activity_at',
            'training_started_at',
            'training_completed_at',
            'participant_code',
            'cohort',
            'created_by',
        ]));
    }

    public function test_email_is_optional_and_may_be_empty_on_several_accounts()
    {
        // An employee signs in by username and may have no email until first
        // login (AUTH-01, AUTH-04). NULL repeats in a unique index.
        $first = User::factory()->withUsername()->create(['email' => null]);
        $second = User::factory()->withUsername()->create(['email' => null]);

        $this->assertNull($first->fresh()?->email);
        $this->assertNull($second->fresh()?->email);
        $this->assertSame(2, User::whereNull('email')->count());
    }

    public function test_email_stays_unique_when_present()
    {
        User::factory()->create(['email' => 'meriem@guesvia.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'meriem@guesvia.test']);
    }

    public function test_username_is_unique()
    {
        User::factory()->create(['username' => 'samira']);

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'samira']);
    }

    public function test_the_seeder_shape_still_inserts_users_with_an_email_and_no_username()
    {
        // HotelPortfolioSeeder bulk inserts rows with email only.
        User::insert([
            'name' => 'Seeded Employee',
            'email' => 'seeded.employee@guesvia.test',
            'password' => 'x',
            'status' => AccountStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::where('email', 'seeded.employee@guesvia.test')->firstOrFail();

        $this->assertNull($user->username);
        $this->assertNull($user->participant_code);
    }

    // ---------------------------------------------------------- factories

    public function test_factory_states_fill_the_learner_columns()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();

        $user = User::factory()
            ->employee()
            ->withUsername()
            ->consented()
            ->firstLoginDone()
            ->forHotel($hotel, $department)
            ->create();

        $this->assertNotNull($user->username);
        $this->assertMatchesRegularExpression('/^[a-z0-9._-]{3,40}$/', (string) $user->username);
        $this->assertNotNull($user->email_consent_at);
        $this->assertNotNull($user->first_login_completed_at);
        $this->assertNotNull($user->research_notice_acknowledged_at);
        $this->assertSame($hotel->id, $user->hotel_id);
        $this->assertSame($department->id, $user->department_id);
        $this->assertTrue($user->hasRole('employee'));
    }

    public function test_a_given_username_is_used_as_is()
    {
        $user = User::factory()->withUsername('samira')->create();

        $this->assertSame('samira', $user->username);
    }

    // ------------------------------------------------------ participant code

    public function test_participant_codes_have_the_export_shape_and_never_repeat()
    {
        $codes = [];

        for ($i = 0; $i < 25; $i++) {
            $code = User::generateParticipantCode();
            $this->assertMatchesRegularExpression('/^P-[A-Z0-9]{6}$/', $code);
            $codes[] = $code;

            User::factory()->create(['participant_code' => $code]);
        }

        $this->assertCount(25, array_unique($codes));
    }

    public function test_participant_code_is_unique_in_the_database()
    {
        User::factory()->create(['participant_code' => 'P-ABC123']);

        $this->expectException(QueryException::class);

        User::factory()->create(['participant_code' => 'P-ABC123']);
    }

    // ------------------------------------------------------------ helpers

    public function test_first_login_and_consent_helpers_read_the_timestamps()
    {
        $fresh = User::factory()->create();
        $this->assertFalse($fresh->hasCompletedFirstLogin());
        $this->assertFalse($fresh->consentsToReminders());

        $done = User::factory()->consented()->firstLoginDone()->create();
        $this->assertTrue($done->hasCompletedFirstLogin());
        $this->assertTrue($done->consentsToReminders());

        // Revocation clears the timestamp, so the answer is always current
        // (PRIV-02, REM-05).
        $done->update(['email_consent_at' => null]);
        $this->assertFalse($done->fresh()?->consentsToReminders());
    }

    public function test_consent_without_an_email_address_cannot_be_acted_on()
    {
        $user = User::factory()->consented()->create(['email' => null]);

        $this->assertFalse($user->consentsToReminders());
    }

    public function test_the_employees_and_active_scopes()
    {
        User::factory()->employee()->count(2)->create();
        User::factory()->employee()->create(['status' => AccountStatus::Inactive]);
        User::factory()->manager()->create();

        $this->assertSame(3, User::query()->employees()->count());
        $this->assertSame(2, User::query()->employees()->active()->count());
    }

    // --------------------------------------------------------- pre-test gate

    public function test_has_submitted_pre_test_flips_after_a_submitted_pre_attempt()
    {
        [$user, $test] = $this->learnerWithPreTest();

        $this->assertFalse($user->hasSubmittedPreTest());

        $attempt = TestAttempt::factory()->create(['user_id' => $user->id, 'test_id' => $test->id]);
        // In progress is not submitted: gate 1 stays shut (JOURNEY-01).
        $this->assertFalse($user->hasSubmittedPreTest());

        $attempt->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->assertTrue($user->fresh()?->hasSubmittedPreTest());
    }

    public function test_an_expired_pre_attempt_does_not_open_the_gate()
    {
        [$user, $test] = $this->learnerWithPreTest();

        TestAttempt::factory()->expired()->create(['user_id' => $user->id, 'test_id' => $test->id]);

        $this->assertFalse($user->hasSubmittedPreTest());
    }

    public function test_a_submitted_post_test_does_not_count_as_the_pre_test()
    {
        [$user, $test] = $this->learnerWithPreTest();
        $post = Test::factory()->post()->create(['department_id' => $test->department_id]);

        TestAttempt::factory()->submitted()->create(['user_id' => $user->id, 'test_id' => $post->id]);

        $this->assertFalse($user->hasSubmittedPreTest());
    }

    public function test_a_submitted_pre_test_of_another_department_does_not_count()
    {
        [$user] = $this->learnerWithPreTest();
        $elsewhere = Test::factory()->pre()->create();

        TestAttempt::factory()->submitted()->create(['user_id' => $user->id, 'test_id' => $elsewhere->id]);

        $this->assertFalse($user->hasSubmittedPreTest());
    }

    public function test_a_submitted_pre_test_of_another_hotel_does_not_count()
    {
        [$user, $test] = $this->learnerWithPreTest();
        $otherHotel = Hotel::factory()->create();
        $elsewhere = Test::factory()->pre()->forHotel($otherHotel->id)->create(['department_id' => $test->department_id]);

        TestAttempt::factory()->submitted()->create(['user_id' => $user->id, 'test_id' => $elsewhere->id]);

        $this->assertFalse($user->hasSubmittedPreTest());
    }

    public function test_a_hotel_specific_pre_test_of_the_users_own_hotel_counts()
    {
        [$user, $test] = $this->learnerWithPreTest();
        /** @var int $hotelId */
        $hotelId = $user->hotel_id;
        $own = Test::factory()->pre()->forHotel($hotelId)->create(['department_id' => $test->department_id]);

        TestAttempt::factory()->submitted()->create(['user_id' => $user->id, 'test_id' => $own->id]);

        $this->assertTrue($user->hasSubmittedPreTest());
    }

    // ------------------------------------------------------------- helpers

    /**
     * An employee in a hotel and department, plus the shared, published
     * Pre-test for that department.
     *
     * @return array{0: User, 1: Test}
     */
    private function learnerWithPreTest(): array
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();
        $user = User::factory()->employee()->forHotel($hotel, $department)->create();
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);

        return [$user, $test];
    }
}
