<?php

namespace Tests\Feature\Learn;

use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Enums\TestAttemptStatus;
use App\Jobs\TranslateText;
use App\Models\AiUsage;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TextTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Show Meaning on any English text (CTRL-01..03; client decision
 * 2026-09-26): the Arabic leaves the server only on request, each distinct
 * text is translated once, and a test whose admin switched Show Meaning off
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

    public function test_a_learner_gets_the_arabic_of_any_text_and_it_is_cached()
    {
        $learner = $this->learner();

        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => 'Check-in basics'])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('arabic', '(ترجمة تجريبية) Check-in basics');

        // Same text, different spacing and case: the same row, no new call.
        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => '  check-in   BASICS '])
            ->assertOk()
            ->assertJsonPath('arabic', '(ترجمة تجريبية) Check-in basics');

        $this->assertDatabaseCount('text_translations', 1);
        $this->assertSame(1, AiUsage::query()->where('feature', AiFeature::Translation->value)->count());
        $this->assertSame(0, (int) AiUsage::query()->sum('points_charged'));
    }

    public function test_the_first_request_queues_the_translation_and_reports_pending()
    {
        Queue::fake();
        $learner = $this->learner();

        $this->actingAs($learner)
            ->postJson(route('meaning'), ['text' => 'Room service'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('arabic', null);

        Queue::assertPushed(TranslateText::class, 1);
        $this->assertSame(GenerationStatus::Pending, TextTranslation::query()->sole()->status);
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
