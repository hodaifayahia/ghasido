<?php

namespace Tests\Feature\Admin\Tests;

use App\Enums\ResultsVisibility;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The builder's Settings tab (client request 2026-09-29): every per-test
 * rule saves through tests.update and comes back on reload (TEST-04,
 * TSTM-02, JOURNEY-05, CERT-04).
 */
class TestSettingsTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Department $department, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Reception baseline',
            'type' => 'pre',
            'department_id' => $department->id,
            'description' => '',
            'time_limit_minutes' => 20,
            'question_count' => 0,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'single_attempt' => false,
            'results_visibility' => 'hidden',
            'show_answers' => false,
            'motivational_message' => false,
            'show_meaning' => true,
            'pass_mark' => null,
        ], $overrides);
    }

    public function test_every_setting_saves_and_reloads(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('tests.update', $test), $this->payload($department, [
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'single_attempt' => true,
                'results_visibility' => 'score_breakdown',
                'show_answers' => true,
                'motivational_message' => true,
                'show_meaning' => false,
                'pass_mark' => 70,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $test->refresh();
        $this->assertSame(ResultsVisibility::ScoreBreakdown, $test->resultsVisibility());
        $this->assertFalse($test->showsMeaning());
        $this->assertTrue($test->shufflesQuestions());
        $this->assertSame(70.0, $test->passScore());

        $this->actingAs($admin)
            ->get(route('tests', ['test' => $test->id, 'editorTab' => 'settings']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.settings.shuffle_questions', true)
                ->where('editor.settings.shuffle_options', true)
                ->where('editor.settings.single_attempt', true)
                ->where('editor.settings.results_visibility', 'score_breakdown')
                ->where('editor.settings.show_answers', true)
                ->where('editor.settings.motivational_message', true)
                ->where('editor.settings.show_meaning', false)
                ->where('editor.settings.passMark', '70')
            );

        // Switching them all back off round-trips too.
        $this->actingAs($admin)
            ->patch(route('tests.update', $test), $this->payload($department))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('tests', ['test' => $test->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.settings.shuffle_questions', false)
                ->where('editor.settings.shuffle_options', false)
                ->where('editor.settings.single_attempt', false)
                ->where('editor.settings.results_visibility', 'hidden')
                ->where('editor.settings.show_answers', false)
                ->where('editor.settings.motivational_message', false)
                ->where('editor.settings.show_meaning', true)
                ->where('editor.settings.passMark', '')
            );
    }

    public function test_settings_are_validated(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('tests.update', $test), $this->payload($department, [
                'results_visibility' => 'always',
                'pass_mark' => 150,
                'shuffle_questions' => 'maybe',
                'show_meaning' => 'sometimes',
            ]))
            ->assertSessionHasErrors(['results_visibility', 'pass_mark', 'shuffle_questions', 'show_meaning']);

        $this->actingAs($admin)
            ->patch(route('tests.update', $test), $this->payload($department, ['pass_mark' => -1]))
            ->assertSessionHasErrors(['pass_mark']);
    }

    public function test_a_save_without_a_pass_mark_and_other_stored_rules_are_kept(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->pre()->create([
            'department_id' => $department->id,
            'settings' => ['on_timeout' => 'unanswered', 'pass_score' => 50],
        ]);
        $admin = User::factory()->superAdmin()->create();
        $payload = $this->payload($department);
        unset($payload['pass_mark']);

        $this->actingAs($admin)
            ->patch(route('tests.update', $test), $payload)
            ->assertSessionHasNoErrors();

        $test->refresh();
        $this->assertSame('unanswered', $test->onTimeout());
        $this->assertNull($test->passScore());
    }

    public function test_saving_keeps_the_learner_intro_copy(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->pre()->create([
            'department_id' => $department->id,
            'intro' => ['heading' => 'Let us start', 'description' => 'Old'],
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->patch(route('tests.update', $test), $this->payload($department, ['description' => 'New']))
            ->assertSessionHasNoErrors();

        $test->refresh();
        $this->assertSame(['heading' => 'Let us start', 'description' => 'New'], $test->intro);
    }

    public function test_the_preview_tab_gets_each_question_in_test_mode_without_answers(): void
    {
        $department = Department::factory()->create();
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);
        $admin = User::factory()->superAdmin()->create();
        $activity = Activity::factory()->create(['department_id' => $department->id]);
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        $response = $this->actingAs($admin)
            ->get(route('tests', ['test' => $test->id, 'editorTab' => 'preview']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('preview.activities', 1)
                ->where('preview.activities.0.mode', 'test')
                ->where('preview.activities.0.showMeaningEnabled', false)
            );

        $items = $response->viewData('page')['props']['preview']['activities'][0]['items'];
        $this->assertStringNotContainsString('"correct"', (string) json_encode($items));
    }
}
