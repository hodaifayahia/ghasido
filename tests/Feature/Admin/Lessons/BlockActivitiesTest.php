<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The activities of a Practice block and the questions of a Quiz block
 * (PRAC-01..07, BLD-03, DATA-10, DATA-11; client report 2026-09-29): add,
 * edit, reorder and delete, through the one `activities` table that also
 * serves the tests (PRAC-05).
 */
class BlockActivitiesTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    private function practiceBlock(): Block
    {
        return $this->lesson->blocks()->where('type', BlockType::Practice->value)->firstOrFail();
    }

    private function quizBlock(): Block
    {
        $this->actingAs($this->owner)
            ->post(route('blocks.store', $this->lesson), ['type' => 'quiz'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return $this->lesson->blocks()->where('type', BlockType::Quiz->value)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function multipleChoice(Block $block, string $question = 'A guest asks for a towel. What do you say?', string $correct = 'A'): array
    {
        return [
            'type' => 'multiple_choice',
            'title' => 'Question 1',
            'prompt' => 'Choose the best answer.',
            'attempts_allowed' => 1,
            'block_id' => $block->id,
            'payload' => ['items' => [[
                'id' => 'i1',
                'question' => $question,
                'image' => null,
                'layout' => 'side',
                'options' => [
                    ['id' => 'A', 'text' => 'Of course, I will bring one right away.'],
                    ['id' => 'B', 'text' => 'Towels are not my job.'],
                    ['id' => 'C', 'text' => 'Please wait outside.'],
                ],
                'correct' => $correct,
            ]]],
        ];
    }

    public function test_the_quiz_tile_adds_a_quiz_block_with_its_own_editor_type()
    {
        $quiz = $this->quizBlock();

        $this->assertSame('Quiz', $quiz->heading());
        $this->assertTrue(BlockType::Quiz->holdsActivities());

        $this->actingAs($this->owner)
            ->get(route('lessons.edit', $this->lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lessonBlocks', fn ($blocks): bool => collect($blocks)->contains(fn (array $block): bool => $block['type'] === 'quiz'))
                ->where('blocks', fn ($tiles): bool => collect($tiles)->firstWhere('id', 'quiz')['type'] === 'quiz')
            );
    }

    public function test_a_multiple_choice_question_is_added_to_a_practice_block_with_its_correct_answer()
    {
        $practice = $this->practiceBlock();
        $before = $practice->placements()->count();

        $this->actingAs($this->owner)
            ->post(route('activities.store'), $this->multipleChoice($practice))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $activity = Activity::query()->where('title', 'Question 1')->firstOrFail();

        $this->assertSame(ActivityType::MultipleChoice, $activity->type);
        $this->assertSame('A', $activity->items()[0]['correct']);
        $this->assertSame('Of course, I will bring one right away.', $activity->items()[0]['options'][0]['text']);
        $this->assertSame(1, $activity->current_version);
        $this->assertSame($this->department->id, $activity->department_id);
        $this->assertSame($before + 1, $practice->placements()->count());
        $this->assertSame($activity->id, $practice->placements()->get()->last()?->activity_id);
    }

    public function test_a_question_is_added_to_a_quiz_block_and_edited_as_a_new_version()
    {
        $quiz = $this->quizBlock();

        $this->actingAs($this->owner)
            ->post(route('activities.store'), $this->multipleChoice($quiz))
            ->assertSessionHasNoErrors();

        $activity = $quiz->placements()->firstOrFail()->activity()->firstOrFail();
        $payload = $activity->payload;
        $payload['items'][0]['correct'] = 'B';
        $payload['items'][0]['options'][] = ['id' => 'D', 'text' => 'One moment, please.'];

        $this->actingAs($this->owner)
            ->patch(route('activities.update', $activity), ['payload' => $payload, 'prompt' => 'Pick one.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $activity->refresh();

        $this->assertSame(2, $activity->current_version);
        $this->assertSame('B', $activity->items()[0]['correct']);
        $this->assertCount(4, $activity->items()[0]['options']);
        $this->assertSame('A', $activity->versions()->where('version', 1)->firstOrFail()->items()[0]['correct']);

        $this->actingAs($this->owner)
            ->get(route('lessons.edit', $this->lesson))
            ->assertInertia(fn (Assert $page) => $page->where('lessonBlocks', function ($blocks) use ($activity): bool {
                $row = collect($blocks)->firstWhere('type', 'quiz');

                return $row['activities'][0]['id'] === $activity->id
                    && $row['activities'][0]['payload']['items'][0]['correct'] === 'B';
            }));
    }

    public function test_a_question_without_a_valid_correct_answer_is_refused()
    {
        $quiz = $this->quizBlock();

        $this->actingAs($this->owner)
            ->from(route('lessons.edit', $this->lesson))
            ->post(route('activities.store'), $this->multipleChoice($quiz, correct: 'Z'))
            ->assertSessionHasErrors('payload.items.0');

        $data = $this->multipleChoice($quiz);
        $data['payload']['items'][0]['options'] = [['id' => 'A', 'text' => 'Only one']];

        $this->actingAs($this->owner)
            ->from(route('lessons.edit', $this->lesson))
            ->post(route('activities.store'), $data)
            ->assertSessionHasErrors('payload.items.0');

        $this->assertSame(0, $quiz->placements()->count());

        $matching = [
            'type' => 'listen_match',
            'prompt' => 'Match.',
            'block_id' => $quiz->id,
            'payload' => ['items' => [[
                'id' => 'i1',
                'prompts' => [['id' => '1', 'audio_text' => 'Towel']],
                'targets' => [['id' => 'a', 'label' => 'Towel'], ['id' => 'b', 'label' => 'Pillow']],
                'pairs' => ['1' => 'c'],
            ]]],
        ];

        $this->actingAs($this->owner)
            ->from(route('lessons.edit', $this->lesson))
            ->post(route('activities.store'), $matching)
            ->assertSessionHasErrors('payload.items.0');

        $matching['payload']['items'][0]['pairs'] = ['1' => 'a'];

        $this->actingAs($this->owner)
            ->post(route('activities.store'), $matching)
            ->assertSessionHasNoErrors();
    }

    public function test_activities_of_a_block_are_reordered_and_a_foreign_placement_is_refused()
    {
        $quiz = $this->quizBlock();

        foreach (['First?', 'Second?', 'Third?'] as $question) {
            $this->actingAs($this->owner)->post(route('activities.store'), $this->multipleChoice($quiz, $question));
        }

        $ids = $quiz->placements()->pluck('id')->all();
        $order = [$ids[2], $ids[0], $ids[1]];

        $this->actingAs($this->owner)
            ->put(route('blocks.activities.reorder', $quiz), ['order' => $order])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($order, $quiz->placements()->pluck('id')->all());
        $this->assertSame([1, 2, 3], $quiz->placements()->pluck('position')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'block.activities.reordered')->count());

        $foreign = $this->practiceBlock()->placements()->first()
            ?? ActivityPlacement::factory()->in($this->practiceBlock())->create();

        $this->actingAs($this->owner)
            ->from(route('lessons.edit', $this->lesson))
            ->put(route('blocks.activities.reorder', $quiz), ['order' => [$ids[0], $ids[1], $foreign->id]])
            ->assertSessionHasErrors('order');

        $this->assertSame($order, $quiz->placements()->pluck('id')->all());
    }

    public function test_deleting_an_unanswered_question_removes_it_and_its_activity()
    {
        $quiz = $this->quizBlock();

        foreach (['First?', 'Second?'] as $question) {
            $this->actingAs($this->owner)->post(route('activities.store'), $this->multipleChoice($quiz, $question));
        }

        [$first, $second] = $quiz->placements()->get()->all();

        $this->actingAs($this->owner)
            ->delete(route('blocks.activities.destroy', ['block' => $quiz, 'placement' => $first]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(ActivityPlacement::query()->find($first->id));
        $this->assertNull(Activity::query()->find($first->activity_id));
        $this->assertSame([$second->id], $quiz->placements()->pluck('id')->all());
        $this->assertSame(1, $quiz->placements()->firstOrFail()->position);
        $this->assertSame(1, AuditLog::query()->where('action', 'block.activity.removed')->count());
    }

    public function test_deleting_an_answered_activity_keeps_the_activity_and_every_answer()
    {
        $practice = $this->practiceBlock();

        $this->actingAs($this->owner)->post(route('activities.store'), $this->multipleChoice($practice));

        $placement = $practice->placements()->get()->last();
        $this->assertNotNull($placement);
        $activity = $placement->activity()->firstOrFail();
        $attempt = Attempt::factory()->create([
            'activity_id' => $activity->id,
            'placement_id' => $placement->id,
            'block_id' => $practice->id,
            'lesson_id' => $this->lesson->id,
            'raw_answer' => ['i1' => 'A'],
        ]);

        $this->actingAs($this->owner)
            ->delete(route('blocks.activities.destroy', ['block' => $practice, 'placement' => $placement]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(ActivityPlacement::query()->find($placement->id));
        $this->assertNotNull(Activity::query()->find($activity->id));
        $attempt->refresh();
        $this->assertSame(['i1' => 'A'], $attempt->raw_answer);
        $this->assertSame($practice->id, $attempt->block_id);
        $this->assertNull($attempt->placement_id);
    }

    public function test_a_placement_of_another_block_cannot_be_deleted_through_this_block()
    {
        $quiz = $this->quizBlock();
        $practice = $this->practiceBlock();
        $placement = ActivityPlacement::factory()->in($practice)->create();

        $this->actingAs($this->owner)
            ->delete(route('blocks.activities.destroy', ['block' => $quiz, 'placement' => $placement]))
            ->assertNotFound();

        $this->assertNotNull(ActivityPlacement::query()->find($placement->id));
    }

    public function test_a_manager_cannot_manage_the_activities_of_a_block()
    {
        $quiz = $this->quizBlock();
        $this->actingAs($this->owner)->post(route('activities.store'), $this->multipleChoice($quiz));
        $placement = $quiz->placements()->firstOrFail();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('activities.store'), $this->multipleChoice($quiz))
            ->assertForbidden();
        $this->actingAs($manager)
            ->put(route('blocks.activities.reorder', $quiz), ['order' => [$placement->id]])
            ->assertForbidden();
        $this->actingAs($manager)
            ->delete(route('blocks.activities.destroy', ['block' => $quiz, 'placement' => $placement]))
            ->assertForbidden();

        $this->assertNotNull($placement->fresh());
    }

    public function test_an_activity_added_to_a_hotel_lesson_belongs_to_that_hotel()
    {
        $hotel = Hotel::factory()->create();
        $course = Course::factory()->forDepartment($this->department->id)->forHotel($hotel->id)->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->withBlocks()->create(['unit_id' => $unit->id]);
        $practice = $lesson->blocks()->where('type', BlockType::Practice->value)->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('activities.store'), $this->multipleChoice($practice, 'Hotel only?'))
            ->assertSessionHasNoErrors();

        $activity = $practice->placements()->get()->last()?->activity()->firstOrFail();

        $this->assertSame($hotel->id, $activity?->hotel_id);
    }
}
