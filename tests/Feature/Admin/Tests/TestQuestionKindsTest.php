<?php

namespace Tests\Feature\Admin\Tests;

use App\Models\Department;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Add Question" in the Pre/Post-test builder keeps the kind chosen in the
 * tiles (TEST-05): a True / False question comes back as True / False with
 * its two answers, and a rejected write names the field in plain words.
 */
class TestQuestionKindsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $this->test = Test::factory()->draft()->create(['department_id' => Department::factory()->create()->id]);
    }

    public function test_a_true_false_question_is_kept_as_true_false()
    {
        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), [
                'kind' => 'true_false',
                'text' => 'You greet every guest with a smile.',
                'options' => [
                    ['id' => 'A', 'text' => 'True', 'correct' => true],
                    ['id' => 'B', 'text' => 'False', 'correct' => false],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('tests', ['test' => $this->test]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.questions.0.kind', 'true_false')
                ->where('editor.questions.0.text', 'You greet every guest with a smile.')
                ->where('editor.questions.0.options.0.text', 'True')
                ->where('editor.questions.0.options.1.text', 'False')
            );
    }

    public function test_changing_a_question_to_true_false_sticks()
    {
        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), [
                'kind' => 'multiple_choice',
                'text' => 'Write the question prompt here.',
                'options' => [
                    ['id' => 'A', 'text' => 'Option A', 'correct' => true],
                    ['id' => 'B', 'text' => 'Option B', 'correct' => false],
                ],
            ])
            ->assertSessionHasNoErrors();

        $placement = $this->test->questions()->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('tests.questions.update', [$this->test, $placement]), [
                'kind' => 'true_false',
                'text' => 'Breakfast starts at seven.',
                'options' => [
                    ['id' => 'A', 'text' => 'True', 'correct' => false],
                    ['id' => 'B', 'text' => 'False', 'correct' => true],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('tests', ['test' => $this->test]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.questions.0.kind', 'true_false')
                ->where('editor.questions.0.text', 'Breakfast starts at seven.')
            );
    }

    public function test_an_empty_answer_is_reported_in_plain_words()
    {
        $this->actingAs($this->owner)
            ->from(route('tests', ['test' => $this->test]))
            ->post(route('tests.questions.store', $this->test), [
                'kind' => 'multiple_choice',
                'text' => 'Pick one.',
                'options' => [
                    ['id' => 'A', 'text' => 'Yes', 'correct' => true],
                    ['id' => 'B', 'text' => '', 'correct' => false],
                ],
            ])
            ->assertSessionHasErrors(['options.1.text' => 'The answer text field is required when options is present.']);
    }
}
