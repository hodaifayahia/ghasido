<?php

namespace App\Services\Content;

use App\Contracts\CourseOutline;
use App\Contracts\ImageProvider;
use App\Contracts\LessonDraft;
use App\Enums\ActivityType;
use App\Enums\AiFeature;
use App\Enums\BlockType;
use App\Enums\ContentGenerationType;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\LexiconKind;
use App\Jobs\GenerateContentImage;
use App\Jobs\GenerateLessonFromPrompt;
use App\Models\AiUsage;
use App\Models\AudioClip;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\ContentGeneration;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Audio\AudioLibrary;
use App\Services\Audio\PlayableTextCollector;
use App\Services\Owner\ApiCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Generate with AI": a whole lesson, or a whole course of lessons, from one
 * admin prompt (GEN-01, GEN-03, GEN-04, LESSON-01, LESSON-02, PERF-04,
 * AIL-01..03; spec 0004).
 *
 * The controller calls start(); the queued GenerateLessonFromPrompt job asks
 * the AI and calls writeOutline() / buildLesson(); GenerateContentImage and
 * GenerateAudioClip fill the media. Everything lands as a draft (GEN-03):
 * the lesson is `draft`, and a course this generator creates is `draft`
 * until the admin publishes a lesson in it.
 *
 * The default block order is Situation → Vocabulary → Useful Expressions →
 * Listen & Repeat → Dialogue → Practice → Lesson Complete, written as normal
 * blocks the admin then edits, reorders or trims (LESSON-02, BLD-07). Arabic
 * is written only into the fields Show Meaning reveals (CTRL-01, CTRL-02).
 */
class LessonGenerator
{
    public const int MAX_LESSONS = 8;

    /** @var list<string> */
    public const array LEVELS = ['beginner', 'elementary', 'intermediate'];

    public function __construct(
        private readonly LessonService $lessons,
        private readonly BlockService $blocks,
        private readonly LexiconService $lexicon,
        private readonly ActivityService $activities,
        private readonly AudioLibrary $audio,
        private readonly PlayableTextCollector $collector,
    ) {}

    // ------------------------------------------------------------- starting

    /**
     * Validate the limits, pick or create the target course and unit, write
     * the generation row and queue the first job.
     *
     * @param  array{prompt: string, department_id: int, hotel_id: int|null, level: string, mode: string, lesson_count: int, course_id: int|null, images: bool, audio: bool}  $data
     *
     * @throws AiLimitReached
     */
    public function start(User $actor, array $data): ContentGeneration
    {
        $isCourse = $data['mode'] === ContentGenerationType::Course->value;
        $count = $isCourse ? max(1, min(self::MAX_LESSONS, $data['lesson_count'])) : 1;

        $this->assertWithinLimits($actor, $count, $data['images']);

        $generation = DB::transaction(function () use ($actor, $data, $isCourse, $count): ContentGeneration {
            $existing = $data['course_id'] === null ? null : Course::query()->findOrFail($data['course_id']);
            $createdCourse = $existing === null;

            $course = $existing ?? $this->lessons->createCourse([
                'title' => $this->provisionalTitle($data['prompt'], $isCourse),
                'department_id' => $data['department_id'],
                'hotel_id' => $data['hotel_id'],
                'description' => null,
                'tone' => 'brand',
            ], $actor);

            $unit = $isCourse || $createdCourse
                ? $this->lessons->createUnit($course, $isCourse ? __('AI course', [], 'en') : __('Unit 1', [], 'en'))
                : (Unit::query()->where('course_id', $course->id)->orderByDesc('position')->orderByDesc('id')->first()
                    ?? $this->lessons->createUnit($course, __('Unit 1', [], 'en')));

            $generation = ContentGeneration::query()->create([
                'user_id' => $actor->id,
                'type' => $isCourse ? ContentGenerationType::Course : ContentGenerationType::Lesson,
                'prompt' => trim($data['prompt']),
                'department_id' => $course->department_id,
                'hotel_id' => $course->hotel_id,
                'level' => $data['level'],
                'options' => [
                    'images' => $data['images'],
                    'audio' => $data['audio'],
                    'lesson_count' => $count,
                    'created_course' => $createdCourse,
                ],
                'status' => GenerationStatus::Pending,
                'stage' => $isCourse ? ContentGeneration::STAGE_OUTLINE : ContentGeneration::STAGE_WRITING,
                'course_id' => $course->id,
                'unit_id' => $unit->id,
                'lessons_total' => $count,
            ]);

            AuditLog::record($generation, 'content.generation_requested', [
                'created' => ['type' => $generation->type->value, 'lesson_count' => $count, 'course_id' => $course->id],
            ]);

            return $generation;
        });

        GenerateLessonFromPrompt::dispatch($generation->id, $isCourse ? null : 0);

        return $generation;
    }

