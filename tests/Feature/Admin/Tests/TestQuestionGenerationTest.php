<?php

namespace Tests\Feature\Admin\Tests;

use App\Enums\ActivityType;
use App\Enums\AiFeature;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\TestQuestionSkill;
use App\Models\Activity;
use App\Models\ActivityVersion;
use App\Models\AudioClip;
use App\Models\Department;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * AI-drafted Pre/Post-test questions (GEN-01, GEN-03, GEN-04, TEST-02,
 * TSTM-03, DATA-11, TTS-01; spec 0004). Fake AI + TTS, sync queue.
 */
class TestQuestionGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'services.ai.provider' => 'fake',
            'services.tts.provider' => 'fake',
            'services.stt.provider' => 'fake',
        ]);
        Storage::fake(MediaAsset::DISK_PUBLIC);

        $this->admin = User::factory()->superAdmin()->create();
        $this->department = Department::factory()->create(['name' => 'Reception']);
    }

    private function generate(Test $test, array $payload): void
    {
        $this->actingAs($this->admin)
            ->post(route('tests.ai.generate', $test), $payload + ['level' => 'A2'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_generation_creates_draft_versioned_questions_hidden_from_learners()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);

        $this->generate($test, [
            'prompt' => 'Check-in and room problems',
            'count' => 5,
            'skills' => ['multiple_choice', 'listening', 'speaking', 'writing', 'ordering'],
        ]);

        $test->refresh();
        $this->assertSame(GenerationStatus::Done, $test->ai_status);
        $placements = $test->questions()->with('activity')->get();
        $this->assertCount(5, $placements);

        $types = $placements->map(fn ($placement) => $placement->activity->type)->all();
        $this->assertSame([
            ActivityType::MultipleChoice,
            ActivityType::BestResponse,
            ActivityType::Speaking,
            ActivityType::Writing,
            ActivityType::DialogueOrder,
        ], $types);

        foreach ($placements as $placement) {
            $this->assertTrue(Test::isAiDraft($placement));
            $this->assertSame(ContentStatus::Draft, $placement->activity->status);
            $this->assertFalse($placement->activity->show_meaning_enabled);
            // Versioned from the first save (DATA-11).
            $this->assertSame(1, ActivityVersion::query()->where('activity_id', $placement->activity_id)->count());
        }

        // Drafts never reach a sitting until approved (GEN-03).
        $this->assertCount(0, $test->learnerQuestions());
        $this->assertSame($placements->pluck('id')->all(), $test->ai_request['placement_ids']);
        $this->assertDatabaseHas('ai_usages', ['feature' => AiFeature::TestQuestionsGenerate->value, 'user_id' => $this->admin->id]);

        // The listening script has stored audio queued at once (CTRL-05).
        $this->assertSame(2, AudioClip::query()->count());

        // Publishing the test releases every draft.
        $this->actingAs($this->admin)->post(route('tests.publish', $test))->assertRedirect();
        $this->assertCount(5, $test->refresh()->learnerQuestions());
        $this->assertSame(0, Activity::query()->where('status', ContentStatus::Draft->value)->count());
    }

    public function test_one_draft_can_be_approved_on_its_own()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);
        $this->generate($test, ['count' => 2, 'skills' => ['multiple_choice']]);

        $first = $test->questions()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('tests.questions.release', ['test' => $test, 'placement' => $first]))
            ->assertRedirect();

        $this->assertCount(1, $test->refresh()->learnerQuestions());
    }

    public function test_regenerate_replaces_the_last_batch()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);
        $this->generate($test, ['count' => 3, 'skills' => ['multiple_choice', 'speaking']]);
        $before = $test->refresh()->questions()->pluck('id')->all();

        $this->actingAs($this->admin)
            ->post(route('tests.ai.regenerate', $test))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $after = $test->refresh()->questions()->pluck('id')->all();
        $this->assertCount(3, $after);
        $this->assertSame([], array_intersect($before, $after));
        $this->assertSame(GenerationStatus::Done, $test->ai_status);
        $this->assertSame([1, 2, 3], $test->questions()->pluck('position')->all());
    }

    public function test_a_post_test_gets_paired_questions_from_its_pre_test()
    {
        $pre = Test::factory()->pre()->create(['department_id' => $this->department->id]);
        $this->generate($pre, ['count' => 4, 'skills' => ['listening', 'speaking', 'writing', 'multiple_choice']]);
        $post = Test::factory()->post()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->admin)
            ->get(route('tests', ['test' => $post->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.ai.pairedSource.id', $pre->id)
                ->where('editor.ai.pairedSource.questionCount', 4)
            );

        $this->generate($post, ['paired' => true]);

        $post->refresh();
        $this->assertSame($pre->id, $post->paired_test_id);
        $this->assertSame(GenerationStatus::Done, $post->ai_status);

        $skillsOf = fn (Test $test): array => $test->questions()->with('activity')->get()
            ->map(fn ($placement) => TestQuestionSkill::fromActivityType($placement->activity->type)->value)
            ->all();
        $questionsOf = fn (Test $test): array => $test->questions()->with('activity')->get()
            ->map(fn ($placement) => $placement->activity->items()[0]['question'] ?? '')
            ->all();

        // Same skills in the same order, different items (TEST-02, TSTM-03).
        $this->assertSame($skillsOf($pre), $skillsOf($post));
        $this->assertSame([], array_intersect($questionsOf($pre), $questionsOf($post)));
    }

    public function test_paired_generation_needs_a_pre_test_with_questions()
    {
        $post = Test::factory()->post()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->admin)
            ->post(route('tests.ai.generate', $post), ['paired' => true, 'level' => 'A2'])
            ->assertSessionHasErrors('paired');

        $this->assertNull($post->refresh()->ai_status);
    }

    public function test_generation_input_is_validated()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);

        $this->actingAs($this->admin)
            ->post(route('tests.ai.generate', $test), ['count' => 50, 'skills' => ['poetry'], 'level' => 'C9'])
            ->assertSessionHasErrors(['count', 'skills.0', 'level']);
    }

    public function test_generate_audio_queues_both_speeds_and_reports_status()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);
        $activity = Activity::factory()->ofType(ActivityType::BestResponse)->create();
        $placement = $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        $this->actingAs($this->admin)
            ->post(route('tests.questions.audio', ['test' => $test, 'placement' => $placement]))
            ->assertRedirect();

        $this->assertSame(2, AudioClip::query()->count());

        $this->actingAs($this->admin)
            ->get(route('tests', ['test' => $test->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.questions.0.audio.status', 'done')
                ->where('editor.questions.0.kind', 'audio_question')
            );

        $this->actingAs($this->admin)
            ->post(route('tests.audio.generate', $test))
            ->assertRedirect();

        // Generated once, never twice (TTS-02).
        $this->assertSame(2, AudioClip::query()->count());
    }

    public function test_a_learner_cannot_reach_the_ai_routes()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->department->id]);
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->post(route('tests.ai.generate', $test), ['count' => 2, 'skills' => ['multiple_choice'], 'level' => 'A2'])
            ->assertForbidden();

        $this->assertNull($test->refresh()->ai_status);
    }
}
