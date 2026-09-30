<?php

namespace Tests\Feature\Activities;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Department;
use App\Models\MediaAsset;
use App\Models\Test as Assessment;
use App\Models\User;
use Database\Factories\ActivityFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The client's ten question types are made in one shared editor, for a
 * Pre/Post-test and for a lesson block alike, and every type is checked
 * on the server for what it needs (client report 2026-09-29; TEST-05,
 * PRAC-01..03, PRAC-05, DATA-11).
 */
class QuestionTypesBuilderTest extends TestCase
{
    use BuildsLearnerFixtures, QuestionPayloads, RefreshDatabase;

    private User $owner;

    private Assessment $test;

    /** @var array{image: int, audio: int, video: int} */
    private array $media;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->setUpLearnerFixtures();
        $this->owner = User::factory()->superAdmin()->create();
        $department = Department::factory()->create(['name' => 'Commercial']);
        $this->test = Assessment::factory()->pre()->create(['department_id' => $department->id]);
        $this->media = [
            'image' => MediaAsset::factory()->create()->id,
            'audio' => MediaAsset::factory()->audio()->create()->id,
            'video' => MediaAsset::factory()->video()->create()->id,
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function builderTypes(): array
    {
        $types = [];

        foreach (ActivityType::builderTypes() as $type) {
            $types[$type->value] = [$type->value];
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function question(string $type, array $item): array
    {
        return [
            'type' => $type,
            'title' => null,
            'prompt' => 'Answer the question.',
            'payload' => ['items' => [$item]],
        ];
    }

    #[DataProvider('builderTypes')]
    public function test_every_type_is_added_to_a_test_with_its_item_verbatim(string $type)
    {
        $item = self::itemFor($type, $this->media);

        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), $this->question($type, $item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $activity = $this->test->questions()->with('activity')->firstOrFail()->activity;
        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertSame($type, $activity->type->value);
        $this->assertSame($item, $activity->items()[0]);
        $this->assertSame(1, $activity->current_version);
        $this->assertFalse($activity->show_meaning_enabled);
    }

    public function test_the_builder_offers_exactly_the_ten_types_and_opens_each_question_in_the_editor()
    {
        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), $this->question('fill_blank', self::itemFor('fill_blank')))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('tests', ['test' => $this->test->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.kinds', array_map(
                    static fn (ActivityType $type): array => ['value' => $type->value, 'label' => $type->builderLabel()],
                    ActivityType::builderTypes(),
                ))
                ->where('editor.kinds.0.label', 'Multiple Choice')
                ->where('editor.kinds.9.label', 'Writing Activity')
                ->where('editor.questions.0.kind', 'fill_blank')
                ->where('editor.questions.0.activity.type', 'fill_blank')
                ->where('editor.questions.0.activity.payload.items.0.blanks.0.accepted', ['passport', 'ID'])
            );
    }