    /**
     * One image for an editor slot, optionally attached straight to a lesson
     * cover, a lexicon item or a block setting (GEN-01, GEN-04, MED-07).
     *
     * @param  array{prompt: string, alt: string, size: string, target_type: string|null, target_id: int|null, target_key: string|null}  $data
     *
     * @throws AiLimitReached
     */
    public function startImage(User $actor, array $data, ?int $hotelId): ContentGeneration
    {
        $this->assertImagesWithinLimits($actor, 1);

        $generation = ContentGeneration::query()->create([
            'user_id' => $actor->id,
            'type' => ContentGenerationType::Image,
            'prompt' => trim($data['prompt']),
            'hotel_id' => $hotelId,
            'options' => ['images' => true, 'audio' => false, 'lesson_count' => 0],
            'status' => GenerationStatus::Pending,
            'stage' => ContentGeneration::STAGE_MEDIA,
            'lessons_total' => 0,
            'images_total' => 1,
            'target' => [
                'type' => $data['target_type'] ?? 'none',
                'id' => $data['target_id'] ?? 0,
                'key' => $data['target_key'] ?? 'image',
                'prompt' => trim($data['prompt']),
                'alt' => trim($data['alt']) !== '' ? trim($data['alt']) : Str::limit(trim($data['prompt']), 120),
                'size' => $data['size'],
            ],
        ]);

        GenerateContentImage::dispatch($generation->id, null);

        return $generation;
    }

    /**
     * Redo what is missing after a failure: the outline, the lessons without
     * a row, and the images that never arrived. Nothing already written is
     * written twice (GEN-04, PERF-04).
     */
    public function retry(ContentGeneration $generation): ContentGeneration
    {
        $generation->forceFill([
            'status' => GenerationStatus::Pending,
            'failed_reason' => null,
            'finished_at' => null,
        ])->save();

        if ($generation->type === ContentGenerationType::Image) {
            $generation->forceFill(['images_done' => 0, 'images_failed' => 0])->save();
            GenerateContentImage::dispatch($generation->id, null);

            return $generation;
        }

        if ($generation->type === ContentGenerationType::Course && ($generation->outline['lessons'] ?? []) === []) {
            GenerateLessonFromPrompt::dispatch($generation->id, null);

            return $generation;
        }

        foreach (range(0, max(0, $generation->lessons_total - 1)) as $index) {
            if ($generation->lessonIdFor($index) === null) {
                GenerateLessonFromPrompt::dispatch($generation->id, $index);
            }
        }

        $this->requeueMissingImages($generation);
        $this->refreshStatus($generation);

        return $generation;
    }

    // ------------------------------------------------------------- limits

