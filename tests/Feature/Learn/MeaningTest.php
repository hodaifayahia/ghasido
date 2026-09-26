<?php

namespace Tests\Feature\Learn;

use App\Enums\GenerationStatus;
use App\Enums\TestAttemptStatus;
use App\Jobs\TranslateText;
use App\Models\AiUsage;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Show Meaning on any English text (CTRL-01..03; client decision
 * 2026-09-26): the Arabic leaves the server only on request, a learner's
 * tap only reads what the content's author stored (never the AI; user
 * request 2026-09-26), and a test whose admin switched Show Meaning off
 * refuses it server-side (CTRL-04).
 */
class MeaningTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_a_guest_cannot_ask_for_a_meaning()
    {
        $this->post(route('meaning'), ['text' => 'Welcome to the hotel'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('text_translations', 0);
    }

    public function test_a_learner_reads_a_stored_meaning_whatever_the_spacing_or_case()
    {
        $learner = $this->learner();
        $this->translated('Check-in basics', 'أساسيات تسجيل الوصول');

        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => '  check-in   BASICS '])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('arabic', 'أساسيات تسجيل الوصول');

        $this->assertDatabaseCount('text_translations', 1);
    }

    public function test_a_tap_never_calls_the_ai_and_notes_the_text_for_the_admin()
    {
        Queue::fake();
        $learner = $this->learner();

        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => 'Room service'])
            ->assertOk()
            ->assertJsonPath('status', 'missing')
            ->assertJsonPath('arabic', null);

        // Tapping again, or by another learner, still calls nothing.
        $this->actingAs($this->learner(['username' => 'karim']))
            ->postJson(route('meaning'), ['text' => 'Room service'])
            ->assertJsonPath('status', 'missing');

        Queue::assertNotPushed(TranslateText::class);
        $this->assertSame(0, AiUsage::query()->count());
        $translation = TextTranslation::query()->sole();
        $this->assertSame(MeaningTranslations::SOURCE_REQUESTED, $translation->source);
        $this->assertSame($learner->id, $translation->requested_by);
    }

    public function test_a_learner_waits_for_a_draft_the_content_save_already_queued()
    {
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf('Room service'),
            'source_text' => 'Room service',
            'status' => GenerationStatus::Pending,
            'source' => MeaningTranslations::SOURCE_AI,
        ]);

        $this->actingAs($this->learner())
            ->postJson(route('meaning'), ['text' => 'Room service'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');
    }

    public function test_text_without_letters_or_too_long_is_refused()
    {
        $learner = $this->learner();

        $this->actingAs($learner)->postJson(route('meaning'), ['text' => '12 / 30'])->assertStatus(422);
        $this->actingAs($learner)->postJson(route('meaning'), ['text' => str_repeat('a', TextTranslation::MAX_LENGTH + 1)])->assertStatus(422);

        $this->assertDatabaseCount('text_translations', 0);
    }

    public function test_show_meaning_is_allowed_during_a_test_by_default()
    {
        $learner = $this->learner();
        $this->openSitting($learner, []);

        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => 'Where is the lift?'])
            ->assertOk();
    }

    public function test_a_test_with_show_meaning_switched_off_refuses_it_server_side()
    {
        $learner = $this->learner();
        $this->openSitting($learner, ['show_meaning' => false]);

        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => 'Where is the lift?'])
            ->assertForbidden();

        $this->assertDatabaseCount('text_translations', 0);
    }

    public function test_the_test_page_tells_the_runner_whether_show_meaning_is_on()
    {
        $this->assertTrue((new Test(['settings' => []]))->showsMeaning());
        $this->assertFalse((new Test(['settings' => ['show_meaning' => false]]))->showsMeaning());
    }

    private function translated(string $text, string $arabic): TextTranslation
    {
        return TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf($text),
            'source_text' => $text,
            'arabic' => $arabic,
            'status' => GenerationStatus::Done,
            'source' => MeaningTranslations::SOURCE_AI,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function openSitting(User $learner, array $settings): TestAttempt
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => $settings + ['time_limit_seconds' => 1200],
        ]);

        return TestAttempt::factory()->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
            'status' => TestAttemptStatus::InProgress,
            'deadline_at' => now()->addMinutes(20),
        ]);
    }
}
