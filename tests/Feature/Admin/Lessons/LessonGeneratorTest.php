<?php

namespace Tests\Feature\Admin\Lessons;

use App\Contracts\AiProvider;
use App\Enums\ActivityType;
use App\Enums\AiFeature;
use App\Enums\BlockType;
use App\Enums\ContentGenerationType;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\LexiconKind;
use App\Jobs\GenerateAudioClip;
use App\Jobs\GenerateContentImage;
use App\Jobs\GenerateLessonFromPrompt;
use App\Models\Activity;
use App\Models\AiUsage;
use App\Models\ContentGeneration;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Services\Ai\UsageMeter;
use App\Services\Content\LessonGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Generate with AI" (GEN-01, GEN-03, GEN-04, PERF-04, AIL-01..03, DATA-11;
 * spec 0004): one prompt becomes a draft lesson with its blocks in the
 * default order, lexicon items, versioned activities, images and audio; a
 * course prompt fans out one job per lesson; the daily limit stops the
 * request before anything is queued.
 */
class LessonGeneratorTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ai.provider', 'fake');
        config()->set('services.ai.image_provider', 'fake');
        config()->set('services.tts.provider', 'fake');

        $this->buildContent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'prompt' => 'Checking in a guest who arrives late at night',
            'department_id' => $this->department->id,
            'hotel_id' => null,
            'level' => 'beginner',
            'mode' => 'lesson',
            'lesson_count' => 1,
            'course_id' => null,
            'images' => true,
            'audio' => true,
        ];
    }

    /**
     * A throwaway public disk in the system temp dir (Storage::fake's own
     * folder may be left read-only by another run).
     */
    private function fakePublicDisk(): void
    {
        $root = sys_get_temp_dir().'/guesvia-lesson-generator-'.uniqid();
        Storage::set('public', Storage::createLocalDriver(['root' => $root, 'url' => '/storage']));

        $this->beforeApplicationDestroyed(fn () => (new Filesystem)->deleteDirectory($root));
    }

    private function runLessonJob(int $generationId, ?int $index): void
    {
        (new GenerateLessonFromPrompt($generationId, $index))->handle(
            app(AiProvider::class),
            app(UsageMeter::class),
            app(LessonGenerator::class),
        );
    }

    public function test_the_request_creates_a_draft_course_and_queues_the_lesson_job()
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.store'), $this->payload())
            ->assertStatus(202)
            ->assertJsonPath('generation.state', 'queued');

        $generation = ContentGeneration::query()->findOrFail($response->json('generation.id'));

        $this->assertSame(ContentGenerationType::Lesson, $generation->type);
        $this->assertSame(1, $generation->lessons_total);
        $course = Course::query()->findOrFail($generation->course_id);
        $this->assertSame(ContentStatus::Draft, $course->status);
        $this->assertSame($this->department->id, $course->department_id);

        Queue::assertPushed(GenerateLessonFromPrompt::class, fn (GenerateLessonFromPrompt $job): bool => $job->generationId === $generation->id && $job->lessonIndex === 0);
    }

    public function test_the_lesson_job_writes_a_draft_lesson_with_blocks_lexicon_activities_and_queues_media()
    {
        Queue::fake();

        $id = $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.store'), $this->payload())
            ->json('generation.id');

        $this->runLessonJob($id, 0);

        $generation = ContentGeneration::query()->findOrFail($id);
        $lesson = Lesson::query()->findOrFail($generation->lessonIdFor(0));

        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertSame(GenerationStatus::Done, $lesson->ai_status);
        $this->assertNotEmpty($lesson->introduction);
        $this->assertCount(3, $lesson->objectives ?? []);

        $this->assertSame(
            [BlockType::Situation, BlockType::Vocabulary, BlockType::Expressions, BlockType::ListenRepeat, BlockType::Dialogue, BlockType::Practice, BlockType::Complete],
            $lesson->blocks()->get()->map(fn ($block) => $block->type)->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $lesson->blocks()->pluck('position')->all());

        $vocabulary = $lesson->blocks()->where('type', BlockType::Vocabulary->value)->firstOrFail();
        $words = $vocabulary->lexiconItems()->get();
        $this->assertCount(6, $words);
        $this->assertTrue($words->every(fn (LexiconItem $item): bool => $item->kind === LexiconKind::Word && $item->source === LexiconItem::SOURCE_AI && $item->arabic_meaning !== null));
        $this->assertSame($words->first()?->id, $vocabulary->setting('featured_lexicon_item_id'));

        $dialogue = $lesson->blocks()->where('type', BlockType::Dialogue->value)->firstOrFail();
        $lines = $dialogue->setting('lines');
        $this->assertIsArray($lines);
        $this->assertSame('guest', $lines[0]['speaker']);

        $practice = $lesson->blocks()->where('type', BlockType::Practice->value)->firstOrFail();
        $activities = Activity::query()->whereIn('id', $practice->placements()->pluck('activity_id'))->get();
        $this->assertEqualsCanonicalizing([ActivityType::MultipleChoice, ActivityType::DialogueOrder], $activities->map(fn (Activity $a) => $a->type)->all());
        $this->assertTrue($activities->every(fn (Activity $a): bool => $a->versions()->count() === 1 && $a->current_version === 1));

        // Images: the situation cover + one per vocabulary word (7), audio
        // for every playable sentence, all queued (TTS-02, PERF-04).
        Queue::assertPushed(GenerateContentImage::class, 7);
        Queue::assertPushed(GenerateAudioClip::class);
        $this->assertSame(7, $generation->images_total);
        $this->assertNotEmpty($generation->audio_texts);
        $this->assertSame(1, AiUsage::query()->forFeature(AiFeature::LessonGenerate)->count());

        // A retried job for the same index writes nothing twice.
        $this->runLessonJob($id, 0);
        $this->assertSame(1, Lesson::query()->where('course_id', $generation->course_id)->count());
    }

    public function test_a_full_run_ends_done_with_images_attached_and_audio_stored()
    {
        $this->fakePublicDisk();

        $id = $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.store'), $this->payload())
            ->assertStatus(202)
            ->json('generation.id');

        $generation = ContentGeneration::query()->findOrFail($id);
        $lesson = Lesson::query()->findOrFail($generation->lessonIdFor(0));

        $this->assertSame(GenerationStatus::Done, $generation->status);
        $this->assertSame(7, $generation->images_done);
        $this->assertNotNull($lesson->cover_media_id);

        $cover = MediaAsset::query()->findOrFail($lesson->cover_media_id);
        $this->assertNotEmpty($cover->alt_text);
        $this->assertStringStartsWith('content/images/', $cover->path);
        Storage::disk('public')->assertExists($cover->path);

        // Every generated word has its picture; expressions have none.
        $this->assertSame(0, LexiconItem::query()->where('source', LexiconItem::SOURCE_AI)->where('kind', LexiconKind::Word->value)->whereNull('image_media_id')->count());
        $this->assertSame(7, AiUsage::query()->forFeature(AiFeature::ImageGenerate)->count());

        $this->actingAs($this->owner)
            ->getJson(route('lesson-generations.show', $generation))
            ->assertOk()
            ->assertJsonPath('generation.state', 'done')
            ->assertJsonPath('generation.openUrl', route('lessons.edit', ['lesson' => $lesson->id]));
    }

    public function test_a_course_prompt_plans_an_outline_and_dispatches_one_job_per_lesson()
    {
        Queue::fake();

        $id = $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.store'), $this->payload([
                'prompt' => 'Front desk English for the first week',
                'mode' => 'course',
                'lesson_count' => 3,
            ]))
            ->assertStatus(202)
            ->json('generation.id');

        Queue::assertPushed(GenerateLessonFromPrompt::class, fn (GenerateLessonFromPrompt $job): bool => $job->lessonIndex === null);

        $this->runLessonJob($id, null);

        $generation = ContentGeneration::query()->findOrFail($id);
        $this->assertCount(3, $generation->outline['lessons'] ?? []);
        $this->assertSame(3, $generation->lessons_total);
        $this->assertSame('Front desk English for the first week', Course::query()->findOrFail($generation->course_id)->title);

        foreach ([0, 1, 2] as $index) {
            Queue::assertPushed(GenerateLessonFromPrompt::class, fn (GenerateLessonFromPrompt $job): bool => $job->generationId === $id && $job->lessonIndex === $index);
        }
    }

    public function test_the_daily_limit_blocks_the_request_before_anything_is_queued()
    {
        Queue::fake();
        config()->set('guesvia.ai.limits.per_admin_daily_generated_lessons', 2);

        ContentGeneration::factory()->course(2)->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.store'), $this->payload())
            ->assertStatus(429)
            ->assertJsonFragment(['message' => __("You have used today's AI lesson limit (:limit lessons). Existing lessons stay editable; try again tomorrow.", ['limit' => 2])]);

        Queue::assertNothingPushed();
        $this->assertSame(1, ContentGeneration::query()->count());
    }

    public function test_a_failed_generation_is_retried_for_the_missing_part_only()
    {
        Queue::fake();

        $generation = ContentGeneration::factory()->failed()->create([
            'user_id' => $this->owner->id,
            'department_id' => $this->department->id,
            'course_id' => $this->course->id,
            'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.retry', $generation))
            ->assertStatus(202)
            ->assertJsonPath('generation.status', 'pending');

        Queue::assertPushed(GenerateLessonFromPrompt::class, 1);
    }

    public function test_another_admins_generation_is_forbidden()
    {
        $generation = ContentGeneration::factory()->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->manager())
            ->getJson(route('lesson-generations.show', $generation))
            ->assertForbidden();
    }

    public function test_a_manager_cannot_generate_into_another_hotels_scope()
    {
        Queue::fake();
        $other = Hotel::factory()->create();

        $this->actingAs($this->manager())
            ->postJson(route('lesson-generations.store'), $this->payload(['hotel_id' => $other->id]))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_an_editor_slot_image_is_generated_and_attached_to_its_lexicon_item()
    {
        $this->fakePublicDisk();

        $item = LexiconItem::factory()->create(['english_text' => 'towel', 'image_media_id' => null]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('lesson-generations.image'), [
                'prompt' => 'A folded white hotel towel',
                'alt' => 'A folded white towel',
                'size' => 'square',
                'target_type' => 'lexicon',
                'target_id' => $item->id,
            ])
            ->assertStatus(202)
            ->assertJsonPath('generation.status', 'done')
            ->assertJsonPath('generation.media.alt', 'A folded white towel');

        $this->assertSame($response->json('generation.media.id'), $item->refresh()->image_media_id);
    }
}