    /**
     * AIL-01..03 for admin generation: lessons and images per admin per day,
     * checked before anything is queued so no budget is spent past the limit.
     *
     * @throws AiLimitReached
     */
    public function assertWithinLimits(User $actor, int $lessonCount, bool $images): void
    {
        // The platform owner's credit for the accounts this run spends
        // (spec 0007, D7a).
        app(ApiCredit::class)->assertCapabilities(...($images ? ['ai', 'image'] : ['ai']));

        $limit = (int) config('guesvia.ai.limits.per_admin_daily_generated_lessons', 40);
        $used = (int) ContentGeneration::query()
            ->where('user_id', $actor->id)
            ->whereIn('type', [ContentGenerationType::Lesson->value, ContentGenerationType::Course->value])
            ->where('created_at', '>=', Date::now()->startOfDay())
            ->sum('lessons_total');

        if ($used + $lessonCount > $limit) {
            throw AiLimitReached::forGeneration(AiFeature::LessonGenerate, $limit);
        }

        if ($images) {
            $this->assertImagesWithinLimits($actor, 1);
        }
    }

    /**
     * @throws AiLimitReached
     */
    public function assertImagesWithinLimits(User $actor, int $wanted): void
    {
        app(ApiCredit::class)->assertCapabilities('image');

        $limit = (int) config('guesvia.ai.limits.per_admin_daily_images', 200);
        $used = AiUsage::query()
            ->forFeature(AiFeature::ImageGenerate)
            ->where('user_id', $actor->id)
            ->where('occurred_at', '>=', Date::now()->startOfDay())
            ->count();

        if ($used + $wanted > $limit) {
            throw AiLimitReached::forGeneration(AiFeature::ImageGenerate, $limit);
        }
    }

    // ------------------------------------------------------------- writing

    /**
     * Store the outline and, when this generation created the course, give
     * the course and its unit the outline's title.
     */
    public function writeOutline(ContentGeneration $generation, CourseOutline $outline): void
    {
        DB::transaction(function () use ($generation, $outline): void {
            $generation->forceFill([
                'outline' => $outline->toArray(),
                'lessons_total' => count($outline->lessons),
                'stage' => ContentGeneration::STAGE_WRITING,
            ])->save();

            if ($generation->option('created_course') && $generation->course_id !== null) {
                $course = Course::query()->find($generation->course_id);

                if ($course !== null && $course->status === ContentStatus::Draft) {
                    $course->forceFill([
                        'title' => Str::limit($outline->title, 120, ''),
                        'description' => $outline->description !== '' ? Str::limit($outline->description, 500, '') : $course->description,
                    ])->save();
                }
            }

            if ($generation->unit_id !== null && $outline->title !== '') {
                Unit::query()->whereKey($generation->unit_id)->update(['title' => Str::limit($outline->title, 120, '')]);
            }
        });
    }

    /**
     * Turn one draft into a draft lesson with its blocks, lexicon items and
     * versioned practice activities, then queue its images and audio.
     * Idempotent per outline index: an index that already has a lesson is
     * returned as is.
     */
    public function buildLesson(ContentGeneration $generation, int $index, LessonDraft $draft): Lesson
    {
        $existing = $generation->lessonIdFor($index);

        if ($existing !== null && ($lesson = Lesson::query()->find($existing)) !== null) {
            return $lesson;
        }

        $actor = $generation->user;

        if ($actor === null) {
            throw new RuntimeException('The admin who asked for this generation no longer exists.');
        }

        $unit = Unit::query()->findOrFail($generation->unit_id);
        $course = $unit->course()->firstOrFail();

        $lesson = DB::transaction(function () use ($generation, $index, $draft, $actor, $unit, $course): Lesson {
            $title = $draft->title !== '' ? $draft->title : ($generation->outlineLesson($index)['title'] ?: __('AI lesson', [], 'en'));

            $lesson = $this->lessons->createLesson($unit, Str::limit($title, 120, ''), false);
            $lesson->forceFill([
                'introduction' => $draft->situationText !== '' ? $draft->situationText : $draft->subtitle,
                'objectives' => $draft->objectives,
                'estimated_minutes' => 15,
                'ai_status' => GenerationStatus::Done,
            ])->save();

            $this->situationBlock($lesson, $draft);
            $this->lexiconBlock($lesson, BlockType::Vocabulary, LexiconKind::Word, $draft->vocabulary, $actor, $course);
            $this->lexiconBlock($lesson, BlockType::Expressions, LexiconKind::Expression, $draft->expressions, $actor, $course);
            $this->listenRepeatBlock($lesson, $draft);
            $this->dialogueBlock($lesson, $draft);
            $this->practiceBlock($lesson, $draft, $actor, $course);
            $this->completeBlock($lesson, $draft);

            $generation->recordLesson($index, $lesson->id);

            return $lesson;
        });

        $this->queueMedia($generation, $lesson, $draft);

        return $lesson;
    }

