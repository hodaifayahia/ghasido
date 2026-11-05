<?php

namespace Tests\Feature\Learn\Roleplay;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\TranslateText;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Learning\RoleplayFeedbackMeaning;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Client request 2026-11-05: the role-play feedback is all English; keep it
 * in English and add the learner's own language. The English stays exactly
 * as stored (AIE-04, RP-11); each comment opens in the learner's helper
 * language through Show Meaning (CTRL-01..03), drafted when the evaluation
 * is saved so a tap never calls the AI (client decision 2026-09-26).
 */
class RoleplayFeedbackMeaningTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    private Lesson $lesson;

    private Block $block;

    private AiScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ai.provider', 'fake');
        // Off in the base TestCase; this feature is the automatic draft.
        config()->set('guesvia.meaning.auto_translate', true);
        $this->setUpLearnerFixtures();
        $this->lesson = $this->publishedLesson([BlockType::AiRoleplay, BlockType::Complete]);
        $this->block = $this->lesson->visibleBlocks()->firstOrFail();
        $this->scenario = AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'status' => ContentStatus::Published,
            'input_mode' => 'text',
        ]);
        $this->block->scenarios()->attach($this->scenario->id, ['position' => 1]);
    }

    private function conversation(User $learner, array $attributes = []): RoleplayAttempt
    {
        return RoleplayAttempt::factory()->create([
            'user_id' => $learner->id,
            'ai_scenario_id' => $this->scenario->id,
            'lesson_id' => $this->lesson->id,
            'block_id' => $this->block->id,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [
                ['role' => 'guest', 'text' => 'Hello!', 'at' => now()->toIso8601String()],
                ['role' => 'employee', 'text' => 'Good afternoon!', 'at' => now()->toIso8601String()],
            ],
            ...$attributes,
        ]);
    }

    public function test_the_feedback_is_drafted_in_the_learners_helper_language_and_the_english_is_kept()
    {
        $learner = $this->learner(['meaning_locale' => 'fr']);
        $attempt = $this->conversation($learner);

        $this->actingAs($learner)
            ->post(route('learn.roleplay.end', ['attempt' => $attempt]))
            ->assertRedirect(route('learn.roleplay.feedback', ['attempt' => $attempt]));

        $attempt->refresh();
        $this->assertSame(RoleplayStatus::Completed, $attempt->status);
        // The English feedback is stored exactly as the AI wrote it.
        $this->assertSame('You greeted the guest politely.', $attempt->feedback['did_well'][0] ?? null);
        $this->assertSame('Your room is on the fifth floor.', $attempt->feedback['better_expression']['better'] ?? null);

        $texts = RoleplayFeedbackMeaning::texts($attempt->feedback);
        $this->assertContains('You greeted the guest politely.', $texts);
        $this->assertContains('The sentence was not clear. Try to use a more natural expression.', $texts);
        // The learner's own words are theirs: not translated.
        $this->assertNotContains('You are in room fifth floor.', $texts);

        foreach ($texts as $text) {
            $translation = TextTranslation::query()
                ->where('hash', TextTranslation::hashOf($text))
                ->where('locale', 'fr')
                ->first();
            $this->assertNotNull($translation, $text);
            $this->assertSame(GenerationStatus::Done, $translation->status);
            $this->assertSame(MeaningTranslations::SOURCE_AI, $translation->source);
        }

        // Only the learner's language is drafted.
        $this->assertSame(0, TextTranslation::query()
            ->where('locale', 'ar')
            ->whereIn('hash', array_map(TextTranslation::hashOf(...), $texts))
            ->count());

        // A Show Meaning tap reads the stored draft (no AI call on a tap).
        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => 'You greeted the guest politely.'])
            ->assertOk()
            ->assertJson(['status' => 'done', 'locale' => 'fr', 'text' => '(French draft) You greeted the guest politely.']);

        // The meaning never ships in the feedback page itself (CTRL-02).
        $this->actingAs($learner)
            ->get(route('learn.roleplay.feedback', ['attempt' => $attempt]))
            ->assertOk()
            ->assertDontSee('(French draft)', false);
    }

    public function test_nothing_is_drafted_when_automatic_drafts_are_off()
    {
        config()->set('guesvia.meaning.auto_translate', false);
        Queue::fake([TranslateText::class]);
        $learner = $this->learner();
        $attempt = $this->conversation($learner, [
            'status' => RoleplayStatus::Completed,
            'feedback' => ['did_well' => ['You smiled at the guest.']],
        ]);

        $this->assertSame(0, app(RoleplayFeedbackMeaning::class)->queue($attempt));
        Queue::assertNotPushed(TranslateText::class);
    }

    public function test_an_admin_preview_is_not_translated()
    {
        Queue::fake([TranslateText::class]);
        $learner = $this->learner();
        $attempt = $this->conversation($learner, ['is_preview' => true]);

        EvaluateRoleplayAttempt::dispatchSync($attempt->id);

        $this->assertSame(RoleplayStatus::Completed, $attempt->fresh()?->status);
        Queue::assertNotPushed(TranslateText::class);
    }

    public function test_opening_older_feedback_drafts_the_missing_translations_once()
    {
        Queue::fake([TranslateText::class]);
        $learner = $this->learner();
        $attempt = $this->conversation($learner, [
            'status' => RoleplayStatus::Completed,
            'overall_score' => 70,
            'feedback' => [
                'summary_label' => 'Good try!',
                'summary_text' => 'Keep practising.',
                'did_well' => ['You smiled at the guest.'],
                'improve' => [['title' => 'Directions', 'text' => 'Give the floor number.']],
                'better_expression' => ['yours' => 'Room fifth floor.', 'better' => 'Your room is on the fifth floor.'],
                'key_phrase' => '',
                'footnote' => '',
            ],
        ]);

        $this->actingAs($learner)
            ->get(route('learn.roleplay.feedback', ['attempt' => $attempt]))
            ->assertOk();

        Queue::assertPushed(TranslateText::class, 6);
        $this->assertSame(6, TextTranslation::query()->where('locale', 'ar')->where('status', GenerationStatus::Pending)->count());

        // A second visit pays for nothing new.
        $this->actingAs($learner)
            ->get(route('learn.roleplay.feedback', ['attempt' => $attempt]))
            ->assertOk();

        Queue::assertPushed(TranslateText::class, 6);
    }

    public function test_a_hand_written_translation_is_never_replaced()
    {
        Queue::fake([TranslateText::class]);
        $learner = $this->learner();
        $admin = User::factory()->superAdmin()->create();
        $row = MeaningTranslations::find('You smiled at the guest.', 'ar');
        $row->save();
        app(MeaningTranslations::class)->write($row, 'ابتسمت للضيف.', $admin);

        $attempt = $this->conversation($learner, [
            'status' => RoleplayStatus::Completed,
            'feedback' => ['did_well' => ['You smiled at the guest.']],
        ]);

        $this->assertSame(0, app(RoleplayFeedbackMeaning::class)->queue($attempt));
        Queue::assertNotPushed(TranslateText::class);
        $this->assertSame('ابتسمت للضيف.', $row->fresh()?->translation);
    }
}
