<?php

namespace Tests\Feature\Learner;

use App\Enums\ResultsVisibility;
use App\Enums\RoleplayStatus;
use App\Enums\TestAttemptStatus;
use App\Models\AiUsage;
use App\Models\Attempt;
use App\Models\BlockCompletion;
use App\Models\Certificate;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\LessonCompletion;
use App\Models\PhrasebookItem;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\VoiceRecording;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The learner data models, their factories, the read methods the runner
 * calls and the owner-only policies (spec 0003 B.6, Part E; TEST-06, TIME-05,
 * RP-11, ROLE-02, SEC-01).
 */
class LearnerModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    // ----------------------------------------------------------- factories

    public function test_test_and_test_attempt_factories_create_rows()
    {
        $attempt = TestAttempt::factory()->create();

        $this->assertDatabaseCount('tests', 1);
        $this->assertDatabaseCount('test_attempts', 1);
        $this->assertSame(TestAttemptStatus::InProgress, $attempt->status);
        $this->assertTrue($attempt->user->hasRole('employee'));
    }

    public function test_ai_usage_factory_creates_a_row()
    {
        $usage = AiUsage::factory()->create();

        $this->assertDatabaseCount('ai_usages', 1);
        $this->assertSame($usage->prompt_tokens + $usage->completion_tokens, $usage->totalTokens());
        $this->assertSame(1, AiUsage::query()->today()->count());
    }

    public function test_block_and_lesson_completion_factories_create_rows()
    {
        $block = BlockCompletion::factory()->create();
        LessonCompletion::factory()->create();

        $this->assertDatabaseCount('block_completions', 1);
        $this->assertDatabaseCount('lesson_completions', 1);
        // The block belongs to the lesson the completion names.
        $this->assertSame($block->lesson_id, $block->block->lesson_id);
    }

    public function test_attempt_factory_creates_a_row_pinned_to_an_activity_version()
    {
        $attempt = Attempt::factory()->create();

        $this->assertDatabaseCount('attempts', 1);
        $this->assertSame($attempt->activity_id, $attempt->activityVersion->activity_id);
        $this->assertSame(['i1' => 'a'], $attempt->raw_answer);
        $this->assertSame(12, $attempt->timeTakenSeconds());
        $this->assertFalse($attempt->isTestAnswer());
    }

    public function test_attempt_in_a_test_sitting_is_unique_per_question()
    {
        $sitting = TestAttempt::factory()->create();
        $answer = Attempt::factory()->inTest($sitting)->create();

        $this->assertTrue($answer->isTestAnswer());
        $this->assertSame(1, Attempt::query()->inTests()->count());
        $this->assertSame(0, Attempt::query()->practice()->count());

        $this->expectException(QueryException::class);

        Attempt::factory()->inTest($sitting)->create(['activity_id' => $answer->activity_id]);
    }

    public function test_roleplay_attempt_factory_creates_rows_in_both_shapes()
    {
        $live = RoleplayAttempt::factory()->create();
        $done = RoleplayAttempt::factory()->completed()->create();
        RoleplayAttempt::factory()->preview()->create();

        $this->assertDatabaseCount('roleplay_attempts', 3);
        $this->assertSame(0, $live->turnsCount());
        $this->assertSame(70, $done->overall_score);
        $this->assertSame(60, $done->criteria_scores['grammar'] ?? null);
        $this->assertSame('Good try!', $done->feedback['summary_label'] ?? null);
        // Previews never count (RP-13).
        $this->assertSame(2, RoleplayAttempt::query()->counted()->count());
    }

    public function test_phrasebook_item_factory_creates_both_shapes()
    {
        $saved = PhrasebookItem::factory()->create();
        $custom = PhrasebookItem::factory()->custom('How can I help you?')->create();

        $this->assertFalse($saved->isCustom());
        $this->assertTrue($custom->isCustom());
        $this->assertSame(PhrasebookItem::hashFor('  how  can I help you? '), $custom->custom_hash);
    }

    public function test_voice_recording_and_certificate_factories_create_rows()
    {
        $recording = VoiceRecording::factory()->create();
        $certificate = Certificate::factory()->create();
        $revoked = Certificate::factory()->revoked()->create();

        $this->assertSame(5, $recording->durationSeconds());
        $this->assertNotNull($recording->recordable);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $certificate->verification_id);
        $this->assertFalse($certificate->isRevoked());
        $this->assertTrue($revoked->isRevoked());
        $this->assertSame(1, Certificate::query()->valid()->count());
    }

    public function test_a_certificate_gets_a_verification_id_when_none_is_given()
    {
        $certificate = Certificate::factory()->make(['verification_id' => null]);
        $certificate->save();

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $certificate->fresh()?->verification_id ?? '');
    }

    // ---------------------------------------------------------------- Test

    public function test_for_learner_matches_published_tests_of_the_department_shared_or_own_hotel()
    {
        $hotel = Hotel::factory()->create();
        $other = Hotel::factory()->create();
        $department = Department::factory()->create();
        $user = User::factory()->employee()->forHotel($hotel, $department)->create();

        $shared = Test::factory()->create(['department_id' => $department->id]);
        $own = Test::factory()->forHotel($hotel->id)->create(['department_id' => $department->id]);
        Test::factory()->forHotel($other->id)->create(['department_id' => $department->id]);
        Test::factory()->draft()->create(['department_id' => $department->id]);
        Test::factory()->create();

        $ids = Test::query()->forLearner($user)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$shared->id, $own->id], $ids);
    }

    public function test_for_learner_matches_nothing_for_a_user_without_a_department()
    {
        Test::factory()->create();
        $user = User::factory()->employee()->create();

        $this->assertSame(0, Test::query()->forLearner($user)->count());
    }

    public function test_settings_are_read_through_the_model_never_assumed()
    {
        $test = Test::factory()->create();
        $this->assertSame(1200, $test->timeLimitSeconds());
        $this->assertSame(ResultsVisibility::Score, $test->resultsVisibility());
        $this->assertFalse($test->shufflesQuestions());
        $this->assertNull($test->passScore());
        $this->assertSame('submit', $test->onTimeout());

        $bare = Test::factory()->post()->create(['settings' => null]);
        // Nothing configured means hidden (TEST-04) and, for a Post-test, no
        // timer.
        $this->assertSame(ResultsVisibility::Hidden, $bare->resultsVisibility());
        $this->assertNull($bare->timeLimitSeconds());

        $pre = Test::factory()->pre()->create(['settings' => null]);
        config(['guesvia.tests.pre_test_time_limit_seconds' => 900]);
        $this->assertSame(900, $pre->timeLimitSeconds());

        $this->assertNull(Test::factory()->untimed()->create()->timeLimitSeconds());
        $this->assertSame(
            ResultsVisibility::ScoreBreakdown,
            Test::factory()->withResultsVisibility(ResultsVisibility::ScoreBreakdown)->create()->resultsVisibility(),
        );
    }

    // --------------------------------------------------------- TestAttempt

    public function test_the_clock_is_computed_from_the_server_deadline()
    {
        Date::setTestNow(Date::parse('2026-09-18 10:00:00'));
        $attempt = TestAttempt::factory()->create([
            'started_at' => Date::now(),
            'deadline_at' => Date::now()->addMinutes(20),
        ]);

        $this->assertSame(1200, $attempt->remainingSeconds());
        $this->assertFalse($attempt->isExpired());
        $this->assertTrue($attempt->isOpen());

        // A refresh five minutes later reads the same clock (TIME-05).
        Date::setTestNow(Date::parse('2026-09-18 10:05:00'));
        $this->assertSame(900, $attempt->fresh()?->remainingSeconds());

        Date::setTestNow(Date::parse('2026-09-18 10:20:00'));
        $this->assertSame(0, $attempt->fresh()?->remainingSeconds());
        $this->assertTrue($attempt->fresh()?->isExpired());
        $this->assertFalse($attempt->fresh()?->isOpen());
    }

    public function test_an_untimed_sitting_never_expires()
    {
        $attempt = TestAttempt::factory()->untimed()->create(['started_at' => now()->subYears(2)]);

        $this->assertNull($attempt->remainingSeconds());
        $this->assertFalse($attempt->isExpired());
    }

    public function test_a_submitted_sitting_is_over_not_expired()
    {
        $attempt = TestAttempt::factory()->submitted()->create(['deadline_at' => now()->subHour()]);

        $this->assertFalse($attempt->isExpired());
        $this->assertTrue($attempt->isSubmitted());
        $this->assertTrue(TestAttempt::factory()->expired()->create()->isExpired());
    }

    public function test_score_summary_reports_percent_and_pass_mark()
    {
        $test = Test::factory()->create(['settings' => ['pass_score' => 70]]);

        $passed = TestAttempt::factory()->submitted(18, 25)->create(['test_id' => $test->id]);
        $this->assertSame(['score' => 18.0, 'max_score' => 25.0, 'percent' => 72, 'passed' => true], $passed->scoreSummary());

        $failed = TestAttempt::factory()->submitted(12, 25)->create(['test_id' => $test->id]);
        $this->assertSame(48, $failed->scoreSummary()['percent']);
        $this->assertFalse($failed->scoreSummary()['passed']);

        $unscored = TestAttempt::factory()->create(['test_id' => $test->id]);
        $this->assertSame(['score' => null, 'max_score' => null, 'percent' => null, 'passed' => null], $unscored->scoreSummary());

        $noPassMark = TestAttempt::factory()->submitted(20, 25)->create();
        $this->assertSame(80, $noPassMark->scoreSummary()['percent']);
        $this->assertNull($noPassMark->scoreSummary()['passed']);
    }

    // ----------------------------------------------------- RoleplayAttempt

    public function test_append_turn_persists_each_line_of_the_transcript()
    {
        $attempt = RoleplayAttempt::factory()->create();

        $attempt->appendTurn(RoleplayAttempt::ROLE_GUEST, 'Hello! I have a reservation for tonight.');
        $attempt->appendTurn(RoleplayAttempt::ROLE_EMPLOYEE, 'Good evening! May I have your name?', audioMediaId: null);

        $fresh = $attempt->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(2, $fresh->turnsCount());
        $this->assertSame(1, $fresh->employeeTurnsCount());
        $this->assertSame('guest', $fresh->transcript[0]['role']);
        $this->assertSame('Good evening! May I have your name?', $fresh->transcript[1]['text']);
        $this->assertArrayHasKey('at', $fresh->transcript[1]);
        $this->assertArrayNotHasKey('audio_media_id', $fresh->transcript[1]);
        $this->assertFalse($fresh->isFinished());
    }

    public function test_is_finished_covers_every_state_but_in_progress()
    {
        $this->assertFalse(RoleplayAttempt::factory()->create()->isFinished());
        $this->assertTrue(RoleplayAttempt::factory()->evaluating()->create()->isFinished());
        $this->assertTrue(RoleplayAttempt::factory()->completed()->create()->isFinished());
        $this->assertTrue(RoleplayAttempt::factory()->abandoned()->create()->isFinished());
        $this->assertTrue(RoleplayStatus::Completed->isFinished());
        $this->assertFalse(RoleplayStatus::InProgress->isFinished());
    }

    // ------------------------------------------------------------ policies

    public function test_test_policy_start_follows_the_learner_scope_and_the_single_attempt_rule()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();
        $learner = User::factory()->employee()->forHotel($hotel, $department)->create();
        $outsider = User::factory()->employee()->forHotel(Hotel::factory()->create(), Department::factory()->create())->create();
        $manager = User::factory()->manager()->forHotel($hotel, $department)->create();

        $pre = Test::factory()->pre()->create(['department_id' => $department->id]);
        $post = Test::factory()->post()->create(['department_id' => $department->id]);
        $draft = Test::factory()->pre()->draft()->create(['department_id' => $department->id]);

        $this->assertTrue(Gate::forUser($learner)->allows('view', $pre));
        $this->assertTrue(Gate::forUser($learner)->allows('start', $pre));
        $this->assertTrue(Gate::forUser($learner)->allows('start', $post));
        $this->assertTrue(Gate::forUser($learner)->denies('start', $draft));

        // Another department's employee, and a manager, get a 403 shape
        // (ROLE-02).
        $this->assertTrue(Gate::forUser($outsider)->denies('view', $pre));
        $this->assertTrue(Gate::forUser($outsider)->denies('start', $pre));
        $this->assertTrue(Gate::forUser($manager)->denies('start', $pre));

        // An in-progress sitting is resumable.
        $sitting = TestAttempt::factory()->create(['user_id' => $learner->id, 'test_id' => $pre->id]);
        $this->assertTrue(Gate::forUser($learner)->allows('start', $pre));

        // Once submitted, the Pre-test is closed for good (TEST-05)...
        $sitting->update(['status' => TestAttemptStatus::Submitted, 'submitted_at' => now()]);
        $this->assertTrue(Gate::forUser($learner)->denies('start', $pre));

        // ...but the Post-test is not single-attempt at the policy level.
        TestAttempt::factory()->submitted()->create(['user_id' => $learner->id, 'test_id' => $post->id]);
        $this->assertTrue(Gate::forUser($learner)->allows('start', $post));

        // The Super Admin passes everything (ROLE-03).
        $this->assertTrue(Gate::forUser(User::factory()->superAdmin()->create())->allows('start', $draft));
    }

    public function test_test_attempt_policy_is_owner_only_and_in_progress_for_updates()
    {
        $owner = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $attempt = TestAttempt::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $attempt));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $attempt));
        $this->assertTrue(Gate::forUser($other)->denies('view', $attempt));
        $this->assertTrue(Gate::forUser($other)->denies('update', $attempt));

        $attempt->update(['status' => TestAttemptStatus::Submitted]);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $attempt));
        $this->assertTrue(Gate::forUser($owner)->denies('update', $attempt));
    }

    public function test_attempt_policy_is_owner_only()
    {
        $owner = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $attempt = new Attempt(['user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $attempt));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $attempt));
        $this->assertTrue(Gate::forUser($other)->denies('view', $attempt));
        $this->assertTrue(Gate::forUser($other)->denies('update', $attempt));
    }

    public function test_roleplay_attempt_policy_is_owner_only_and_messages_need_an_open_conversation()
    {
        $owner = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();
        $attempt = new RoleplayAttempt(['user_id' => $owner->id, 'status' => RoleplayStatus::InProgress]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $attempt));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $attempt));
        $this->assertTrue(Gate::forUser($owner)->allows('message', $attempt));
        $this->assertTrue(Gate::forUser($other)->denies('view', $attempt));
        // A manager never reads a transcript through the policy (ROLE-04).
        $this->assertTrue(Gate::forUser($manager)->denies('view', $attempt));

        $attempt->status = RoleplayStatus::Evaluating;
        $this->assertTrue(Gate::forUser($owner)->allows('view', $attempt));
        $this->assertTrue(Gate::forUser($owner)->denies('message', $attempt));
    }

    public function test_phrasebook_item_policy_is_owner_only()
    {
        $owner = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $item = new PhrasebookItem(['user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $item));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $item));
        $this->assertTrue(Gate::forUser($other)->denies('view', $item));
        $this->assertTrue(Gate::forUser($other)->denies('delete', $item));
    }
}