    /**
     * Queue the lesson's pictures and stored audio (TTS-01, TTS-02). Images
     * first count towards `images_total` so the status never reads "done"
     * while a picture is still on its way.
     */
    public function queueMedia(ContentGeneration $generation, Lesson $lesson, ?LessonDraft $draft = null): void
    {
        if ($generation->option('images')) {
            $targets = $this->imageTargets($lesson, $draft);

            if ($targets !== []) {
                ContentGeneration::query()->whereKey($generation->id)->increment('images_total', count($targets));

                foreach ($targets as $target) {
                    GenerateContentImage::dispatch($generation->id, $target);
                }
            }
        }

        if ($generation->option('audio')) {
            $texts = $this->collector->forLesson($lesson->fresh() ?? $lesson);

            foreach ($texts as $text) {
                $this->audio->ensureBoth($text);
            }

            $generation->recordAudioTexts($texts);
        }
    }

    /**
     * One image finished (or failed) for this generation.
     */
    public function imageFinished(int $generationId, bool $succeeded, ?string $reason = null): void
    {
        $generation = ContentGeneration::query()->find($generationId);

        if ($generation === null) {
            return;
        }

        if ($generation->type === ContentGenerationType::Image) {
            $generation->forceFill([
                'images_done' => $succeeded ? 1 : 0,
                'images_failed' => $succeeded ? 0 : 1,
                'status' => $succeeded ? GenerationStatus::Done : GenerationStatus::Failed,
                'stage' => ContentGeneration::STAGE_DONE,
                'failed_reason' => $succeeded ? null : mb_substr($reason ?? 'Image generation failed.', 0, 1000),
                'finished_at' => Carbon::now(),
            ])->save();

            return;
        }

        ContentGeneration::query()->whereKey($generationId)->increment($succeeded ? 'images_done' : 'images_failed');

        $this->refreshStatus($generation);
    }

    /**
     * Move the row to its next stage, or to done once every lesson is
     * written and every image has landed or failed.
     */
    public function refreshStatus(ContentGeneration $generation): void
    {
        $generation->refresh();

        if ($generation->status === GenerationStatus::Failed) {
            return;
        }

        $lessonsComplete = $generation->lessons_total > 0 && $generation->lessons_done >= $generation->lessons_total;
        $imagesComplete = $generation->images_done + $generation->images_failed >= $generation->images_total;

        if ($lessonsComplete && $imagesComplete) {
            if ($generation->status !== GenerationStatus::Done) {
                $generation->forceFill([
                    'status' => GenerationStatus::Done,
                    'stage' => ContentGeneration::STAGE_DONE,
                    'finished_at' => Carbon::now(),
                ])->save();
            }

            return;
        }

        if ($lessonsComplete) {
            $generation->forceFill(['status' => GenerationStatus::Running, 'stage' => ContentGeneration::STAGE_MEDIA])->save();
        }
    }

    // ---------------------------------------------------------- presenting

