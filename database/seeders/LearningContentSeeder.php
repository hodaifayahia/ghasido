<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\ActivityType;
use App\Enums\AutomationTrigger;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\DepartmentStatus;
use App\Enums\LexiconKind;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Enums\RoleplayStatus;
use App\Enums\ScenarioDifficulty;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\AudioClip;
use App\Models\AutomationRule;
use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Audio\AudioLibrary;
use App\Services\Learning\ActivityScorer;
use App\Services\Reminders\TemplateRenderer;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The learning content behind the employee journey (spec 0003 Part G).
 *
 * Everything the mockups in desginphotos/employ/ show is transcribed into the
 * data files under database/seeders/data/ and written here: the catalogue
 * focus lines (G.1), the "Guest Service Basics" course with its fully built
 * first lesson (G.2, G.3), the paired Reception Pre-test and Post-test (G.4),
 * the six AI role-play scenarios (G.5), the demo accounts samira and amine
 * with their progress (G.6, G.7), progress spread over the portfolio, and the
 * reminder templates, rules and log the Messages screen reads.
 *
 * Idempotent: every row is keyed on a slug, a title, a path, a username or a
 * unique pair, so running it twice leaves one of everything.
 *
 * Model events are switched back on for the duration of this seeder.
 * DatabaseSeeder uses WithoutModelEvents, which mutes every nested seeder,
 * but the content models carry rules in their hooks that this data depends
 * on: Activity writes activity_versions v1 on create (DATA-11, TEST-09),
 * Lesson copies course_id and hotel_id from its unit, AudioClip derives its
 * hash. Seeding without them would leave the rows the models never allow.
 */
class LearningContentSeeder extends Seeder
{
    public const HOTEL_NAME = "La Gazelle d'Or";

    public const COURSE_SLUG = 'guest-service-basics';

    public const LESSON_SLUG = 'handling-guest-complaints';

    public const PRE_TEST_TITLE = 'Reception Pre-test';

    public const POST_TEST_TITLE = 'Reception Post-test';

    private const MEDIA_PREFIX = 'media:';

    private const SEED_MEDIA_DIR = 'content/seed';

    /**
     * Usernames this seeder hands out, so a portfolio employee is never
     * claimed twice.
     *
     * @var list<string>
     */
    private array $reservedUsernames = [];

    /** @var array<string, int> */
    private array $mediaIds = [];

    /**
     * Manifest names referenced by the data files but absent from
     * seed-media.json; rows are created anyway and the names reported.
     *
     * @var list<string>
     */
    private array $missingMedia = [];

    /** @var array<string, true> */
    private array $playableTexts = [];

    private AudioLibrary $audio;

    public function run(): void
    {
        $this->audio = app(AudioLibrary::class);

        $muted = Model::getEventDispatcher();
        Model::setEventDispatcher(app(Dispatcher::class));

        try {
            $this->seedAll();
        } finally {
            if ($muted !== null) {
                Model::setEventDispatcher($muted);
            }
        }
    }

    private function seedAll(): void
    {
        $departments = $this->seedDepartments();
        $reception = $departments['Reception'];

        $this->seedMedia();

        $scenarios = $this->seedScenarios($reception);
        $lesson = $this->seedMainCourse($reception, $scenarios);
        $this->seedOtherCourses($departments);
        [$pre, $post] = $this->seedTests($reception);

        $this->seedAudio();

        $templates = $this->seedReminderTemplates();
        $rules = $this->seedAutomationRules($templates, $departments);

        $hotel = $this->hotel();
        $samira = $this->seedSamira($hotel, $reception);
        $amine = $this->seedAmine($hotel, $reception, $lesson, $pre, $scenarios['check-in']);
        $this->seedNamedEmployees($departments);
        $this->seedPortfolioProgress($samira, $amine, $pre, $post, $scenarios);
        $this->seedReminderLog($templates, $rules);

        $this->report();
    }

    // ------------------------------------------------------------ departments

