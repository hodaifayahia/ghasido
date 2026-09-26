<?php

namespace Tests\Feature\Content;

use App\Contracts\SynthesisedAudio;
use App\Contracts\TtsProvider;
use App\Enums\ActivityType;
use App\Enums\AudioSpeed;
use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Jobs\GenerateAudioClip;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\ActivityVersion;
use App\Models\AiScenario;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Policies\LessonPolicy;
use App\Policies\MediaAssetPolicy;
use App\Services\Ai\UsageMeter;
use App\Services\Audio\AudioLibrary;
use App\Services\Learning\ActivityScorer;
use Database\Factories\LessonFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * The content schema and the rules its models encode (spec 0003 B.3, B.4,
 * B.5; ORG-04, JOURNEY-01, CTRL-05, DATA-11, TEST-09).
 */
class ContentModelTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------- lesson denormalising

    public function test_a_lesson_copies_course_and_hotel_from_its_unit_on_save()
    {
        $hotel = Hotel::factory()->create();
        $course = Course::factory()->forHotel($hotel->id)->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);

        $lesson = Lesson::factory()->create(['unit_id' => $unit->id]);

        $this->assertSame($course->id, $lesson->course_id);
        $this->assertSame($hotel->id, $lesson->hotel_id);
    }

    public function test_moving_a_lesson_to_another_unit_moves_its_course_and_hotel()
    {
        $lesson = Lesson::factory()->create();
        $other = Unit::factory()->create(['course_id' => Course::factory()->forHotel(Hotel::factory()->create()->id)->create()->id]);

        $lesson->update(['unit_id' => $other->id]);
        $lesson->refresh();

        $this->assertSame($other->course_id, $lesson->course_id);
        $this->assertSame($other->course()->firstOrFail()->hotel_id, $lesson->hotel_id);
    }

    public function test_moving_a_course_between_hotels_carries_every_lesson()
    {
        $course = Course::factory()->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['unit_id' => $unit->id]);
        $this->assertNull($lesson->hotel_id);

        $hotel = Hotel::factory()->create();
        $course->update(['hotel_id' => $hotel->id]);

        $this->assertSame($hotel->id, $lesson->fresh()?->hotel_id);
    }

    public function test_moving_a_unit_to_another_course_carries_its_lessons()
    {
        $lesson = Lesson::factory()->create();
        $unit = $lesson->unit()->firstOrFail();
        $target = Course::factory()->forHotel(Hotel::factory()->create()->id)->create();

        $unit->update(['course_id' => $target->id]);

        $fresh = $lesson->fresh();
        $this->assertSame($target->id, $fresh?->course_id);
        $this->assertSame($target->hotel_id, $fresh?->hotel_id);
    }

    // ------------------------------------------------------- learner scope

    public function test_for_learner_returns_published_lessons_of_the_department_shared_or_own_hotel()
    {
        $hotel = Hotel::factory()->create();
        $otherHotel = Hotel::factory()->create();
        $reception = Department::factory()->create();
        $spa = Department::factory()->create();
        $learner = User::factory()->employee()->create(['hotel_id' => $hotel->id, 'department_id' => $reception->id]);

        $shared = $this->publishedLesson($reception, null);
        $own = $this->publishedLesson($reception, $hotel);
        $foreign = $this->publishedLesson($reception, $otherHotel);
        $wrongDepartment = $this->publishedLesson($spa, null);
        $draft = $this->publishedLesson($reception, null, publishLesson: false);
        $draftCourse = $this->publishedLesson($reception, null, publishCourse: false);

        $ids = Lesson::query()->forLearner($learner)->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$shared->id, $own->id])->sort()->values()->all(), $ids);
        $this->assertFalse($foreign->isVisibleTo($learner));
        $this->assertFalse($wrongDepartment->isVisibleTo($learner));
        $this->assertFalse($draft->isVisibleTo($learner));
        $this->assertFalse($draftCourse->isVisibleTo($learner));
    }

    public function test_a_learner_with_no_department_sees_no_content()
    {
        $learner = User::factory()->employee()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $this->publishedLesson(Department::factory()->create(), null);
        AiScenario::factory()->published()->create();

        $this->assertSame(0, Lesson::query()->forLearner($learner)->count());
        $this->assertSame(0, Course::query()->forLearner($learner)->count());
        $this->assertSame(0, AiScenario::query()->forLearner($learner)->count());
    }

    public function test_scenarios_for_learner_follow_the_same_rule()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();
        $learner = User::factory()->employee()->create(['hotel_id' => $hotel->id, 'department_id' => $department->id]);

        $shared = AiScenario::factory()->published()->forDepartment($department->id)->create();
        $own = AiScenario::factory()->published()->forDepartment($department->id)->forHotel($hotel->id)->create();
        AiScenario::factory()->published()->forDepartment($department->id)->forHotel(Hotel::factory()->create()->id)->create();
        AiScenario::factory()->published()->create();
        AiScenario::factory()->forDepartment($department->id)->create();

        $ids = AiScenario::query()->forLearner($learner)->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$shared->id, $own->id])->sort()->values()->all(), $ids);
    }

    // ------------------------------------------------------- lesson policy

    public function test_a_learner_cannot_open_a_visible_lesson_before_the_pre_test()
    {
        $department = Department::factory()->create();
        $learner = User::factory()->employee()->create(['hotel_id' => Hotel::factory()->create()->id, 'department_id' => $department->id]);
        $lesson = $this->publishedLesson($department, null);

        // No published Pre-test yet: lessons are open (JOURNEY-01, client
        // decision 2026-09-23). Once one exists, the gate applies.
        $this->assertTrue($learner->can('view', $lesson));

        Test::factory()->pre()->create(['department_id' => $department->id]);

        $this->assertTrue($lesson->isVisibleTo($learner));
        $this->assertFalse($learner->can('view', $lesson));
    }

    public function test_a_learner_can_open_a_visible_lesson_once_the_pre_test_is_submitted()
    {
        $department = Department::factory()->create();
        $learner = User::factory()->employee()->create(['hotel_id' => Hotel::factory()->create()->id, 'department_id' => $department->id]);
        $lesson = $this->publishedLesson($department, null);
        $this->submitPreTest($learner, $department);

        $this->assertTrue($learner->can('view', $lesson));
    }

    public function test_the_pre_test_never_unlocks_another_hotels_or_departments_lesson()
    {
        $department = Department::factory()->create();
        $learner = User::factory()->employee()->create(['hotel_id' => Hotel::factory()->create()->id, 'department_id' => $department->id]);
        $this->submitPreTest($learner, $department);

        $foreign = $this->publishedLesson($department, Hotel::factory()->create());
        $otherDepartment = $this->publishedLesson(Department::factory()->create(), null);

        $this->assertFalse($learner->can('view', $foreign));
        $this->assertFalse($learner->can('view', $otherDepartment));
    }

    public function test_a_super_admin_opens_any_lesson_without_a_pre_test()
    {
        $admin = User::factory()->superAdmin()->create();
        $lesson = $this->publishedLesson(Department::factory()->create(), Hotel::factory()->create(), publishLesson: false);

        $this->assertTrue($admin->can('view', $lesson));
        $this->assertTrue($admin->can('update', $lesson));
    }

    public function test_a_manager_reads_shared_and_own_hotel_lessons_but_not_another_hotels()
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $policy = new LessonPolicy;
        $department = Department::factory()->create();

        $this->assertTrue($policy->view($manager, $this->publishedLesson($department, null)));
        $this->assertTrue($policy->view($manager, $this->publishedLesson($department, $hotel)));
        $this->assertFalse($policy->view($manager, $this->publishedLesson($department, Hotel::factory()->create())));
        // Reading is not editing (ROLE-01: managers never change content).
        $this->assertFalse($policy->update($manager, $this->publishedLesson($department, $hotel)));
    }

    // ------------------------------------------------------- blocks & steps

    public function test_visible_blocks_are_ordered_by_position_and_skip_hidden_ones()
    {
        $lesson = Lesson::factory()->withBlocks()->create();

        $this->assertCount(9, $lesson->blocks);
        $this->assertSame(
            array_map(static fn (BlockType $type): string => $type->value, LessonFactory::DEFAULT_BLOCKS),
            $lesson->visibleBlocks()->get()->map(fn (Block $block): string => $block->type->value)->all(),
        );

        $lesson->blocks()->where('type', BlockType::Video->value)->update(['is_visible' => false]);
        Block::factory()->ofType(BlockType::Note)->hidden()->create(['lesson_id' => $lesson->id, 'position' => 0]);

        $visible = $lesson->visibleBlocks()->get();
        $this->assertCount(8, $visible);
        $this->assertSame(BlockType::Situation, $visible->first()?->type);
        $this->assertSame(BlockType::Complete, $visible->last()?->type);

        $this->assertSame(8, Lesson::query()->withCount('visibleBlocks')->findOrFail($lesson->id)->visible_blocks_count);
    }

    public function test_step_number_and_total_follow_unit_then_lesson_position()
    {
        $course = Course::factory()->published()->create();
        $unitB = Unit::factory()->at(2)->create(['course_id' => $course->id]);
        $unitA = Unit::factory()->at(1)->create(['course_id' => $course->id]);

        $a2 = Lesson::factory()->published()->at(2)->create(['unit_id' => $unitA->id]);
        $a1 = Lesson::factory()->published()->at(1)->create(['unit_id' => $unitA->id]);
        $b1 = Lesson::factory()->published()->at(1)->create(['unit_id' => $unitB->id]);
        $draft = Lesson::factory()->at(3)->create(['unit_id' => $unitA->id]);

        $this->assertSame(1, $a1->stepNumberIn($course));
        $this->assertSame(2, $a2->stepNumberIn($course));
        $this->assertSame(3, $b1->stepNumberIn($course));
        $this->assertSame(3, $b1->totalStepsIn($course));
        $this->assertSame(3, $course->lessonsTotal());

        // A draft is not a step for a learner, but the admin still counts it.
        $this->assertSame(0, $draft->stepNumberIn($course));
        $this->assertSame(3, $draft->stepNumberIn($course, publishedOnly: false));
        $this->assertSame(4, $course->lessonsTotal(publishedOnly: false));

        $this->assertSame($a2->id, $a1->nextIn($course)?->id);
        $this->assertSame($b1->id, $a2->nextIn($course)?->id);
        $this->assertNull($b1->nextIn($course));

        // The course is looked up from the lesson when not passed.
        $this->assertSame(2, $a2->stepNumberIn());
    }

    public function test_a_block_links_lexicon_items_in_order_and_activities_through_placements()
    {
        $block = Block::factory()->ofType(BlockType::Vocabulary)->create();
        $second = LexiconItem::factory()->create();
        $first = LexiconItem::factory()->create();
        $block->lexiconItems()->attach([$second->id => ['position' => 2], $first->id => ['position' => 1]]);

        $this->assertSame([$first->id, $second->id], $block->lexiconItems()->pluck('lexicon_items.id')->all());

        $practice = Block::factory()->ofType(BlockType::Practice)->create();
        $later = ActivityPlacement::factory()->in($practice)->at(2)->create();
        $earlier = ActivityPlacement::factory()->in($practice)->at(1)->overriding(['prompt' => 'Custom line.'])->create();

        $this->assertSame([$earlier->id, $later->id], $practice->placements()->pluck('id')->all());
        $this->assertSame('Custom line.', $earlier->effectivePrompt());
        $this->assertSame($later->activity()->firstOrFail()->prompt, $later->effectivePrompt());
        $this->assertInstanceOf(Block::class, $earlier->placeable);
    }

    // --------------------------------------------------------- versioning

    public function test_creating_an_activity_writes_version_one()
    {
        $activity = Activity::factory()->ofType(ActivityType::ListenChoose)->create();

        $this->assertSame(1, $activity->current_version);
        $this->assertCount(1, $activity->versions);
        $this->assertSame(1, $activity->currentVersion?->version);
        $this->assertEquals($activity->payload, $activity->currentVersion?->payload);
    }

    public function test_changing_the_payload_or_scoring_writes_a_new_version_and_keeps_the_old_one()
    {
        $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
        $original = $activity->payload;

        $changed = $original;
        $changed['items'][0]['correct'] = 'B';
        $activity->update(['payload' => $changed]);

        $this->assertSame(2, $activity->fresh()?->current_version);
        $this->assertSame(2, $activity->versions()->count());
        $this->assertSame('A', $activity->versions()->where('version', 1)->firstOrFail()->payload['items'][0]['correct']);
        $this->assertSame('B', $activity->fresh()?->currentVersion?->payload['items'][0]['correct']);

        $activity->update(['scoring' => ['partial' => true]]);
        $this->assertSame(3, $activity->fresh()?->current_version);
        $this->assertSame(['partial' => true], $activity->fresh()?->currentVersion?->scoring);

        // A change to anything else is not a new version.
        $activity->update(['title' => 'Renamed']);
        $this->assertSame(3, $activity->fresh()?->current_version);
        $this->assertSame(3, $activity->versions()->count());
    }

    public function test_the_scorer_judges_against_the_version_not_the_live_activity()
    {
        $activity = Activity::factory()->ofType(ActivityType::MultipleChoice)->create();
        /** @var ActivityVersion $v1 */
        $v1 = $activity->currentVersion;

        $changed = $activity->payload;
        $changed['items'][0]['correct'] = 'B';
        $activity->update(['payload' => $changed]);

        $scorer = new ActivityScorer;

        $this->assertTrue($scorer->score($v1, ['i1' => 'A'])->isCorrect);
        $this->assertTrue($scorer->score($activity->fresh()?->currentVersion ?? $v1, ['i1' => 'B'])->isCorrect);
    }

    // ----------------------------------------------------------- media

    public function test_a_public_asset_has_a_storage_url_and_a_private_one_goes_through_the_serve_route()
    {
        // The real route is the admin-routes lane's (spec 0003 Part D); the
        // model only needs its name to exist.
        Route::get('/media/{media}', fn () => 'ok')->name('media.show');
        Route::getRoutes()->refreshNameLookups();

        $public = MediaAsset::factory()->seed('situation-complaint')->create();
        $private = MediaAsset::factory()->recording()->create();

        // With `storage:link` the storage URL; without it (CI, fresh clones)
        // the copy shipped in public/content/seed. Never the serve route.
        $path = 'content/seed/situation-complaint.jpg';
        $expected = is_file(public_path('storage/'.$path)) ? Storage::disk('public')->url($path) : asset($path);
        $this->assertSame($expected, $public->url());
        $this->assertSame(route('media.show', $private), $private->url());
        $this->assertTrue($public->isImage());
        $this->assertTrue($private->isAudio());
        $this->assertSame(MediaLibrary::Recordings, $private->library);
    }

    public function test_media_policy_opens_public_files_owner_files_and_own_hotel_files_only()
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);
        $colleague = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $outsider = User::factory()->manager()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $homeless = User::factory()->manager()->create();
        $policy = new MediaAssetPolicy;

        $public = MediaAsset::factory()->create();
        $recording = MediaAsset::factory()->recording()->uploadedBy($employee->id)->forHotel($hotel->id)->create();

        foreach ([$employee, $colleague, $outsider, $homeless] as $user) {
            $this->assertTrue($policy->view($user, $public));
        }

        $this->assertTrue($policy->view($employee, $recording));
        $this->assertTrue($policy->view($colleague, $recording));
        $this->assertFalse($policy->view($outsider, $recording));
        $this->assertFalse($policy->view($homeless, $recording));

        $orphan = MediaAsset::factory()->recording()->create();
        $this->assertFalse($policy->view($homeless, $orphan));
        $this->assertTrue(User::factory()->superAdmin()->create()->can('view', $orphan));
    }

    // ----------------------------------------------------------- audio

    public function test_the_clip_hash_ignores_surrounding_and_repeated_whitespace()
    {
        $this->assertSame(AudioClip::hashFor('How can I help you?'), AudioClip::hashFor("  How  can\nI help you? "));
        $this->assertNotSame(AudioClip::hashFor('How can I help you?'), AudioClip::hashFor('How can I help you'));
        $this->assertSame(64, strlen(AudioClip::hashFor('x')));
    }

    public function test_ensure_creates_one_pending_clip_per_text_and_speed_and_queues_it_once()
    {
        Queue::fake();
        $library = app(AudioLibrary::class);

        $first = $library->ensure('How can I help you?', AudioSpeed::Normal);
        $again = $library->ensure(' How can  I help you? ', AudioSpeed::Normal);
        $slow = $library->ensure('How can I help you?', AudioSpeed::Slow);

        $this->assertSame($first->id, $again->id);
        $this->assertNotSame($first->id, $slow->id);
        $this->assertSame(GenerationStatus::Pending, $first->status);
        $this->assertSame('How can I help you?', $first->text);
        $this->assertSame(config('services.tts.voice', 'guesvia-en'), $first->voice);
        $this->assertSame(2, AudioClip::query()->count());

        Queue::assertPushed(GenerateAudioClip::class, 2);
        Queue::assertPushed(GenerateAudioClip::class, fn (GenerateAudioClip $job): bool => $job->clipId === $first->id);
    }

    public function test_ensure_requeues_a_failed_clip_but_leaves_a_done_one_alone()
    {
        Queue::fake();
        $library = app(AudioLibrary::class);

        $failed = AudioClip::factory()->failed()->create(['text' => 'Try again', 'voice' => $library->voice()]);
        $done = AudioClip::factory()->done()->create(['text' => 'All good', 'voice' => $library->voice()]);

        $library->ensure('Try again', AudioSpeed::Normal);
        $library->ensure('All good', AudioSpeed::Normal);

        $this->assertSame(GenerationStatus::Pending, $failed->fresh()?->status);
        $this->assertSame(GenerationStatus::Done, $done->fresh()?->status);
        Queue::assertPushed(GenerateAudioClip::class, 1);
    }

    public function test_urls_for_resolves_every_text_in_one_pass_and_never_queues()
    {
        Queue::fake();
        $library = app(AudioLibrary::class);
        $voice = $library->voice();

        AudioClip::factory()->done()->create(['text' => 'Towel', 'voice' => $voice]);
        AudioClip::factory()->done()->slow()->create(['text' => 'Towel', 'voice' => $voice]);
        AudioClip::factory()->done()->create(['text' => 'Pillow', 'voice' => $voice]);
        AudioClip::factory()->create(['text' => 'Room key', 'voice' => $voice]); // pending

        $urls = $library->urlsFor(['Towel', 'Pillow', 'Room key', 'Slippers', '  ', 'towel ']);

        $this->assertSame(['Towel', 'Pillow', 'Room key', 'Slippers', 'towel '], array_keys($urls));
        $this->assertNotNull($urls['Towel']['normal']);
        $this->assertNotNull($urls['Towel']['slow']);
        $this->assertNotSame($urls['Towel']['normal'], $urls['Towel']['slow']);
        $this->assertNotNull($urls['Pillow']['normal']);
        $this->assertNull($urls['Pillow']['slow']);
        $this->assertSame(['normal' => null, 'slow' => null], $urls['Room key']);
        $this->assertSame(['normal' => null, 'slow' => null], $urls['Slippers']);
        // Hashing is case sensitive: "towel" is a different sentence.
        $this->assertSame(['normal' => null, 'slow' => null], $urls['towel ']);

        $this->assertSame($urls['Towel']['normal'], $library->urlFor(' Towel ', AudioSpeed::Normal));
        $this->assertNull($library->urlFor('Slippers', AudioSpeed::Normal));
        Queue::assertNothingPushed();
    }

    public function test_the_generate_job_stores_the_file_and_marks_the_clip_done()
    {
        Storage::fake('public');
        $this->app->instance(TtsProvider::class, new class implements TtsProvider
        {
            public function synthesise(string $text, string $voice, AudioSpeed $speed): SynthesisedAudio
            {
                return new SynthesisedAudio('RIFF-bytes-'.$speed->value, 'audio/wav', 'wav', 600);
            }
        });

        $clip = AudioClip::factory()->slow()->create();

        (new GenerateAudioClip($clip->id))->handle(app(TtsProvider::class), app(UsageMeter::class));

        $clip->refresh();
        $this->assertSame(GenerationStatus::Done, $clip->status);
        $this->assertNotNull($clip->generated_at);
        $this->assertNotNull($clip->url());

        $asset = $clip->mediaAsset()->firstOrFail();
        $this->assertSame(MediaKind::Audio, $asset->kind);
        $this->assertSame(MediaLibrary::Generated, $asset->library);
        $this->assertSame('audio/wav', $asset->mime);
        $this->assertSame(600, $asset->duration_ms);
        $this->assertMatchesRegularExpression('#^content/audio/\d{4}/\d{2}/[0-9a-f-]{36}\.wav$#', $asset->path);
        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame('RIFF-bytes-slow', Storage::disk('public')->get($asset->path));

        // Running it again is a no-op: one clip, one file, one billed call.
        (new GenerateAudioClip($clip->id))->handle(app(TtsProvider::class), app(UsageMeter::class));
        $this->assertSame(1, MediaAsset::query()->count());
    }

    public function test_the_generate_job_records_the_failure_reason()
    {
        $clip = AudioClip::factory()->create();

        (new GenerateAudioClip($clip->id))->failed(new RuntimeException('Provider returned 502.'));

        $clip->refresh();
        $this->assertSame(GenerationStatus::Failed, $clip->status);
        $this->assertSame('Provider returned 502.', $clip->failed_reason);
        $this->assertNull($clip->url());
    }

    // -------------------------------------------------------------- enums

    public function test_block_and_activity_types_carry_the_spec_labels()
    {
        $this->assertSame('Listen', BlockType::ListenRepeat->stepLabel());
        $this->assertSame('Listen & Repeat', BlockType::ListenRepeat->heading());
        $this->assertSame('Video – Watch the Situation', BlockType::Video->heading());
        $this->assertSame('Lesson Completed!', BlockType::Complete->heading());
        $this->assertSame('Writing', BlockType::EmailActivity->stepLabel());

        $this->assertSame('Put the Dialogue in Order', ActivityType::DialogueOrder->label());
        $this->assertSame('Look at the picture and choose the correct audio.', ActivityType::LookListen->hubDescription());
        $this->assertSame('sunset', ActivityType::BestResponse->tone());
        $this->assertSame('blossom', ActivityType::WatchRespond->tone());
        $this->assertTrue(ActivityType::ListenMatch->isAutoScored());
        $this->assertFalse(ActivityType::Speaking->isAutoScored());

        foreach (BlockType::cases() as $type) {
            $this->assertNotSame('', $type->icon());
            $this->assertNotSame('', $type->description());
        }
    }

    // -------------------------------------------------------------- helpers

    private function publishedLesson(Department $department, ?Hotel $hotel, bool $publishLesson = true, bool $publishCourse = true): Lesson
    {
        $course = Course::factory()
            ->forDepartment($department->id)
            ->when($hotel !== null, fn ($factory) => $factory->forHotel($hotel?->id ?? 0))
            ->when($publishCourse, fn ($factory) => $factory->published())
            ->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()
            ->when($publishLesson, fn ($factory) => $factory->published())
            ->create(['unit_id' => $unit->id]);
    }

    private function submitPreTest(User $learner, Department $department): void
    {
        $test = Test::factory()->pre()->create(['department_id' => $department->id]);

        TestAttempt::factory()->submitted()->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
        ]);
    }
}