    /**
     * What the progress panel polls (PERF-04).
     *
     * @return array<string, mixed>
     */
    public function present(ContentGeneration $generation): array
    {
        $audio = $this->audioProgress($generation);
        $lessonIds = $generation->lessonIds();
        $firstLesson = $lessonIds === [] ? null : $lessonIds[0];
        $audioPending = $audio['total'] > 0 && $audio['total'] > $audio['done'] + $audio['failed'];
        $status = $generation->status;

        $state = match (true) {
            $status === GenerationStatus::Failed => 'failed',
            $status === GenerationStatus::Pending && $generation->started_at === null => 'queued',
            $generation->stage === ContentGeneration::STAGE_OUTLINE => 'outline',
            $generation->lessons_done < $generation->lessons_total => 'writing',
            $generation->images_done + $generation->images_failed < $generation->images_total => 'images',
            $audioPending => 'audio',
            default => 'done',
        };

        $media = $generation->mediaAsset;

        return [
            'id' => $generation->id,
            'type' => $generation->type->value,
            'status' => $status->value,
            'state' => $state,
            'prompt' => $generation->prompt,
            'failedReason' => $generation->failed_reason,
            'lessons' => ['done' => $generation->lessons_done, 'total' => $generation->lessons_total],
            'images' => ['done' => $generation->images_done, 'failed' => $generation->images_failed, 'total' => $generation->images_total],
            'audio' => $audio,
            'courseId' => $generation->course_id,
            'lessonIds' => $lessonIds,
            'openUrl' => $firstLesson === null ? null : route('lessons.edit', ['lesson' => $firstLesson]),
            'media' => $media === null ? null : [
                'id' => $media->id,
                'url' => $media->url(),
                'thumbUrl' => $media->variantUrl('thumb'),
                'alt' => $media->alt_text ?? '',
                'label' => $media->label ?? '',
            ],
        ];
    }

    /**
     * Clips done / failed / total for the sentences this generation queued,
     * read from the clip rows so nothing is double counted.
     *
     * @return array{done: int, failed: int, total: int}
     */
    public function audioProgress(ContentGeneration $generation): array
    {
        $texts = $generation->audio_texts ?? [];

        if ($texts === []) {
            return ['done' => 0, 'failed' => 0, 'total' => 0];
        }

        $hashes = array_values(array_unique(array_map(
            fn (string $text): string => AudioClip::hashFor(AudioClip::normalise($text)),
            $texts,
        )));

        $clips = AudioClip::query()
            ->forVoice($this->audio->voice())
            ->whereIn('text_hash', $hashes)
            ->get(['status']);

        return [
            'done' => $clips->filter(fn (AudioClip $clip): bool => $clip->status === GenerationStatus::Done)->count(),
            'failed' => $clips->filter(fn (AudioClip $clip): bool => $clip->status === GenerationStatus::Failed)->count(),
            'total' => count($hashes) * 2,
        ];
    }

    // ------------------------------------------------------------- blocks

    private function situationBlock(Lesson $lesson, LessonDraft $draft): void
    {
        $icons = ['chat', 'people', 'check'];
        $objectives = [];

        foreach (array_slice($draft->objectives, 0, 3) as $position => $text) {
            $objectives[] = ['icon' => $icons[$position] ?? 'check', 'text' => $text];
        }

        $this->addBlock($lesson, BlockType::Situation, [
            'quote' => $draft->situationQuote !== '' ? $draft->situationQuote : null,
            'objectives' => $objectives,
        ]);
    }

    /**
     * @param  list<array{english: string, arabic: string, explanation: string, example: string}>  $rows
     */
    private function lexiconBlock(Lesson $lesson, BlockType $type, LexiconKind $kind, array $rows, User $actor, Course $course): void
    {
        $block = $this->addBlock($lesson, $type, []);
        $first = null;

        foreach ($rows as $row) {
            $item = $this->lexicon->create([
                'kind' => $kind,
                'english_text' => Str::limit($row['english'], 250, ''),
                // Arabic only in the Show Meaning columns (CTRL-01, CTRL-02).
                'arabic_meaning' => $row['arabic'] !== '' ? $row['arabic'] : null,
                'simple_explanation' => $row['explanation'] !== '' ? $row['explanation'] : null,
                'hotel_example' => $row['example'] !== '' ? $row['example'] : null,
                'show_meaning_enabled' => true,
                'department_id' => $course->department_id,
                'hotel_id' => $course->hotel_id,
            ], $actor, $block);

            $item->forceFill(['source' => LexiconItem::SOURCE_AI])->save();
            $first ??= $item->id;
        }

        $settings = $block->settings ?? [];
        $settings['featured_lexicon_item_id'] = $first;
        $block->settings = $settings;
        $block->save();
    }

