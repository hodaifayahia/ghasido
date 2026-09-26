<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Test as Assessment;
use App\Models\User;
use App\Services\Tests\QuestionImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Import Pre/Post-test questions from CSV or pasted rows (TEST-05, TSTM-01;
 * client request 2026-09-26): all rows or none, every problem reported.
 */
class TestQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Assessment $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $department = Department::factory()->create(['name' => 'Reception']);
        $this->test = Assessment::factory()->pre()->create(['department_id' => $department->id]);
    }

    public function test_the_template_imports_one_question_of_every_kind()
    {
        $file = UploadedFile::fake()->createWithContent('questions.csv', QuestionImport::template());

        $this->actingAs($this->owner)
            ->post(route('tests.questions.import', $this->test), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $placements = $this->test->questions()->orderBy('position')->with('activity')->get();
        $this->assertCount(6, $placements);
        $this->assertSame([1, 2, 3, 4, 5, 6], $placements->pluck('position')->all());

        /** @var Activity $first */
        $first = $placements[0]->activity;
        $this->assertSame(ActivityType::MultipleChoice, $first->type);
        $this->assertSame('A', $first->items()[0]['correct']);
        $this->assertCount(3, $first->items()[0]['options']);

        $this->assertSame(ActivityType::Writing, $placements[3]->activity?->type);
        $this->assertSame(ActivityType::Speaking, $placements[4]->activity?->type);
        $this->assertSame(ActivityType::DialogueOrder, $placements[5]->activity?->type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.questions.imported']);
    }

    public function test_pasted_semicolon_rows_without_a_header_are_accepted()
    {
        $rows = "multiple_choice;Where is the lift?;On the left.;I am hungry.;;;;;a\ntrue_false;The pool opens at 8.;;;;;;;false";

        $this->actingAs($this->owner)
            ->post(route('tests.questions.import', $this->test), ['rows' => $rows])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->test->questions()->count());
        $second = $this->test->questions()->get()->last()?->activity;
        $this->assertSame('B', $second?->items()[0]['correct']);
    }

    public function test_one_bad_row_imports_nothing_and_names_every_problem()
    {
        $rows = "type,question,option_a,option_b,option_c,option_d,option_e,option_f,correct\n"
            ."multiple_choice,Good question,Yes,No,,,,,A\n"
            ."multiple_choice,,Yes,No,,,,,A\n"
            ."multiple_choice,No right answer,Yes,No,,,,,D\n"
            .'drawing,Draw the lobby,,,,,,,';

        $this->actingAs($this->owner)
            ->from(route('tests'))
            ->post(route('tests.questions.import', $this->test), ['rows' => $rows])
            ->assertSessionHasErrors(['import.0', 'import.1', 'import.2']);

        $this->assertSame(0, $this->test->questions()->count());
        $errors = session('errors')->all();
        $this->assertStringContainsString('Line 3', $errors[0]);
        $this->assertStringContainsString('Line 4', $errors[1]);
        $this->assertStringContainsString('Line 5', $errors[2]);
    }

    public function test_a_file_that_is_not_csv_is_refused()
    {
        $file = UploadedFile::fake()->create('questions.pdf', 10, 'application/pdf');

        $this->actingAs($this->owner)
            ->post(route('tests.questions.import', $this->test), ['file' => $file])
            ->assertSessionHasErrors(['file']);

        $this->assertSame(0, $this->test->questions()->count());
    }

    public function test_only_test_managers_can_import_or_download_the_template()
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->post(route('tests.questions.import', $this->test), ['rows' => 'multiple_choice,Q,Yes,No,,,,,A'])
            ->assertForbidden();

        $this->actingAs($employee)->get(route('tests.questions.import-template'))->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('tests.questions.import-template'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertSame(0, $this->test->questions()->count());
    }
}
