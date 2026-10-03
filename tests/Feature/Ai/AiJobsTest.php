<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiEvaluation;
use App\Contracts\AiProvider;
use App\Contracts\AiReply;
use App\Contracts\AiUsageInfo;
use App\Contracts\CoachingSummary;
use App\Contracts\CourseOutline;
use App\Contracts\DashboardBriefingDraft;
use App\Contracts\InterfaceTranslationDraft;
use App\Contracts\LessonDraft;
use App\Contracts\LexiconDraft;
use App\Contracts\PronunciationCoaching;
use App\Contracts\PronunciationGuideDraft;
use App\Contracts\ReminderDraft;
use App\Contracts\ScenarioDraft;
use App\Contracts\SpeakingEvaluation;
use App\Contracts\TestQuestionsDraft;
use App\Contracts\TextTranslationDraft;
use App\Contracts\WritingEvaluation;
use App\Enums\Accent;
use App\Enums\ActivityType;
use App\Enums\AiFeature;
use App\Enums\EnglishLevel;
use App\Enums\GenerationStatus;
use App\Enums\LexiconKind;
use App\Enums\RoleplayStatus;
use App\Enums\ScenarioDifficulty;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\EvaluateWrittenAnswer;
use App\Jobs\GenerateLexiconDraft;
use App\Jobs\GenerateRoleplayReply;
use App\Models\Activity;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\Department;
use App\Models\LexiconItem;
use App\Models\RoleplayAttempt;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The four AI jobs against the fake provider (spec 0003 Part C): each one
 * walks its owning row's `ai_status` to a terminal state, stores the
 * structured result and meters exactly one call (PERF-04, AIE-04, AIL-04).
 *
 * phpunit.xml runs the queue synchronously, so dispatch() executes inline
 * and a failing handle() reaches failed() the way a worker would.
 */
class AiJobsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------- lexicon

    public function test_generate_lexicon_draft_stores_a_draft_without_touching_the_live_columns()
    {
        $admin = User::factory()->superAdmin()->create();
        $department = Department::factory()->create(['name' => 'Housekeeping']);
        $item = LexiconItem::factory()->create([
            'english_text' => 'Towel',
            'arabic_meaning' => 'منشفة',
            'department_id' => $department->id,
            'created_by' => $admin->id,
            'ai_status' => GenerationStatus::Pending,
        ]);

        GenerateLexiconDraft::dispatch($item->id);

        $item->refresh();
        $this->assertSame(GenerationStatus::Done, $item->ai_status);
        $this->assertNotNull($item->ai_draft);
        $this->assertSame('n', $item->ai_draft['part_of_speech']);
        $this->assertStringContainsString('Housekeeping', $item->ai_draft['hotel_example']);
        // A draft, never auto-published (GEN-03).
        $this->assertSame('منشفة', $item->arabic_meaning);
        $this->assertTrue($item->hasPendingDraft());

        $this->assertDatabaseHas('ai_usages', [
            'feature' => AiFeature::LexiconGenerate->value,
            'user_id' => $admin->id,
            'provider' => 'fake',
        ]);
        $this->assertDatabaseCount('ai_usages', 1);
    }

    public function test_generate_lexicon_draft_for_a_missing_item_is_a_no_op()
    {
        GenerateLexiconDraft::dispatch(999_999);

        $this->assertDatabaseCount('ai_usages', 0);
    }

    // ------------------------------------------------------------ role-play

    public function test_generate_roleplay_reply_appends_the_guest_line_and_clears_pending()
    {
        $attempt = RoleplayAttempt::factory()->create([
            'ai_scenario_id' => AiScenario::factory()->published()->create(['slug' => 'check-in'])->id,
            'pending_reply' => true,
        ]);

        GenerateRoleplayReply::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertCount(1, $attempt->transcript);
        $this->assertSame(RoleplayAttempt::ROLE_GUEST, $attempt->transcript[0]['role']);
        $this->assertSame('Hello! I have a reservation for tonight.', $attempt->transcript[0]['text']);
        $this->assertArrayHasKey('at', $attempt->transcript[0]);
        $this->assertFalse($attempt->pending_reply);
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        $this->assertSame(RoleplayStatus::InProgress, $attempt->status);

        $this->assertDatabaseHas('ai_usages', [
            'feature' => AiFeature::RoleplayTurn->value,
            'user_id' => $attempt->user_id,
        ]);
    }

    public function test_generate_roleplay_reply_walks_the_script_as_the_employee_answers()
    {
        $attempt = RoleplayAttempt::factory()->create([
            'ai_scenario_id' => AiScenario::factory()->published()->create(['slug' => 'check-in'])->id,
            'transcript' => [
                ['role' => 'guest', 'text' => 'Hello! I have a reservation for tonight.', 'at' => '2026-09-18T10:00:00+00:00'],
                ['role' => 'employee', 'text' => 'Good evening. May I have your name, please?', 'at' => '2026-09-18T10:00:20+00:00'],
            ],
            'pending_reply' => true,
        ]);

        GenerateRoleplayReply::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertCount(3, $attempt->transcript);
        $this->assertSame("Yes, it's John Miller.", $attempt->transcript[2]['text']);
    }

    public function test_a_finished_attempt_gets_no_reply_and_no_metered_call()
    {
        $attempt = RoleplayAttempt::factory()->evaluating()->create(['pending_reply' => true]);
        $before = $attempt->transcript;

        GenerateRoleplayReply::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertSame($before, $attempt->transcript);
        $this->assertFalse($attempt->pending_reply);
        $this->assertDatabaseCount('ai_usages', 0);
    }

    public function test_evaluate_roleplay_attempt_writes_scores_feedback_and_completes()
    {
        $attempt = RoleplayAttempt::factory()->evaluating()->create([
            'transcript' => [
                ['role' => 'guest', 'text' => 'Hello! I have a reservation for tonight.', 'at' => '2026-09-18T10:00:00+00:00'],
                ['role' => 'employee', 'text' => 'Good evening. May I have your name, please?', 'at' => '2026-09-18T10:00:20+00:00'],
            ],
            'started_at' => now()->subMinutes(3),
            'ended_at' => null,
            'duration_ms' => null,
        ]);
        $attempt->user?->forceFill(['english_level' => EnglishLevel::Beginner])->save();

        EvaluateRoleplayAttempt::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertSame(RoleplayStatus::Completed, $attempt->status);
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        $this->assertSame(70, $attempt->overall_score);
        // The bar the score was judged against is kept (spec 0005 §5.2).
        $this->assertSame(EnglishLevel::Beginner, $attempt->graded_level);
        $this->assertSame(
            ['pronunciation' => 70, 'grammar' => 60, 'vocabulary' => 80, 'fluency' => 70, 'politeness' => 80],
            $attempt->criteria_scores,
        );
        $this->assertNotNull($attempt->feedback);
        $this->assertSame('Good try!', $attempt->feedback['summary_label']);
        $this->assertSame('Your room is on the fifth floor.', $attempt->feedback['better_expression']['better']);
        $this->assertCount(4, $attempt->feedback['did_well']);
        $this->assertNotNull($attempt->ended_at);
        $this->assertGreaterThan(0, $attempt->duration_ms);
        $this->assertTrue($attempt->isEvaluated());

        $this->assertDatabaseHas('ai_usages', [
            'feature' => AiFeature::RoleplayEval->value,
            'user_id' => $attempt->user_id,
        ]);
        $this->assertDatabaseCount('ai_usages', 1);
    }

    public function test_an_already_completed_attempt_is_not_evaluated_twice()
    {
        $attempt = RoleplayAttempt::factory()->completed()->create();

        EvaluateRoleplayAttempt::dispatch($attempt->id);

        $this->assertDatabaseCount('ai_usages', 0);
    }

    // -------------------------------------------------------------- writing

    public function test_evaluate_written_answer_attaches_the_verdict_and_the_score()
    {
        $attempt = $this->writingAttempt('Dear guest, the double room is 12000 DZD per night with breakfast.');

        EvaluateWrittenAnswer::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        $this->assertNotNull($attempt->ai_feedback);
        $this->assertSame(['task_completion', 'accuracy', 'politeness', 'clarity'], array_keys($attempt->ai_feedback['criteria']));
        $this->assertStringContainsString('12,000 DZD', $attempt->ai_feedback['better_answer']);
        $this->assertSame('77.50', $attempt->score);
        $this->assertSame('100.00', $attempt->max_score);
        // Writing is never auto-marked right or wrong (spec 0003 B.9).
        $this->assertNull($attempt->is_correct);

        $this->assertDatabaseHas('ai_usages', [
            'feature' => AiFeature::WritingEval->value,
            'user_id' => $attempt->user_id,
        ]);
    }

    public function test_a_test_answer_is_graded_on_the_fixed_scale_and_practice_at_the_learners_level()
    {
        // The level moves after every sitting, so Pre- and Post-test answers
        // are judged on one fixed scale and compare (TEST-02, TSTM-03; spec
        // 0005 §5.2); lesson practice is judged at the learner's level. The
        // bar used is stored beside the score.
        $levels = [];
        $this->mock(AiProvider::class, function (MockInterface $mock) use (&$levels): void {
            $mock->shouldReceive('evaluateWriting')
                ->twice()
                ->andReturnUsing(function (array $item, string $answer, ?EnglishLevel $level) use (&$levels): WritingEvaluation {
                    $levels[] = $level;

                    return new WritingEvaluation(
                        criteria: ['task_completion' => ['score' => 80, 'comment' => 'ok']],
                        betterAnswer: 'Dear guest, welcome.',
                        summary: 'Good.',
                        usage: new AiUsageInfo(promptTokens: 1, completionTokens: 1, model: 'm', provider: 'fake'),
                    );
                });
        });

        $learner = User::factory()->employee()->create(['english_level' => EnglishLevel::Intermediate]);
        $sitting = TestAttempt::factory()->create(['user_id' => $learner->id]);
        $testAnswer = $this->writingAttempt('Dear guest, welcome.', ['user_id' => $learner->id, 'test_attempt_id' => $sitting->id]);
        $practice = $this->writingAttempt('Dear guest, welcome.', ['user_id' => $learner->id]);

        EvaluateWrittenAnswer::dispatch($testAnswer->id);
        EvaluateWrittenAnswer::dispatch($practice->id);

        $this->assertSame([null, EnglishLevel::Intermediate], $levels);
        $this->assertNull($testAnswer->fresh()?->graded_level);
        $this->assertSame(EnglishLevel::Intermediate, $practice->fresh()?->graded_level);
    }

    public function test_evaluate_written_answer_never_overwrites_an_overridden_score()
    {
        $admin = User::factory()->superAdmin()->create();
        $attempt = $this->writingAttempt('Dear guest, welcome.', [
            'score' => 95,
            'original_score' => 40,
            'score_overridden_by' => $admin->id,
        ]);

        EvaluateWrittenAnswer::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertSame('95.00', $attempt->score);
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        $this->assertNotNull($attempt->ai_feedback);
    }

    // -------------------------------------------------------------- failure

    public function test_a_failing_provider_leaves_a_failed_state_the_page_can_show()
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            public function roleplayReply(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiReply
            {
                throw new RuntimeException('provider down');
            }

            public function evaluateRoleplay(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiEvaluation
            {
                throw new RuntimeException('provider down');
            }

            public function generateLexicon(string $english, LexiconKind $kind, string $context): LexiconDraft
            {
                throw new RuntimeException('provider down');
            }

            public function generateScenario(string $title, string $department, ScenarioDifficulty $difficulty, string $notes = ''): ScenarioDraft
            {
                throw new RuntimeException('provider down');
            }

            public function generateLesson(string $topic, string $department, string $level, string $notes = ''): LessonDraft
            {
                throw new RuntimeException('provider down');
            }

            public function generateCourseOutline(string $brief, string $department, string $level, int $lessonCount): CourseOutline
            {
                throw new RuntimeException('provider down');
            }

            public function evaluateWriting(array $item, string $answer, ?EnglishLevel $level = null): WritingEvaluation
            {
                throw new RuntimeException('provider down');
            }

            public function coachLearner(array $context, ?EnglishLevel $level = null): CoachingSummary
            {
                throw new RuntimeException('provider down');
            }

            public function briefDashboard(array $context): DashboardBriefingDraft
            {
                throw new RuntimeException('provider down');
            }

            public function draftReminder(string $purpose, string $tone, array $variables): ReminderDraft
            {
                throw new RuntimeException('provider down');
            }

            public function pronunciationGuide(string $text, Accent $accent): PronunciationGuideDraft
            {
                throw new RuntimeException('provider down');
            }

            public function coachPronunciation(array $result, array $words, Accent $accent, ?EnglishLevel $level = null): PronunciationCoaching
            {
                throw new RuntimeException('provider down');
            }

            public function translateText(string $english, string $language = 'Arabic'): TextTranslationDraft
            {
                throw new RuntimeException('provider down');
            }

            public function translateInterfaceStrings(array $strings, string $language): InterfaceTranslationDraft
            {
                throw new RuntimeException('provider down');
            }

            public function evaluateSpeaking(array $item, string $transcript, ?EnglishLevel $level = null): SpeakingEvaluation
            {
                throw new RuntimeException('provider down');
            }

            public function generateTestQuestions(string $department, string $level, array $skills, string $notes = '', array $avoid = []): TestQuestionsDraft
            {
                throw new RuntimeException('provider down');
            }
        });

        $attempt = RoleplayAttempt::factory()->create(['pending_reply' => true]);
        $item = LexiconItem::factory()->create(['ai_status' => GenerationStatus::Pending]);

        try {
            GenerateRoleplayReply::dispatch($attempt->id);
            $this->fail('The provider failure did not surface.');
        } catch (RuntimeException $e) {
            $this->assertSame('provider down', $e->getMessage());
        }

        try {
            GenerateLexiconDraft::dispatch($item->id);
            $this->fail('The provider failure did not surface.');
        } catch (RuntimeException) {
        }

        $attempt->refresh();
        $this->assertFalse($attempt->pending_reply);
        $this->assertSame(GenerationStatus::Failed, $attempt->ai_status);
        $this->assertSame('provider down', $attempt->failed_reason);
        $this->assertSame([], $attempt->transcript);

        $this->assertSame(GenerationStatus::Failed, $item->refresh()->ai_status);
        $this->assertDatabaseCount('ai_usages', 0);
    }

    // -------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function writingAttempt(string $text, array $overrides = []): Attempt
    {
        $activity = Activity::factory()->create([
            'type' => ActivityType::Writing,
            'skill_label' => 'Writing',
            'prompt' => 'Write a short reply.',
            // Activity::booted() snapshots $this->current_version on create;
            // the factory leaves it to the DB default, which is not hydrated
            // in memory, so version 1 must be explicit here (integrator note).
            'current_version' => 1,
            'payload' => ['items' => [[
                'id' => 'i1',
                'scenario' => 'A guest emails to ask about room rates for two nights in July.',
                'request_text' => 'Hello, could you tell me the price of a double room for 12–14 July, including breakfast? Thank you.',
                'information' => ['Double room: 12,000 DZD per night', 'Breakfast: included', 'Tax: 19% VAT included'],
                'min_words' => 20,
            ]]],
        ]);

        return Attempt::factory()->create([
            'activity_id' => $activity->id,
            'raw_answer' => ['i1' => ['text' => $text]],
            'is_correct' => null,
            'score' => null,
            'max_score' => null,
            'ai_status' => GenerationStatus::Pending,
            ...$overrides,
        ]);
    }
}