    private function listenRepeatBlock(Lesson $lesson, LessonDraft $draft): void
    {
        $rows = $draft->listenRepeat;

        if ($rows === []) {
            foreach (array_slice($draft->expressions, 0, 4) as $expression) {
                $rows[] = ['text' => $expression['english'], 'arabic' => $expression['arabic']];
            }
        }

        $this->addBlock($lesson, BlockType::ListenRepeat, [
            'items' => array_map(
                static fn (array $row): array => ['text' => $row['text'], 'arabic' => $row['arabic'] !== '' ? $row['arabic'] : null, 'image' => null],
                $rows,
            ),
        ]);
    }

    private function dialogueBlock(Lesson $lesson, LessonDraft $draft): void
    {
        $this->addBlock($lesson, BlockType::Dialogue, [
            'image' => null,
            'situation_caption' => __('Situation: :title', ['title' => $lesson->title], 'en'),
            'lines' => array_map(
                static fn (array $line): array => [
                    'speaker' => str_contains(strtolower($line['speaker']), 'guest') ? 'guest' : 'staff',
                    'text' => $line['text'],
                    'arabic' => $line['arabic'] !== '' ? $line['arabic'] : null,
                ],
                $draft->dialogue,
            ),
        ]);
    }

    /**
     * A practice hub with a multiple-choice activity built from the draft's
     * questions and, when the dialogue is long enough, a "put the dialogue in
     * order" activity. Both are versioned activities (DATA-11).
     */
    private function practiceBlock(Lesson $lesson, LessonDraft $draft, User $actor, Course $course): void
    {
        $block = $this->addBlock($lesson, BlockType::Practice, []);
        $letters = ['A', 'B', 'C', 'D', 'E', 'F'];

        $items = [];
        foreach ($draft->practice as $position => $question) {
            $options = array_slice($question['options'], 0, count($letters));
            $items[] = [
                'id' => 'i'.($position + 1),
                'question' => $question['prompt'],
                'subtitle' => null,
                'image' => null,
                'passage' => null,
                'layout' => 'side',
                'options' => array_map(
                    static fn (int $i, string $text): array => ['id' => $letters[$i], 'text' => $text],
                    array_keys($options),
                    $options,
                ),
                'correct' => $letters[min($question['answer_index'], count($options) - 1)],
            ];
        }

        if ($items !== []) {
            $this->activities->create([
                'type' => ActivityType::MultipleChoice,
                'skill_label' => ActivityType::MultipleChoice->label('en'),
                'title' => ActivityType::MultipleChoice->label('en'),
                'prompt' => __('Read the situation and choose the best answer.', [], 'en'),
                'payload' => ['items' => $items],
                'department_id' => $course->department_id,
                'hotel_id' => $course->hotel_id,
                'status' => ContentStatus::Published,
            ], $actor, $block);
        }

        $lines = array_slice($draft->dialogue, 0, 6);

        if (count($lines) >= 3) {
            $sentences = [];
            foreach ($lines as $position => $line) {
                $sentences[] = ['id' => 's'.($position + 1), 'text' => $line['text']];
            }

            $order = array_column($sentences, 'id');
            $shuffled = $sentences;
            usort($shuffled, static fn (array $a, array $b): int => strcmp(md5($a['text']), md5($b['text'])));

            if (array_column($shuffled, 'id') === $order) {
                $shuffled = array_reverse($shuffled);
            }

            $this->activities->create([
                'type' => ActivityType::DialogueOrder,
                'skill_label' => ActivityType::DialogueOrder->label('en'),
                'title' => ActivityType::DialogueOrder->label('en'),
                'prompt' => ActivityType::DialogueOrder->hubDescription('en'),
                'payload' => ['items' => [[
                    'id' => 'i1',
                    'audio_text' => implode(' ', array_column($lines, 'text')),
                    'sentences' => $shuffled,
                    'order' => $order,
                ]]],
                'department_id' => $course->department_id,
                'hotel_id' => $course->hotel_id,
                'status' => ContentStatus::Published,
            ], $actor, $block);
        }
    }

