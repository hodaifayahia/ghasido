<?php

namespace Tests\Feature\Learn;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\PronunciationAttempt;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client report 2026-11-05: a learner who logs out and comes back finds
 * their answers, results and AI feedback; every try is its own attempt and a
 * new try never erases an old one; they can look back and compare (PROG-01,
 * PROG-04, TEST-06, DATA-01..05, AIE-04, RP-11). Another learner's attempt is
 * a 403 (ROLE-02, SEC-01). The score of a test sitting shows only as far as
 * the admin allows (TEST-04).
 */
class AttemptHistoryTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected Lesson $lesson;

    protected Block $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        $this->lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $this->block = $this->lesson->visibleBlocks()->firstOrFail();
    }

    // ------------------------------------------------------------ practice

    public function test_a_saved_answer_and_its_ai_feedback_come_back_after_logging_out_and_in_again()
    {
        Queue::fake();
        $learner = $this->learner();
        $placement = $this->place(ActivityType::Writing);

        $this->actingAs($learner)
            ->post($this->answerUrl($placement), [
                'answers' => ['i1' => ['text' => 'Dear guest, the double room is 12,000 DZD per night.']],
                'submission_key' => 'first-try',
            ])
            ->assertRedirect($this->activityUrl($placement));

        // The queued evaluation lands on the same row (AIE-04).
        $attempt = Attempt::query()->sole();
        $attempt->forceFill([
            'ai_status' => GenerationStatus::Done,
            'score' => 72,
            'max_score' => 100,
            'ai_feedback' => [
                'kind' => 'writing',
                'summary' => 'Clear and polite. Add the dates.',
                'criteria' => ['task_completion' => ['score' => 3, 'comment' => 'Most of the task.']],
            ],
        ])->save();

        // A brand new session: log out, then sign in again by username.
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => 'samira', 'password' => 'password']);
        $this->assertAuthenticatedAs($learner);

        $this->get($this->activityUrl($placement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/lesson/Activity')
                ->where('result.attemptId', $attempt->id)
                ->where('result.attemptNo', 1)
                ->where('result.answers.i1.text', 'Dear guest, the double room is 12,000 DZD per night.')
                ->where('result.aiStatus', 'done')
                ->where('result.feedback.summary', 'Clear and polite. Add the dates.')
                ->where('result.feedback.criteria.0.comment', 'Most of the task.')
                ->has('attempts', 1)
                ->where('attempts.0.id', $attempt->id)
                ->where('attempts.0.answer', 'Dear guest, the double room is 12,000 DZD per night.')
                ->where('attempts.0.feedback', 'Clear and polite. Add the dates.')
                ->where('attempts.0.score', 72)
            );
    }

    public function test_a_second_attempt_is_a_new_row_and_keeps_the_first()
    {
        $learner = $this->learner();
        $placement = $this->place(ActivityType::MultipleChoice);

        $this->actingAs($learner)->post($this->answerUrl($placement), [
            'answers' => ['i1' => 'B'],
            'submission_key' => 'try-one',
        ]);
        $this->actingAs($learner)->post($this->answerUrl($placement), [
            'answers' => ['i1' => 'A'],
            'submission_key' => 'try-two',
        ]);

        $attempts = Attempt::query()->where('user_id', $learner->id)->orderBy('attempt_no')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame([1, 2], $attempts->pluck('attempt_no')->all());
        // The first answer is untouched by the second (TEST-06, DATA-01).
        $this->assertSame(['i1' => 'B'], $attempts[0]->raw_answer);
        $this->assertFalse($attempts[0]->is_correct);
        $this->assertSame(['i1' => 'A'], $attempts[1]->raw_answer);
        $this->assertTrue($attempts[1]->is_correct);

        // Reopened: the latest attempt is shown, both are listed to compare.
        $this->actingAs($learner)
            ->get($this->activityUrl($placement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.attemptNo', 2)
                ->where('result.isCorrect', true)
                ->has('attempts', 2)
                ->where('attempts.0.attemptNo', 2)
                ->where('attempts.1.attemptNo', 1)
                ->where('attempts.1.isCorrect', false)
                ->where('attempts.1.url', $this->activityUrl($placement, $attempts[0]->id))
            );

        // The earlier attempt opens on its own, with its own answer.
        $this->actingAs($learner)
            ->get($this->activityUrl($placement, $attempts[0]->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.attemptId', $attempts[0]->id)
                ->where('result.answers.i1', 'B')
                ->where('result.isCorrect', false)
            );
    }

    public function test_a_retried_request_of_the_same_submission_writes_no_second_attempt()
    {
        $learner = $this->learner();
        $placement = $this->place(ActivityType::MultipleChoice);

        foreach ([1, 2] as $ignored) {
            $this->actingAs($learner)
                ->post($this->answerUrl($placement), [
                    'answers' => ['i1' => 'A'],
                    'submission_key' => 'same-check',
                ])
                ->assertRedirect($this->activityUrl($placement));
        }

        $this->assertSame(1, Attempt::query()->where('user_id', $learner->id)->count());
    }

    public function test_another_learners_attempt_is_forbidden_in_the_same_hotel_and_across_hotels()
    {
        $owner = $this->learner();
        $placement = $this->place(ActivityType::MultipleChoice);

        $this->actingAs($owner)->post($this->answerUrl($placement), ['answers' => ['i1' => 'A']]);
        $attempt = Attempt::query()->sole();

        $coworker = $this->learner(['username' => 'amine']);

        $this->actingAs($coworker)
            ->get($this->activityUrl($placement, $attempt->id))
            ->assertForbidden();

        // The coworker's own page shows nothing of the owner's tries.
        $this->actingAs($coworker)
            ->get($this->activityUrl($placement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', null)
                ->has('attempts', 0)
            );

        $outsider = User::factory()
            ->employee()
            ->firstLoginDone()
            ->forHotel(Hotel::factory()->create(), $this->department)
            ->create(['username' => 'outsider']);

        $this->actingAs($outsider)
            ->get($this->activityUrl($placement, $attempt->id))
            ->assertForbidden();
    }

    // ---------------------------------------------------- listen & repeat

    public function test_saved_pronunciation_checks_come_back_on_the_listen_and_repeat_step()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::ListenRepeat, BlockType::Complete]);
        $step = $lesson->visibleBlocks()->firstOrFail();

        $first = $this->check($learner, $lesson, $step, 1, 55);
        $second = $this->check($learner, $lesson, $step, 2, 88);
        $this->check($this->learner(['username' => 'amine']), $lesson, $step, 1, 99);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $step]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/lesson/Step')
                ->has('pronunciationHistory', 2)
                ->where('pronunciationHistory.0.id', $second->id)
                ->where('pronunciationHistory.0.attemptNo', 2)
                ->where('pronunciationHistory.0.score', 88)
                ->where('pronunciationHistory.0.item', 0)
                ->where('pronunciationHistory.1.id', $first->id)
            );

        // Another learner cannot read a check through its poll URL.
        $this->actingAs($this->learner(['username' => 'karim']))
            ->getJson(route('learn.pronunciation.show', $first))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ role-play

    public function test_the_role_play_brief_lists_every_earlier_conversation_with_its_feedback_link()
    {
        $learner = $this->learner();
        $lesson = $this->publishedLesson([BlockType::AiRoleplay, BlockType::Complete]);
        $step = $lesson->visibleBlocks()->firstOrFail();
        $scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
            'attempts_allowed' => 3,
        ]);
        $step->scenarios()->attach($scenario->id, ['position' => 1]);

        $first = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $scenario->id,
            'attempt_no' => 1,
            'status' => RoleplayStatus::Completed,
            'ai_status' => GenerationStatus::Done,
            'overall_score' => 64,
            'is_preview' => false,
        ]);
        $second = RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $scenario->id,
            'attempt_no' => 2,
            'status' => RoleplayStatus::Completed,
            'ai_status' => GenerationStatus::Done,
            'overall_score' => 81,
            'is_preview' => false,
        ]);
        // An admin preview run is not the learner's (RP-13).
        RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $scenario->id,
            'is_preview' => true,
        ]);

        $this->actingAs($learner)
            ->get(route('learn.roleplay.ready', ['lesson' => $lesson, 'block' => $step, 'scenario' => $scenario]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('attempts', 2)
                ->where('attempts.0.id', $second->id)
                ->where('attempts.0.score', 81)
                ->where('attempts.1.id', $first->id)
                ->where('attempts.1.score', 64)
                ->where('attempts.1.url', route('learn.roleplay.feedback', ['attempt' => $first->id]))
            );

        // Both feedback pages stay open to their owner, closed to others.
        $this->actingAs($learner)->get(route('learn.roleplay.feedback', ['attempt' => $first->id]))->assertOk();
        $this->actingAs($this->learner(['username' => 'amine']))
            ->get(route('learn.roleplay.feedback', ['attempt' => $first->id]))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- tests

    public function test_every_finished_sitting_is_listed_and_its_score_follows_the_visibility_setting()
    {
        $learner = $this->learner();
        $test = $this->publishedPreTest();

        $old = TestAttempt::factory()->submitted()->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
            'attempt_no' => 1,
            'score' => 4,
            'max_score' => 10,
            'submitted_at' => now()->subDay(),
        ]);
        $new = TestAttempt::factory()->submitted()->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
            'attempt_no' => 2,
            'score' => 8,
            'max_score' => 10,
        ]);

        $resultUrl = route('learn.tests.result', ['test' => $test->id, 'attempt' => $new->id]);

        $test->forceFill(['settings' => [...($test->settings ?? []), 'results_visibility' => 'score']])->save();

        $this->actingAs($learner)
            ->get($resultUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sittings', 2)
                ->where('sittings.0.id', $new->id)
                ->where('sittings.0.percent', 80)
                ->where('sittings.1.id', $old->id)
                ->where('sittings.1.percent', 40)
                ->where('currentAttemptId', $new->id)
            );

        // My Progress links back to the saved result page.
        $this->actingAs($learner)
            ->get(route('learn.progress'))
            ->assertInertia(fn (Assert $page) => $page->where('tests.pre.url', $resultUrl));

        // Hidden results: the sittings are listed, never their scores.
        $test->forceFill(['settings' => [...($test->settings ?? []), 'results_visibility' => 'hidden']])->save();

        $this->actingAs($learner)
            ->get($resultUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('result', null)
                ->has('sittings', 2)
                ->where('sittings.0.percent', null)
                ->where('sittings.1.percent', null)
            );

        // Another learner's sitting is a 403.
        $this->actingAs($this->learner(['username' => 'amine']))
            ->get($resultUrl)
            ->assertForbidden();
    }

    // -------------------------------------------------------------- helpers

    private function place(ActivityType $type): ActivityPlacement
    {
        $activity = Activity::factory()->ofType($type)->create();

        return ActivityPlacement::factory()->in($this->block)->create(['activity_id' => $activity->id]);
    }

    private function activityUrl(ActivityPlacement $placement, ?int $attemptId = null): string
    {
        return route('learn.lessons.activity', array_filter([
            'lesson' => $this->lesson,
            'block' => $this->block,
            'placement' => $placement,
            'attempt' => $attemptId,
        ]));
    }

    private function answerUrl(ActivityPlacement $placement): string
    {
        return route('learn.lessons.activity.answer', ['lesson' => $this->lesson, 'block' => $this->block, 'placement' => $placement]);
    }

    private function check(User $learner, Lesson $lesson, Block $step, int $attemptNo, int $score): PronunciationAttempt
    {
        $check = PronunciationAttempt::factory()->create([
            'user_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'block_id' => $step->id,
            'item_index' => 0,
            'attempt_no' => $attemptNo,
        ]);
        $check->forceFill([
            'status' => GenerationStatus::Done,
            'score' => $score,
            'level' => 'good',
        ])->save();

        return $check;
    }
}