    public function test_editing_a_question_writes_a_new_version_and_keeps_its_type()
    {
        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), $this->question('short_answer', self::itemFor('short_answer')))
            ->assertSessionHasNoErrors();
        $placement = $this->test->questions()->firstOrFail();

        $item = [...self::itemFor('short_answer'), 'accepted' => ['key card', 'keycard', 'card']];

        $this->actingAs($this->owner)
            ->patch(route('tests.questions.update', [$this->test, $placement]), [
                'prompt' => 'Type your answer.',
                'payload' => ['items' => [$item]],
            ])
            ->assertSessionHasNoErrors();

        $activity = $placement->activity()->firstOrFail();
        $this->assertSame(ActivityType::ShortAnswer, $activity->type);
        $this->assertSame(2, $activity->current_version);
        $this->assertSame(['key card', 'keycard', 'card'], $activity->items()[0]['accepted']);
        $this->assertSame(['key card', 'room key'], $activity->versions()->where('version', 1)->firstOrFail()->items()[0]['accepted']);

        // The type never changes on an edit (DATA-11).
        $this->actingAs($this->owner)
            ->patch(route('tests.questions.update', [$this->test, $placement]), [
                'type' => 'matching',
                'prompt' => 'Type your answer.',
                'payload' => ['items' => [$item]],
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_a_test_question_holds_exactly_one_item_and_an_older_type_is_refused()
    {
        $item = self::itemFor('short_answer');

        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), [
                ...$this->question('short_answer', $item),
                'payload' => ['items' => [$item, [...$item, 'id' => 'i2']]],
            ])
            ->assertSessionHasErrors('payload.items');

        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), $this->question('listen_choose', ActivityFactory::payloadFor(ActivityType::ListenChoose)['items'][0]))
            ->assertSessionHasErrors('type');

        $this->assertSame(0, $this->test->questions()->count());
    }

    /**
     * @return array<string, array{0: string, 1: callable(array<string, mixed>, array{image: int, audio: int, video: int}): array<string, mixed>, 2: string}>
     */
    public static function invalidItems(): array
    {
        return [
            'matching with one pair' => ['matching', static fn (array $item): array => [
                ...$item,
                'prompts' => [['id' => '1', 'text' => 'Towel']],
                'pairs' => ['1' => 'a'],
            ], 'needs at least two pairs'],
            'matching with an empty side' => ['matching', static fn (array $item): array => [
                ...$item,
                'targets' => [['id' => 'a', 'text' => ''], ['id' => 'b', 'text' => 'Oreiller']],
            ], 'its word on both sides'],
            'matching pointing nowhere' => ['matching', static fn (array $item): array => [
                ...$item,
                'pairs' => ['1' => 'a', '2' => 'z'],
            ], 'matching answer for every word'],
            'fill in the blank without a blank' => ['fill_blank', static fn (array $item): array => [
                ...$item,
                'sentence' => 'May I see your passport, please?',
            ], 'Mark at least one blank'],
            'a blank without an accepted answer' => ['fill_blank', static fn (array $item): array => [
                ...$item,
                'blanks' => [['id' => 'b1', 'accepted' => ['passport']], ['id' => 'b2', 'accepted' => ['  ']]],
            ], 'accepted answers of every blank'],
            'a blank missing from the list' => ['fill_blank', static fn (array $item): array => [
                ...$item,
                'blanks' => [['id' => 'b1', 'accepted' => ['passport']]],
            ], 'accepted answers of every blank'],
            'a short answer without accepted answers' => ['short_answer', static fn (array $item): array => [
                ...$item,
                'accepted' => ['', ' '],
            ], 'at least one accepted answer'],
            'an audio question without audio' => ['audio_question', static fn (array $item): array => [
                ...$item,
                'audio' => null,
                'audio_text' => '',
            ], 'Add the audio'],
            'an image question without a picture' => ['image_question', static fn (array $item): array => [
                ...$item,
                'image' => null,
            ], 'Add the picture'],
            'a video question without a video' => ['video_question', static fn (array $item): array => [
                ...$item,
                'video' => null,
            ], 'Add the video'],
            'a video that is not a video' => ['video_question', static fn (array $item, array $media): array => [
                ...$item,
                'video' => $media['image'],
            ], 'video of question 1 was not found'],
            'picture answers without pictures' => ['multiple_choice', static fn (array $item): array => [
                ...$item,
                'option_style' => 'image',
            ], 'Add a picture to answer A'],
            'an ordering with one line' => ['ordering', static fn (array $item): array => [
                ...$item,
                'sentences' => [['id' => 's1', 'text' => 'Greet the guest.']],
                'order' => ['s1'],
            ], 'at least two lines'],
            'a writing task without a task' => ['writing', static fn (array $item): array => [
                'id' => 'i1',
                'min_words' => 10,
            ], 'Write the question 1'],
        ];
    }

    /**
     * @param  callable(array<string, mixed>, array{image: int, audio: int, video: int}): array<string, mixed>  $break
     */
    #[DataProvider('invalidItems')]
    public function test_an_item_missing_what_its_type_needs_is_refused(string $type, callable $break, string $message)
    {
        $item = $break(self::itemFor($type, $this->media), $this->media);

        $this->actingAs($this->owner)
            ->post(route('tests.questions.store', $this->test), $this->question($type, $item))
            ->assertSessionHasErrors('payload.items.0');

        $this->assertStringContainsString($message, (string) session('errors')?->first('payload.items.0'));
        $this->assertSame(0, $this->test->questions()->count());
    }

    public function test_matching_and_fill_in_the_blank_are_added_to_a_lesson_practice_block()
    {
        $lesson = $this->publishedLesson([BlockType::Practice, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();

        foreach (['matching', 'fill_blank'] as $type) {
            $this->actingAs($this->owner)
                ->post(route('activities.store'), [
                    ...$this->question($type, self::itemFor($type)),
                    'attempts_allowed' => 0,
                    'block_id' => $block->id,
                ])
                ->assertSessionHasNoErrors();
        }

        $types = $block->placements()->orderBy('position')->with('activity')->get()
            ->map(fn (ActivityPlacement $placement): ?string => $placement->activity?->type->value)
            ->all();
        $this->assertSame(['matching', 'fill_blank'], $types);

        // A lesson block is checked the same way.
        $this->actingAs($this->owner)
            ->post(route('activities.store'), [
                ...$this->question('fill_blank', [...self::itemFor('fill_blank'), 'blanks' => []]),
                'block_id' => $block->id,
            ])
            ->assertSessionHasErrors('payload.items.0');
    }

    public function test_the_csv_import_still_creates_questions_of_the_new_types()
    {
        $rows = "fill_blank;Could you please ___ this form?;fill in;eat;;;;;A\nshort_answer;What do you give at check-in?;key card;room key;;;;;\nwriting;Reply to a guest who lost a key.;;;;;;;";

        $this->actingAs($this->owner)
            ->post(route('tests.questions.import', $this->test), ['rows' => $rows])
            ->assertSessionHasNoErrors();

        $activities = $this->test->questions()->orderBy('position')->with('activity')->get()->pluck('activity');
        $this->assertSame(ActivityType::FillBlank, $activities[0]?->type);
        $this->assertSame('Could you please [[b1]] this form?', $activities[0]?->items()[0]['sentence']);
        $this->assertSame(['fill in'], $activities[0]?->items()[0]['blanks'][0]['accepted']);
        $this->assertSame(ActivityType::ShortAnswer, $activities[1]?->type);
        $this->assertSame(['key card', 'room key'], $activities[1]?->items()[0]['accepted']);
        $this->assertSame(ActivityType::Writing, $activities[2]?->type);
    }

    public function test_the_media_library_lists_audio_and_video_for_their_slots()
    {
        MediaAsset::query()->update(['library' => 'guesvia_library']);

        $this->actingAs($this->owner)
            ->getJson(route('media.index', ['kind' => 'audio']))
            ->assertOk()
            ->assertJsonPath('images.0.id', (string) $this->media['audio'])
            ->assertJsonCount(1, 'images');

        $this->actingAs($this->owner)
            ->getJson(route('media.index', ['kind' => 'video']))
            ->assertJsonPath('images.0.id', (string) $this->media['video'])
            ->assertJsonCount(1, 'images');
    }
}