    private function completeBlock(Lesson $lesson, LessonDraft $draft): void
    {
        $this->addBlock($lesson, BlockType::Complete, [
            'subtitle' => $draft->subtitle !== '' ? $draft->subtitle : __('Great job! You have finished this lesson.', [], 'en'),
        ]);
    }

    /**
     * Add a block at the end with the type's default settings overlaid by
     * the generated ones.
     *
     * @param  array<string, mixed>  $settings
     */
    private function addBlock(Lesson $lesson, BlockType $type, array $settings): Block
    {
        $block = $this->blocks->add($lesson, $type, null, $lesson->blocks()->count() + 1);

        if ($settings !== []) {
            $block->settings = array_merge($block->settings ?? [], $settings);
            $block->save();
        }

        return $block;
    }

    // ------------------------------------------------------------- images

    /**
     * The pictures one lesson gets: its cover (the situation photo) and one
     * per vocabulary word. Alt text is always set (MED-07).
     *
     * @return list<array{type: string, id: int, key: string, prompt: string, alt: string, size: string}>
     */
    private function imageTargets(Lesson $lesson, ?LessonDraft $draft): array
    {
        $targets = [];

        if ($lesson->cover_media_id === null) {
            $scene = $draft !== null && $draft->imagePrompt !== ''
                ? $draft->imagePrompt
                : sprintf('A realistic photo of this hotel situation: %s', $lesson->introduction ?? $lesson->title);

            $targets[] = [
                'type' => 'lesson',
                'id' => $lesson->id,
                'key' => 'cover_media_id',
                'prompt' => $scene.' Photorealistic, modern hotel, adult professional staff, natural light, no text or letters in the image.',
                'alt' => Str::limit(__('Situation: :title', ['title' => $lesson->title], 'en'), 200, ''),
                'size' => ImageProvider::SIZE_LANDSCAPE,
            ];
        }

        $vocabulary = $lesson->blocks()->where('type', BlockType::Vocabulary->value)->first();

        if ($vocabulary !== null) {
            foreach ($vocabulary->lexiconItems()->get() as $item) {
                if ($item->image_media_id !== null) {
                    continue;
                }

                $targets[] = [
                    'type' => 'lexicon',
                    'id' => $item->id,
                    'key' => 'image_media_id',
                    'prompt' => sprintf(
                        'A clear, realistic photo that shows "%s" in a modern hotel. %s Simple composition, no text or letters in the image.',
                        $item->english_text,
                        $item->simple_explanation ?? '',
                    ),
                    'alt' => Str::limit($item->english_text, 200, ''),
                    'size' => ImageProvider::SIZE_SQUARE,
                ];
            }
        }

        return $targets;
    }

    private function requeueMissingImages(ContentGeneration $generation): void
    {
        if (! $generation->option('images')) {
            return;
        }

        $targets = [];
        foreach (Lesson::query()->whereIn('id', $generation->lessonIds())->get() as $lesson) {
            $targets = [...$targets, ...$this->imageTargets($lesson, null)];
        }

        $generation->forceFill([
            'images_total' => $generation->images_done + count($targets),
            'images_failed' => 0,
        ])->save();

        foreach ($targets as $target) {
            GenerateContentImage::dispatch($generation->id, $target);
        }
    }

    private function provisionalTitle(string $prompt, bool $isCourse): string
    {
        $title = Str::limit(trim(preg_replace('/\s+/u', ' ', $prompt) ?? $prompt), 60, '');

        return $title !== '' ? $title : ($isCourse ? __('AI course', [], 'en') : __('AI lesson', [], 'en'));
    }

    /**
     * The department name the prompts carry.
     */
    public function departmentName(ContentGeneration $generation): string
    {
        return Department::query()->whereKey($generation->department_id)->value('name') ?? '';
    }
}