    /**
     * G.1: the catalogue rows with their focus line, keyed by name.
     *
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        $departments = [];
        $position = 0;

        foreach ($this->arr($this->data('course'), 'department_focus') as $name => $focus) {
            $name = (string) $name;
            $department = Department::query()
                ->whereNull('hotel_id')
                ->where('slug', Str::slug($name))
                ->first();

            if ($department === null) {
                $department = new Department([
                    'hotel_id' => null,
                    'slug' => Str::slug($name),
                    'name' => $name,
                    'position' => $position,
                    'is_active' => true,
                ]);
            }

            $department->fill([
                'focus' => is_string($focus) ? $focus : null,
                'status' => DepartmentStatus::Active,
            ])->save();

            $departments[$name] = $department;
            $position++;
        }

        return $departments;
    }

    // ------------------------------------------------------------------ media

    /**
     * One media_assets row per manifest entry, keyed by path (MED-02).
     */
    private function seedMedia(): void
    {
        $manifestPath = database_path('seeders/data/seed-media.json');
        $manifest = [];

        if (is_file($manifestPath)) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);
            $manifest = is_array($decoded) ? $decoded : [];
        }

        foreach ($manifest as $name => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $file = $this->strn($entry, 'file') ?? self::SEED_MEDIA_DIR.'/'.$name.'.jpg';

            $this->mediaIds[(string) $name] = $this->mediaRow(
                (string) $name,
                $file,
                $this->strn($entry, 'alt'),
                $this->intn($entry, 'width'),
                $this->intn($entry, 'height'),
                $this->strn($entry, 'category'),
            )->id;
        }
    }

    private function mediaRow(string $name, string $file, ?string $alt, ?int $width, ?int $height, ?string $category): MediaAsset
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $asset = MediaAsset::query()->firstOrNew([
            'disk' => MediaAsset::DISK_PUBLIC,
            'path' => $file,
        ]);

        $asset->fill([
            'original_name' => basename($file),
            'mime' => $mime,
            'kind' => MediaKind::Image,
            'alt_text' => $alt ?? Str::headline($name),
            'width' => $width,
            'height' => $height,
            'library' => MediaLibrary::Seed,
            'category' => $category,
            'label' => $name,
            'hotel_id' => null,
            'uploaded_by' => null,
        ])->save();

        $this->publishSeedFile($file);

        return $asset;
    }

    /**
     * The crops ship in the repo under public/content/seed (spec 0003 Part F)
     * while the rows live on the `public` disk (Part G), whose URL is
     * /storage/<path>. Copy the file onto that disk once so the URL resolves
     * on a fresh clone without a hand-made symlink.
     */
    private function publishSeedFile(string $file): void
    {
        $source = public_path($file);
        $disk = Storage::disk(MediaAsset::DISK_PUBLIC);

        if (! is_file($source) || $disk->exists($file)) {
            return;
        }

        $contents = file_get_contents($source);

        if ($contents !== false) {
            $disk->put($file, $contents);
        }
    }

    /**
     * The media_assets id for a manifest name. A name the asset lane did not
     * ship still gets a row at the path the page expects, and is reported.
     */
    private function mediaId(string $name): int
    {
        if (! isset($this->mediaIds[$name])) {
            $this->missingMedia[] = $name;
            $this->mediaIds[$name] = $this->mediaRow(
                $name,
                self::SEED_MEDIA_DIR.'/'.$name.'.jpg',
                null,
                null,
                null,
                null,
            )->id;
        }

        return $this->mediaIds[$name];
    }

    /**
     * Replace every `media:<name>` string in a payload with the row id.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function resolveMedia(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->resolveMedia($item);
            } elseif (is_string($item) && str_starts_with($item, self::MEDIA_PREFIX)) {
                $value[$key] = $this->mediaId(substr($item, strlen(self::MEDIA_PREFIX)));
            }
        }

        return $value;
    }

    private function mediaRef(?string $reference): ?int
    {
        if ($reference === null || ! str_starts_with($reference, self::MEDIA_PREFIX)) {
            return null;
        }

        return $this->mediaId(substr($reference, strlen(self::MEDIA_PREFIX)));
    }

    // ------------------------------------------------------------------ audio

    private function playable(?string $text): void
    {
        if ($text !== null && trim($text) !== '') {
            $this->playableTexts[$text] = true;
        }
    }

    /**
     * Collect every sentence a payload plays: `audio_text` and
     * `guest_audio_text` always; `text` under options, sentences, lines, items
     * and example when `$textIsPlayable` (lesson content), never for test
     * questions, whose option texts are read, not played.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function collectPlayable(array $value, bool $textIsPlayable): void
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $this->collectPlayable($item, $textIsPlayable);

                continue;
            }

            if (! is_string($item)) {
                continue;
            }

            if ($key === 'audio_text' || $key === 'guest_audio_text' || ($textIsPlayable && $key === 'text')) {
                $this->playable($item);
            }
        }
    }

    /**
     * Every collected sentence gets a normal and a slow clip (CTRL-05,
     * TTS-01, TTS-02). With QUEUE_CONNECTION=sync and TTS_PROVIDER=fake the
     * files are written here and now; on a database queue the clips stay
     * pending until a worker runs.
     */
    private function seedAudio(): void
    {
        foreach (FakeAiProvider::$scripts as $lines) {
            foreach ($lines as $line) {
                $this->playable($line);
            }
        }

        $this->playable(FakeAiProvider::CLOSING_LINE);

        foreach (array_keys($this->playableTexts) as $text) {
            $this->audio->ensureBoth((string) $text);
        }
    }

    // ---------------------------------------------------------------- courses

    /**
     * G.2 + G.3: the main course, its eight lessons, and the fully built
     * first lesson. Returns lesson 1.
     *
     * @param  array<string, AiScenario>  $scenarios
     */
    private function seedMainCourse(Department $reception, array $scenarios): Lesson
    {
        $data = $this->arr($this->data('course'), 'main_course');

        $course = $this->upsertCourse(
            $this->str($data, 'slug'),
            $this->str($data, 'title'),
            $reception,
            $this->str($data, 'tone'),
            $this->strn($data, 'description'),
            0,
            $this->mediaId('situation-complaint'),
        );

        $lessonOne = null;
        $lessonPosition = 0;

        foreach ($this->arr($data, 'units') as $unitPosition => $unitData) {
            $unitData = $this->row($unitData);
            $unit = $this->upsertUnit($course, $this->str($unitData, 'title'), (int) $unitPosition);

            foreach ($this->arr($unitData, 'lessons') as $lessonData) {
                $lessonData = $this->row($lessonData);
                $lessonPosition++;

                if (($lessonData['full'] ?? false) === true) {
                    $lessonOne = $this->seedFullLesson($unit, $lessonPosition, $scenarios);

                    continue;
                }

                $this->seedLightLesson($unit, $lessonData, $lessonPosition);
            }
        }

        if ($lessonOne === null) {
            throw new RuntimeException('course.php names no fully built lesson.');
        }

        return $lessonOne;
    }

    /**
     * The other five courses the admin tree shows (G.2).
     *
     * @param  array<string, Department>  $departments
     */
    private function seedOtherCourses(array $departments): void
    {
        $position = 1;

        foreach ($this->arr($this->data('course'), 'other_courses') as $courseData) {
            $courseData = $this->row($courseData);
            $department = $departments[$this->str($courseData, 'department')]
                ?? throw new RuntimeException('Unknown department in course.php.');

            $course = $this->upsertCourse(
                $this->str($courseData, 'slug'),
                $this->str($courseData, 'title'),
                $department,
                $this->str($courseData, 'tone'),
                $this->strn($courseData, 'description'),
                $position++,
                null,
            );

            $unit = $this->upsertUnit($course, $this->str($courseData, 'unit'), 0);
            $lessonPosition = 0;

            foreach ($this->arr($courseData, 'lessons') as $lessonData) {
                $lessonData = $this->row($lessonData);
                $lessonPosition++;

                $lesson = $this->upsertLesson($unit, $this->str($lessonData, 'slug'), [
                    'title' => $this->str($lessonData, 'title'),
                    'introduction' => $this->strn($lessonData, 'introduction'),
                    'objectives' => ['Understand the situation', 'Learn useful language', 'Practice and be ready for real situations'],
                    'cover_media_id' => $this->mediaId('video-poster-reception'),
                    'estimated_minutes' => 10,
                    'position' => $lessonPosition,
                ]);

                $this->upsertBlock($lesson, 1, BlockType::Situation, $this->situationSettings(null));
                $this->upsertBlock($lesson, 2, BlockType::Complete, $this->completeSettings());
            }
        }
    }

    private function upsertCourse(string $slug, string $title, Department $department, string $tone, ?string $description, int $position, ?int $coverId): Course
    {
        $course = Course::query()->firstOrNew(['slug' => $slug]);

        $course->fill([
            'department_id' => $department->id,
            'hotel_id' => null,
            'title' => $title,
            'description' => $description,
            'tone' => $tone,
            'position' => $position,
            'status' => ContentStatus::Published,
            'published_at' => $course->published_at ?? now()->subDays(30),
            'cover_media_id' => $coverId,
        ])->save();

        return $course;
    }

    private function upsertUnit(Course $course, string $title, int $position): Unit
    {
        $unit = Unit::query()->firstOrNew(['course_id' => $course->id, 'title' => $title]);

        $unit->fill([
            'position' => $position,
            'status' => ContentStatus::Published,
        ])->save();

        return $unit;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function upsertLesson(Unit $unit, string $slug, array $attributes): Lesson
    {
        $lesson = Lesson::query()->firstOrNew(['unit_id' => $unit->id, 'slug' => $slug]);

        $lesson->fill($attributes + [
            'status' => ContentStatus::Published,
            'published_at' => $lesson->published_at ?? now()->subDays(30),
            'completion_condition' => ['type' => 'all_blocks'],
        ])->save();

        return $lesson;
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private function upsertBlock(Lesson $lesson, int $position, BlockType $type, array $settings, ?string $title = null): Block
    {
        $block = Block::query()->firstOrNew(['lesson_id' => $lesson->id, 'position' => $position]);

        $block->fill([
            'type' => $type,
            'title' => $title,
            'layout' => 'full',
            'settings' => $this->resolveMedia($settings),
            'is_visible' => true,
        ])->save();

        if ($type === BlockType::AiRoleplay) {
            $scenarioIds = is_array($settings['scenario_ids'] ?? null)
                ? array_values(array_unique(array_map('intval', array_filter($settings['scenario_ids'], 'is_numeric'))))
                : [];
            $pivot = [];

            foreach ($scenarioIds as $index => $scenarioId) {
                $pivot[$scenarioId] = ['position' => $index + 1];
            }

            $block->scenarios()->sync($pivot);
        }

        return $block;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function situationSettings(?string $quote): array
    {
        $situation = $this->arr($this->data('lesson-handling-complaints'), 'situation');

        return [
            'quote' => $quote ?? $this->str($situation, 'quote'),
            'objectives' => $this->arr($situation, 'objectives'),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function completeSettings(): array
    {
        return $this->arr($this->data('lesson-handling-complaints'), 'complete');
    }

    // -------------------------------------------------------- the full lesson

    /**
     * G.3: nine visible blocks in mockup order.
     *
     * @param  array<string, AiScenario>  $scenarios
     */
    private function seedFullLesson(Unit $unit, int $position, array $scenarios): Lesson
    {
        $data = $this->data('lesson-handling-complaints');
        $lessonData = $this->arr($data, 'lesson');

        $lesson = $this->upsertLesson($unit, self::LESSON_SLUG, [
            'title' => $this->str($lessonData, 'title'),
            'introduction' => $this->str($lessonData, 'introduction'),
            'objectives' => $this->arr($lessonData, 'objectives'),
            'cover_media_id' => $this->mediaRef($this->strn($lessonData, 'cover')),
            'estimated_minutes' => $this->intn($lessonData, 'estimated_minutes'),
            'position' => $position,
        ]);

        // 1. Situation
        $this->upsertBlock($lesson, 1, BlockType::Situation, $this->situationSettings(null));

        // 2. Vocabulary
        $vocabulary = $this->arr($data, 'vocabulary');
        $vocabularyBlock = $this->upsertBlock($lesson, 2, BlockType::Vocabulary, $this->arr($vocabulary, 'settings'));
        $this->attachLexicon($vocabularyBlock, LexiconKind::Word, $this->arr($vocabulary, 'items'));

        // 3. Useful Expressions
        $expressions = $this->arr($data, 'expressions');
        $expressionsBlock = $this->upsertBlock($lesson, 3, BlockType::Expressions, $this->arr($expressions, 'settings'));
        $this->attachLexicon($expressionsBlock, LexiconKind::Expression, $this->arr($expressions, 'items'));

        // 4. Listen & Repeat
        $listen = $this->arr($data, 'listen_repeat');
        $this->collectPlayable($this->arr($listen, 'items'), true);
        $this->upsertBlock($lesson, 4, BlockType::ListenRepeat, $listen);

        // 5. Dialogue
        $dialogue = $this->arr($data, 'dialogue');
        $this->collectPlayable($this->arr($dialogue, 'lines'), true);
        $this->upsertBlock($lesson, 5, BlockType::Dialogue, $dialogue);

        // 6. Video
        $video = $this->arr($data, 'video');
        $this->collectPlayable($this->arr($video, 'example'), true);
        $this->upsertBlock($lesson, 6, BlockType::Video, $video);

        // 7. Practice
        $practiceBlock = $this->upsertBlock($lesson, 7, BlockType::Practice, $this->arr($data, 'practice'));
        $this->seedPracticeActivities($practiceBlock);

        // 8. AI Role-play
        $roleplay = $this->arr($data, 'ai_roleplay');
        $roleplay['scenario_ids'] = array_values(array_map(
            static fn (AiScenario $scenario): int => $scenario->id,
            $scenarios,
        ));
        $this->upsertBlock($lesson, 8, BlockType::AiRoleplay, $roleplay);

        // 9. Lesson Completed
        $this->upsertBlock($lesson, 9, BlockType::Complete, $this->arr($data, 'complete'));

        return $lesson;
    }

    /**
     * Lessons 2–8: situation, vocabulary (3 words), practice (1 listen_choose
     * activity) and complete (G.2).
     *
     * @param  array<array-key, mixed>  $lessonData
     */
    private function seedLightLesson(Unit $unit, array $lessonData, int $position): Lesson
    {
        $lesson = $this->upsertLesson($unit, $this->str($lessonData, 'slug'), [
            'title' => $this->str($lessonData, 'title'),
            'introduction' => $this->strn($lessonData, 'introduction'),
            'objectives' => ['Understand the situation', 'Learn useful language', 'Practice and be ready for real situations'],
            'cover_media_id' => $this->mediaId('dialogue-checkin'),
            'estimated_minutes' => 15,
            'position' => $position,
        ]);

        $this->upsertBlock($lesson, 1, BlockType::Situation, $this->situationSettings($this->strn($lessonData, 'situation_quote')));

        $vocabularySettings = $this->arr($this->arr($this->data('lesson-handling-complaints'), 'vocabulary'), 'settings');
        $vocabularyBlock = $this->upsertBlock($lesson, 2, BlockType::Vocabulary, $vocabularySettings);
        $this->attachLexicon($vocabularyBlock, LexiconKind::Word, $this->arr($lessonData, 'words'));

        $practiceBlock = $this->upsertBlock($lesson, 3, BlockType::Practice, $this->arr($this->data('lesson-handling-complaints'), 'practice'));

        $practice = $this->arr($lessonData, 'practice');
        $letters = ['a', 'b', 'c'];
        $options = [];

        foreach (array_values($this->arr($practice, 'options')) as $index => $option) {
            $option = $this->row($option);
            $options[] = [
                'id' => $letters[$index],
                'label' => $this->str($option, 'label'),
                'image' => $this->str($option, 'image'),
            ];
        }

        $payload = [
            'tip' => 'Listen carefully and look at the pictures. You can play the audio as many times as you need.',
            'items' => [[
                'id' => 'i1',
                'audio_text' => $this->str($practice, 'audio_text'),
                'options' => $options,
                'correct' => 'a',
            ]],
        ];

        $this->collectPlayable($payload, true);

        $activity = $this->upsertActivity(
            $this->str($practice, 'title'),
            ActivityType::ListenChoose,
            'Listening',
            'Listen to the word or sentence and choose the correct picture.',
            $payload,
            null,
            true,
        );

        $this->placeActivity($activity, $practiceBlock, 1);

        $this->upsertBlock($lesson, 4, BlockType::Complete, $this->completeSettings());

        return $lesson;
    }

    /**
     * Lexicon rows keyed on (kind, english_text), attached to the block in
     * order, the first one featured.
     *
     * @param  array<array-key, mixed>  $items
     */
    private function attachLexicon(Block $block, LexiconKind $kind, array $items): void
    {
        $sync = [];
        $featuredId = null;
        $position = 1;

        foreach ($items as $itemData) {
            $itemData = $this->row($itemData);

            $item = LexiconItem::query()->firstOrNew([
                'kind' => $kind->value,
                'english_text' => $this->str($itemData, 'english_text'),
                'hotel_id' => null,
            ]);

            $item->fill([
                'ipa' => $this->strn($itemData, 'ipa'),
                'part_of_speech' => $this->strn($itemData, 'part_of_speech'),
                'arabic_meaning' => $this->strn($itemData, 'arabic_meaning'),
                'simple_explanation' => $this->strn($itemData, 'simple_explanation'),
                'hotel_example' => $this->strn($itemData, 'hotel_example'),
                'hotel_example_arabic' => $this->strn($itemData, 'hotel_example_arabic'),
                'image_media_id' => $this->mediaRef($this->strn($itemData, 'image')),
                'source' => 'manual',
                'show_meaning_enabled' => true,
                'department_id' => null,
            ])->save();

            foreach ($item->playableTexts() as $text) {
                $this->playable($text);
            }

            $sync[$item->id] = ['position' => $position++];
            $featuredId ??= $item->id;
        }

        $block->lexiconItems()->sync($sync);

        $settings = $block->settings ?? [];
        $settings['featured_lexicon_item_id'] = $featuredId;
        $block->forceFill(['settings' => $settings])->save();
    }

    // ------------------------------------------------------------- activities

    /**
     * The seven practice activities in hub order (G.3 step 7).
     */
    private function seedPracticeActivities(Block $practiceBlock): void
    {
        $position = 1;

        foreach ($this->data('practice-activities') as $activityData) {
            $activityData = $this->row($activityData);
            $payload = $this->arr($activityData, 'payload');

            $this->collectPlayable($payload, true);

            $activity = $this->upsertActivity(
                $this->str($activityData, 'title'),
                ActivityType::from($this->str($activityData, 'type')),
                $this->strn($activityData, 'skill_label'),
                $this->str($activityData, 'prompt'),
                $payload,
                null,
                true,
            );

            $this->placeActivity($activity, $practiceBlock, $position++);
        }
    }

    /**
     * Keyed on title. Created through the model so the saved hook writes
     * activity_versions v1; a changed payload on a re-run is a new version,
     * never an edit of one that may already have attempts (DATA-11).
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function upsertActivity(string $title, ActivityType $type, ?string $skillLabel, string $prompt, array $payload, ?int $timeLimit, bool $showMeaning, int $attemptsAllowed = 0): Activity
    {
        $activity = Activity::query()
            ->where('title', $title)
            ->whereNull('hotel_id')
            ->first() ?? new Activity;

        $activity->fill([
            'type' => $type,
            'skill_label' => $skillLabel,
            'title' => $title,
            'prompt' => $prompt,
            'prompt_arabic' => null,
            'payload' => $this->resolveMedia($payload),
            'scoring' => null,
            'time_limit_seconds' => $timeLimit,
            'attempts_allowed' => $attemptsAllowed,
            'show_meaning_enabled' => $showMeaning,
            'department_id' => null,
            'hotel_id' => null,
            'status' => ContentStatus::Published,
        ])->save();

        // Belt and braces for a row that predates the model hook.
        $activity->writeCurrentVersion();

        return $activity;
    }

    private function placeActivity(Activity $activity, Model $placeable, int $position): ActivityPlacement
    {
        $placement = ActivityPlacement::query()->firstOrNew([
            'activity_id' => $activity->id,
            'placeable_type' => $placeable->getMorphClass(),
            'placeable_id' => $placeable->getKey(),
        ]);

        $placement->fill(['position' => $position])->save();

        return $placement;
    }

    // ------------------------------------------------------------------ tests

    /**
     * G.4: the paired Reception Pre-test and Post-test.
     *
     * @return array{0: Test, 1: Test}
     */
    private function seedTests(Department $reception): array
    {
        $data = $this->data('tests');

        $pre = $this->upsertTest(TestType::Pre, $reception, $this->arr($data, 'pre'));
        $post = $this->upsertTest(TestType::Post, $reception, $this->arr($data, 'post'));

        $pre->forceFill(['paired_test_id' => $post->id])->save();
        $post->forceFill(['paired_test_id' => $pre->id])->save();

        return [$pre, $post];
    }

    /**
     * @param  array<array-key, mixed>  $testData
     */
    private function upsertTest(TestType $type, Department $department, array $testData): Test
    {
        $test = Test::query()
            ->ofType($type)
            ->where('department_id', $department->id)
            ->whereNull('hotel_id')
            ->first() ?? new Test;

        $test->fill([
            'type' => $type,
            'department_id' => $department->id,
            'hotel_id' => null,
            'title' => $this->str($testData, 'title'),
            'intro' => $this->arr($testData, 'intro'),
            'settings' => $this->arr($testData, 'settings'),
            'status' => ContentStatus::Published,
        ])->save();

        $title = $this->str($testData, 'title');
        $position = 1;

        foreach ($this->arr($testData, 'questions') as $questionData) {
            $questionData = $this->row($questionData);
            $payload = $this->arr($questionData, 'payload');

            // Test options are read, never played: only audio_text counts.
            $this->collectPlayable($payload, false);

            $activity = $this->upsertActivity(
                sprintf('%s – Question %02d', $title, $position),
                ActivityType::from($this->str($questionData, 'type')),
                $this->strn($questionData, 'skill_label'),
                $this->str($questionData, 'prompt'),
                $payload,
                null,
                false, // CTRL-04, TEST-03
                1,
            );

            $this->placeActivity($activity, $test, $position++);
        }

        return $test;
    }

    // -------------------------------------------------------------- scenarios

    /**
     * G.5: the six scenarios, keyed by slug.
     *
     * @return array<string, AiScenario>
     */
    private function seedScenarios(Department $reception): array
    {
        $scenarios = [];

        foreach ($this->arr($this->data('scenarios'), 'scenarios') as $scenarioData) {
            $scenarioData = $this->row($scenarioData);
            $slug = $this->str($scenarioData, 'slug');

            $scenario = AiScenario::query()->firstOrNew(['slug' => $slug]);

            $usefulPhrases = $this->arr($scenarioData, 'useful_phrases');

            foreach ($usefulPhrases as $phrase) {
                $this->playable(is_string($phrase) ? $phrase : null);
            }

            $scenario->fill([
                'department_id' => $reception->id,
                'hotel_id' => null,
                'title' => $this->str($scenarioData, 'title'),
                'description' => $this->str($scenarioData, 'description'),
                'difficulty' => ScenarioDifficulty::from($this->str($scenarioData, 'difficulty')),
                'situation' => $this->str($scenarioData, 'situation'),
                'ai_role' => $this->str($scenarioData, 'ai_role'),
                'employee_role' => $this->str($scenarioData, 'employee_role'),
                'objective' => $this->str($scenarioData, 'objective'),
                'goals' => $this->arr($scenarioData, 'goals'),
                'useful_phrases' => $usefulPhrases,
                'feedback_criteria' => AiScenario::defaultFeedbackCriteria(),
                'attempts_allowed' => 3,
                'input_mode' => 'both',
                'min_turns' => 4,
                'max_turns' => 12,
                'thumbnail_media_id' => $this->mediaRef($this->strn($scenarioData, 'thumbnail')),
                'icon' => $this->str($scenarioData, 'icon'),
                'quote' => $this->strn($scenarioData, 'quote'),
                'tip' => $this->strn($scenarioData, 'tip'),
                'status' => ContentStatus::Published,
            ])->save();

            $scenarios[$slug] = $scenario;
        }

        return $scenarios;
    }

    // -------------------------------------------------------------- reminders

    /**
     * @return array<string, ReminderTemplate>
     */
    private function seedReminderTemplates(): array
    {
        $templates = [];

        foreach ($this->arr($this->data('reminders'), 'templates') as $templateData) {
            $templateData = $this->row($templateData);
            $name = $this->str($templateData, 'name');

            $template = ReminderTemplate::query()->firstOrNew(['name' => $name]);
            $template->fill([
                'subject' => $this->str($templateData, 'subject'),
                'body' => $this->str($templateData, 'body'),
                'audience_label' => $this->strn($templateData, 'audience_label'),
                'trigger_label' => $this->strn($templateData, 'trigger_label'),
                'is_active' => true,
            ])->save();

            $templates[$name] = $template;
        }

        return $templates;
    }

    /**
     * @param  array<string, ReminderTemplate>  $templates
     * @param  array<string, Department>  $departments
     * @return array<string, AutomationRule>
     */
    private function seedAutomationRules(array $templates, array $departments): array
    {
        $rules = [];

        foreach ($this->arr($this->data('reminders'), 'rules') as $ruleData) {
            $ruleData = $this->row($ruleData);
            $name = $this->str($ruleData, 'name');
            $template = $templates[$this->str($ruleData, 'template')]
                ?? throw new RuntimeException('Unknown template in reminders.php.');

            $audience = null;
            $audienceDepartments = $ruleData['audience_departments'] ?? null;

            if (is_array($audienceDepartments)) {
                $departmentIds = [];

                foreach ($audienceDepartments as $departmentName) {
                    $department = is_string($departmentName) ? ($departments[$departmentName] ?? null) : null;

                    if ($department === null) {
                        throw new RuntimeException('Unknown department in reminders.php.');
                    }

                    $departmentIds[] = $department->id;
                }

                $audience = ['department_ids' => $departmentIds];
            }

            $rule = AutomationRule::query()->firstOrNew(['name' => $name]);
            $rule->fill([
                'trigger' => AutomationTrigger::from($this->str($ruleData, 'trigger')),
                'days' => $this->intn($ruleData, 'days'),
                'template_id' => $template->id,
                'audience' => $audience,
                'audience_label' => $this->strn($ruleData, 'audience_label'),
                'is_active' => (bool) ($ruleData['is_active'] ?? false),
            ])->save();

            $rules[$name] = $rule;
        }

        return $rules;
    }

    /**
     * About ten log rows in the states the Messages screen shows: sent,
     * blocked (no consent) and scheduled.
     *
     * @param  array<string, ReminderTemplate>  $templates
     * @param  array<string, AutomationRule>  $rules
     */
    private function seedReminderLog(array $templates, array $rules): void
    {
        $renderer = app(TemplateRenderer::class);
        // Not User::role(): that name is the model's `role` attribute accessor.
        $sender = User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::SuperAdmin->value))
            ->orderBy('id')
            ->first();

        foreach ($this->arr($this->data('reminders'), 'log') as $rowData) {
            $rowData = $this->row($rowData);
            $user = User::query()->where('username', $this->str($rowData, 'username'))->first();
            $template = $templates[$this->str($rowData, 'template')] ?? null;

            if ($user === null || $template === null) {
                continue;
            }

            $ruleName = $this->strn($rowData, 'rule');
            $rule = $ruleName === null ? null : ($rules[$ruleName] ?? null);
            $status = ReminderStatus::from($this->str($rowData, 'status'));
            $time = $this->strn($rowData, 'time') ?? '09:00';

            $daysAgo = $this->intn($rowData, 'days_ago');
            $daysAhead = $this->intn($rowData, 'days_ahead');
            $when = $daysAhead !== null
                ? now()->addDays($daysAhead)
                : now()->subDays($daysAgo ?? 0);
            $when = Carbon::parse($when->toDateString().' '.$time);

            $reminder = Reminder::query()->firstOrNew([
                'user_id' => $user->id,
                'template_id' => $template->id,
                'channel' => ReminderChannel::Email->value,
                'status' => $status->value,
            ]);

            $reminder->fill([
                'automation_rule_id' => $rule?->id,
                'subject' => $renderer->render($template->subject, $user),
                'body' => $renderer->render($template->body, $user),
                'sent_by' => $rule === null ? $sender?->id : null,
                'scheduled_for' => $status === ReminderStatus::Scheduled ? $when : null,
                'sent_at' => $status === ReminderStatus::Sent ? $when : null,
                'blocked_reason' => $status === ReminderStatus::Blocked
                    ? ($user->email === null ? Reminder::BLOCKED_NO_EMAIL : Reminder::BLOCKED_NO_CONSENT)
                    : null,
            ])->save();
        }
    }

    // --------------------------------------------------------------- accounts

    private function hotel(): Hotel
    {
        $slug = Str::slug(self::HOTEL_NAME);
        $hotel = Hotel::withoutGlobalScopes()->where('slug', $slug)->first();

        if ($hotel !== null) {
            return $hotel;
        }

        return Hotel::factory()->active(45)->create([
            'name' => self::HOTEL_NAME,
            'slug' => $slug,
            'city' => 'Algiers',
            'manager_name' => 'Meriem Haddad',
            'manager_email' => 'meriem.haddad@guesvia.dz',
            'contract_starts_on' => now()->subDays(60)->toDateString(),
        ]);
    }

    /**
     * G.7: Samira Benali, pre-test not submitted, so /learn shows photo_20.
     */
    private function seedSamira(Hotel $hotel, Department $reception): User
    {
        return $this->claimEmployee($hotel, $reception, 'samira', [
            'name' => 'Samira Benali',
            'email' => 'samira.benali@guesvia.test',
            'email_consent_at' => now()->subDays(20),
            'first_login_completed_at' => now()->subDays(20),
            'research_notice_acknowledged_at' => now()->subDays(20),
            'last_login_at' => now()->subHours(3),
            'last_activity_at' => now()->subHours(3),
            'training_started_at' => null,
            'training_completed_at' => null,
        ]);
    }

    /**
     * G.7: amine, pre-test submitted, lesson 1 completed, one evaluated
     * role-play attempt on Check-in (G.6).
     */
    private function seedAmine(Hotel $hotel, Department $reception, Lesson $lessonOne, Test $pre, AiScenario $checkIn): User
    {
        $amine = $this->claimEmployee($hotel, $reception, 'amine', [
            'name' => 'Amine Khelifi',
            'email' => 'amine.khelifi@guesvia.test',
            'email_consent_at' => now()->subDays(18),
            'first_login_completed_at' => now()->subDays(18),
            'research_notice_acknowledged_at' => now()->subDays(18),
            'last_login_at' => now()->subDay(),
            'last_activity_at' => now()->subDay(),
            'training_started_at' => now()->subDays(16),
            'training_completed_at' => null,
        ]);

        $this->seedTestSitting($amine, $pre, 74, now()->subDays(17)->setTime(10, 5));
        $this->seedLessonCompletion($amine, $lessonOne, now()->subDays(16)->setTime(14, 20));
        $this->seedRoleplayAttempt($amine, $checkIn, $lessonOne, now()->subDays(16)->setTime(15, 2));

        return $amine;
    }

    /**
     * The Messages sample recipients, as renamed portfolio employees.
     *
     * @param  array<string, Department>  $departments
     */
    private function seedNamedEmployees(array $departments): void
    {
        foreach ($this->arr($this->data('reminders'), 'named_employees') as $rowData) {
            $rowData = $this->row($rowData);

            $hotel = Hotel::withoutGlobalScopes()
                ->where('slug', Str::slug($this->str($rowData, 'hotel')))
                ->first();
            $department = $departments[$this->str($rowData, 'department')] ?? null;

            if ($hotel === null || $department === null) {
                continue;
            }

            $daysAgo = $this->intn($rowData, 'last_activity_days_ago');
            $lastActivity = $daysAgo === null ? null : now()->subDays($daysAgo)->setTime(11, 30);
            $consent = (bool) ($rowData['consent'] ?? false);
            $username = $this->str($rowData, 'username');

            $this->claimEmployee($hotel, $department, $username, [
                'name' => $this->str($rowData, 'name'),
                'email' => $username.'@guesvia.test',
                'email_consent_at' => $consent ? now()->subDays(25) : null,
                'first_login_completed_at' => $daysAgo === null ? null : now()->subDays(25),
                'research_notice_acknowledged_at' => $daysAgo === null ? null : now()->subDays(25),
                'last_login_at' => $lastActivity,
                'last_activity_at' => $lastActivity,
                'status' => ($rowData['inactive'] ?? false) === true ? AccountStatus::Inactive : AccountStatus::Active,
            ]);
        }
    }

    /**
     * An employee account with this username in this hotel and department.
     *
     * Finds it by username, else renames the lowest-id portfolio employee of
     * that hotel and department so the seat numbers HotelPortfolioSeeder set
     * stay identical, else creates one. Deterministic on a fresh database.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    private function claimEmployee(Hotel $hotel, Department $department, string $username, array $attributes): User
    {
        $this->reservedUsernames[] = $username;

        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            $user = User::query()
                ->employees()
                ->where('hotel_id', $hotel->id)
                ->where('department_id', $department->id)
                ->where(function (Builder $query): void {
                    $query->whereNull('username')->orWhereNotIn('username', $this->reservedUsernames);
                })
                ->orderBy('id')
                ->first();
        }

        $isNew = $user === null;

        if ($user === null) {
            $user = new User([
                'password' => Hash::make('password'),
                'hotel_id' => $hotel->id,
                'department_id' => $department->id,
                'status' => AccountStatus::Active,
            ]);
        }

        $user->fill($attributes + ['username' => $username, 'status' => AccountStatus::Active]);

        if ($user->participant_code === null) {
            $user->participant_code = User::generateParticipantCode();
        }

        // The demo password is the same as the portfolio's, but a renamed row
        // keeps whatever hash it had; write it so `password` always works.
        $user->password = 'password';
        $user->save();

        if ($isNew) {
            $user->setRole(Role::Employee);
        }

        return $user;
    }

    // --------------------------------------------------------------- progress

    /**
     * Progress spread over the portfolio, decided by user id so it is the
     * same on every run: about 55 % in progress (pre-test submitted, 1–5
     * lessons done), about 20 % finished (all lessons, post-test sat, a
     * role-play attempt), the rest untouched. Tests exist for Reception only,
     * so other departments get lesson completions alone.
     *
     * @param  array<string, AiScenario>  $scenarios
     */
    private function seedPortfolioProgress(User $samira, User $amine, Test $pre, Test $post, array $scenarios): void
    {
        $lessonsByDepartment = [];
        $scenarioList = array_values($scenarios);

        $employees = User::query()
            ->employees()
            ->whereNotNull('department_id')
            ->whereKeyNot([$samira->id, $amine->id])
            ->orderBy('id')
            ->get();

        foreach ($employees as $user) {
            $departmentId = (int) $user->department_id;

            $lessonsByDepartment[$departmentId] ??= Lesson::query()
                ->published()
                ->whereNull('hotel_id')
                ->whereHas('course', fn (Builder $course) => $course->where('department_id', $departmentId))
                ->orderBy('course_id')
                ->orderBy('position')
                ->get();

            /** @var Collection<int, Lesson> $lessons */
            $lessons = $lessonsByDepartment[$departmentId];

            if ($lessons->isEmpty()) {
                continue;
            }

            $bucket = $user->id % 100;
            $isReception = $departmentId === $pre->department_id;
            $started = now()->subDays(12 + ($user->id % 10))->setTime(9, 0);

            if ($bucket < 55) {
                if ($isReception) {
                    $this->seedTestSitting($user, $pre, 45 + ($user->id % 40), $started);
                }

                $count = 1 + ($user->id % 5);

                foreach ($lessons->take($count) as $index => $lesson) {
                    $this->seedLessonCompletion($user, $lesson, $started->copy()->addDays($index + 1)->setTime(14, 0));
                }

                $user->forceFill(['training_started_at' => $user->training_started_at ?? $started])->save();

                continue;
            }

            if ($bucket < 75) {
                if ($isReception) {
                    $this->seedTestSitting($user, $pre, 40 + ($user->id % 35), $started);
                }

                $finished = $started->copy();

                foreach ($lessons as $index => $lesson) {
                    $finished = $started->copy()->addDays($index + 1)->setTime(16, 0);
                    $this->seedLessonCompletion($user, $lesson, $finished);
                }

                if ($isReception) {
                    $this->seedTestSitting($user, $post, 65 + ($user->id % 30), $finished->copy()->addDay());

                    if ($user->id % 3 === 0 && $scenarioList !== []) {
                        $scenario = $scenarioList[$user->id % count($scenarioList)];
                        $this->seedRoleplayAttempt($user, $scenario, $lessons->first(), $finished->copy()->addHours(2));
                    }
                }

                $user->forceFill([
                    'training_started_at' => $user->training_started_at ?? $started,
                    'training_completed_at' => $user->training_completed_at ?? $finished->copy()->addDay(),
                ])->save();
            }
        }
    }

    /**
     * Every visible block of the lesson completed, then the lesson (PROG-03).
     */
    private function seedLessonCompletion(User $user, Lesson $lesson, CarbonInterface $when): void
    {
        $blocks = $lesson->visibleBlocks()->orderBy('position')->get();

        foreach ($blocks as $index => $block) {
            BlockCompletion::query()->firstOrCreate(
                ['user_id' => $user->id, 'block_id' => $block->id],
                ['lesson_id' => $lesson->id, 'completed_at' => $when->copy()->subMinutes(($blocks->count() - $index) * 3)],
            );
        }

        LessonCompletion::query()->firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['completed_at' => $when],
        );
    }

    /**
     * One submitted sitting with an answer row per question (TEST-06,
     * DATA-01, DATA-08): auto-scored questions score through
     * ActivityScorer against the frozen version, speaking questions are
     * stored ungraded with no recording file (none exists to attach).
     */
    private function seedTestSitting(User $user, Test $test, int $correctPercent, CarbonInterface $startedAt): TestAttempt
    {
        $attempt = TestAttempt::query()->firstOrNew([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'attempt_no' => 1,
        ]);

        if ($attempt->exists && $attempt->status === TestAttemptStatus::Submitted) {
            return $attempt;
        }

        $submittedAt = $startedAt->copy()->addMinutes(14)->addSeconds(20);

        $attempt->fill([
            'status' => TestAttemptStatus::Submitted,
            'started_at' => $startedAt,
            'deadline_at' => $startedAt->copy()->addSeconds($test->timeLimitSeconds() ?? 1200),
            'submitted_at' => $submittedAt,
            'results_released_at' => $submittedAt,
        ])->save();

        $scorer = app(ActivityScorer::class);
        $score = 0;
        $maxScore = 0;
        $breakdown = [];
        $clock = $startedAt->copy();

        $placements = $test->questions()->with(['activity.currentVersion'])->get();

        foreach ($placements as $placement) {
            $activity = $placement->activity;

            if ($activity === null) {
                continue;
            }

            $version = $activity->currentVersion ?? $activity->writeCurrentVersion();
            $items = $version->items();
            $item = $items[0] ?? [];
            $itemId = (string) ($item['id'] ?? 'i1');

            // Stable per (user, question): a different mix for every learner.
            $wantsCorrect = (crc32($user->id.':'.$placement->id) % 100) < $correctPercent;
            $rawAnswer = [$itemId => $this->answerFor($activity->type, $item, $wantsCorrect)];

            $result = $scorer->scoreItems($activity->type, $items, $rawAnswer);
            $timeTaken = 20000 + (crc32($placement->id.':'.$user->id) % 40000);
            $clock = $clock->copy()->addMilliseconds($timeTaken);

            Attempt::query()->updateOrCreate(
                ['test_attempt_id' => $attempt->id, 'activity_id' => $activity->id],
                [
                    'user_id' => $user->id,
                    'activity_version_id' => $version->id,
                    'placement_id' => $placement->id,
                    'lesson_id' => null,
                    'block_id' => null,
                    'attempt_no' => 1,
                    'raw_answer' => $rawAnswer,
                    'answer_history' => [['answer' => $rawAnswer, 'at' => $clock->toIso8601String()]],
                    'transcript' => $activity->type === ActivityType::Speaking ? '[voice message]' : null,
                    'started_at' => $clock->copy()->subMilliseconds($timeTaken),
                    'submitted_at' => $clock,
                    'time_taken_ms' => $timeTaken,
                ] + $result->toAttemptColumns(),
            );

            $skill = $activity->skill_label ?? $activity->type->label();
            $breakdown[$skill] ??= ['correct' => 0, 'total' => 0, 'ungraded' => 0];

            if ($result->isAutoScored()) {
                $score += (int) $result->score;
                $maxScore += $result->maxScore;
                $breakdown[$skill]['total']++;
                $breakdown[$skill]['correct'] += (int) $result->score;
            } else {
                $breakdown[$skill]['ungraded']++;
            }
        }

        $attempt->forceFill([
            'score' => $score,
            'max_score' => $maxScore,
            'breakdown' => $breakdown,
        ])->save();

        return $attempt;
    }

    /**
     * A right or wrong answer in the shape B.9 fixes for the type.
     *
     * @param  array<array-key, mixed>  $item
     */
    private function answerFor(ActivityType $type, array $item, bool $correct): mixed
    {
        switch ($type) {
            case ActivityType::PictureOrder:
                $order = $this->arr($item, 'order');

                return $correct ? array_values($order) : array_reverse(array_values($order));

            case ActivityType::Speaking:
                return ['recording_media_id' => null, 'duration_ms' => 6200, 'transcript' => '[voice message]'];

            case ActivityType::Writing:
                return ['text' => 'Dear Guest, thank you for your message. We confirm your request. Kind regards, The Reception Team'];

            default:
                $right = (string) ($item['correct'] ?? 'A');

                if ($correct) {
                    return $right;
                }

                foreach ($this->arr($item, 'options') as $option) {
                    $id = is_array($option) ? (string) ($option['id'] ?? '') : '';

                    if ($id !== '' && $id !== $right) {
                        return $id;
                    }
                }

                return $right;
        }
    }

    /**
     * One completed, evaluated role-play attempt with the mockup transcript,
     * scores and feedback (G.6; RP-11, DATA-05).
     */
    private function seedRoleplayAttempt(User $user, AiScenario $scenario, ?Lesson $lesson, CarbonInterface $startedAt): RoleplayAttempt
    {
        $demo = $this->arr($this->data('scenarios'), 'demo_attempt');

        $attempt = RoleplayAttempt::query()->firstOrNew([
            'user_id' => $user->id,
            'ai_scenario_id' => $scenario->id,
            'attempt_no' => 1,
        ]);

        if ($attempt->exists && $attempt->status === RoleplayStatus::Completed) {
            return $attempt;
        }

        $transcript = [];
        $clock = $startedAt->copy();

        foreach ($this->arr($demo, 'transcript') as $turn) {
            $turn = $this->row($turn);
            $clock = $clock->copy()->addSeconds(20);
            $transcript[] = [
                'role' => $this->str($turn, 'role'),
                'text' => $this->str($turn, 'text'),
                'at' => $clock->toIso8601String(),
            ];
        }

        $durationMs = $this->intn($demo, 'duration_ms') ?? 150000;

        $roleplayBlock = $lesson?->visibleBlocks()->where('type', BlockType::AiRoleplay->value)->first();

        $attempt->fill([
            'lesson_id' => $lesson?->id,
            'block_id' => $roleplayBlock?->id,
            'status' => RoleplayStatus::Completed,
            'transcript' => $transcript,
            'pending_reply' => false,
            'criteria_scores' => $this->arr($demo, 'criteria_scores'),
            'overall_score' => $this->intn($demo, 'overall_score') ?? 70,
            'feedback' => $this->arr($demo, 'feedback'),
            'ai_status' => null,
            'failed_reason' => null,
            'duration_ms' => $durationMs,
            'is_preview' => false,
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMilliseconds($durationMs),
        ])->save();

        return $attempt;
    }

    // ----------------------------------------------------------------- report

    private function report(): void
    {
        // isset(), not ?->: Seeder::$command is uninitialised outside the
        // console (see SuperAdminSeeder).
        // @phpstan-ignore isset.property
        if (! isset($this->command)) {
            return;
        }

        $this->command->info(sprintf(
            'LearningContentSeeder: %d courses, %d lessons, %d blocks, %d lexicon items, %d activities, %d test questions, %d scenarios, %d audio clips.',
            Course::query()->count(),
            Lesson::query()->count(),
            Block::query()->count(),
            LexiconItem::query()->count(),
            Activity::query()->count(),
            ActivityPlacement::query()->where('placeable_type', (new Test)->getMorphClass())->count(),
            AiScenario::query()->count(),
            AudioClip::query()->count(),
        ));

        if ($this->missingMedia !== []) {
            $this->command->warn(
                'LearningContentSeeder: media names missing from seed-media.json (rows created at the expected path): '
                .implode(', ', array_unique($this->missingMedia)),
            );
        }
    }

    // ------------------------------------------------------------ data access

    /**
     * @return array<array-key, mixed>
     */
    private function data(string $name): array
    {
        static $cache = [];

        if (! isset($cache[$name])) {
            $loaded = require database_path("seeders/data/{$name}.php");

            if (! is_array($loaded)) {
                throw new RuntimeException("seeders/data/{$name}.php must return an array.");
            }

            $cache[$name] = $loaded;
        }

        return $cache[$name];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function row(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Expected an array row in the seed data.');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value)) {
            throw new RuntimeException("Seed data key [{$key}] must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private function strn(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private function intn(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return array<array-key, mixed>
     */
    private function arr(array $row, string $key): array
    {
        $value = $row[$key] ?? null;

        if (! is_array($value)) {
            throw new RuntimeException("Seed data key [{$key}] must be an array.");
        }

        return $value;
    }
}
