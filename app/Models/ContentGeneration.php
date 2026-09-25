<?php

namespace App\Models;

use App\Enums\ContentGenerationType;
use App\Enums\GenerationStatus;
use App\Policies\ContentGenerationPolicy;
use Database\Factories\ContentGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One "Generate with AI" request and its progress (GEN-01, GEN-03, GEN-04,
 * PERF-04; spec 0004).
 *
 * The queued jobs write here and the Lessons & Content screen polls it. Every
 * generation reaches `done` or `failed`; a failure keeps the reason and what
 * was already created, and Retry only redoes the missing parts.
 *
 * `lesson_ids` maps the outline index to the lesson it produced, which is
 * what makes a retried lesson job idempotent: an index that already has a
 * lesson is never written twice.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ContentGenerationType $type
 * @property string $prompt
 * @property int|null $department_id
 * @property int|null $hotel_id
 * @property string|null $level
 * @property array{images?: bool, audio?: bool, lesson_count?: int, created_course?: bool}|null $options
 * @property GenerationStatus $status
 * @property string|null $stage
 * @property string|null $failed_reason
 * @property int|null $course_id
 * @property int|null $unit_id
 * @property array{title?: string, description?: string, lessons?: list<array{title: string, topic: string}>}|null $outline
 * @property array<int|string, int>|null $lesson_ids
 * @property int $lessons_total
 * @property int $lessons_done
 * @property int $images_total
 * @property int $images_done
 * @property int $images_failed
 * @property list<string>|null $audio_texts
 * @property array{type?: string, id?: int, key?: string, prompt?: string, alt?: string, size?: string}|null $target
 * @property int|null $media_asset_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UsePolicy(ContentGenerationPolicy::class)]
#[Fillable([
    'user_id',
    'type',
    'prompt',
    'department_id',
    'hotel_id',
    'level',
    'options',
    'status',
    'stage',
    'failed_reason',
    'course_id',
    'unit_id',
    'outline',
    'lesson_ids',
    'lessons_total',
    'lessons_done',
    'images_total',
    'images_done',
    'images_failed',
    'audio_texts',
    'target',
    'media_asset_id',
    'started_at',
    'finished_at',
])]
class ContentGeneration extends Model
{
    /** @use HasFactory<ContentGenerationFactory> */
    use HasFactory;

    public const string STAGE_OUTLINE = 'outline';

    public const string STAGE_WRITING = 'writing';

    public const string STAGE_MEDIA = 'media';

    public const string STAGE_DONE = 'done';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContentGenerationType::class,
            'status' => GenerationStatus::class,
            'options' => 'array',
            'outline' => 'array',
            'lesson_ids' => 'array',
            'audio_texts' => 'array',
            'target' => 'array',
            'lessons_total' => 'integer',
            'lessons_done' => 'integer',
            'images_total' => 'integer',
            'images_done' => 'integer',
            'images_failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    // ---------------------------------------------------------------- reading

    public function option(string $key, bool $default = false): bool
    {
        $value = ($this->options ?? [])[$key] ?? $default;

        return (bool) $value;
    }

    public function requestedLessonCount(): int
    {
        return max(1, (int) (($this->options ?? [])['lesson_count'] ?? 1));
    }

    /**
     * The lesson written for one outline index, if any.
     */
    public function lessonIdFor(int $index): ?int
    {
        $map = $this->lesson_ids ?? [];

        return array_key_exists($index, $map) ? (int) $map[$index] : null;
    }

    /**
     * Every lesson this generation wrote, in outline order.
     *
     * @return list<int>
     */
    public function lessonIds(): array
    {
        $map = $this->lesson_ids ?? [];
        ksort($map, SORT_NUMERIC);

        return array_values(array_map('intval', $map));
    }

    /**
     * The outline topic for one index, falling back to the admin's prompt.
     *
     * @return array{title: string, topic: string}
     */
    public function outlineLesson(int $index): array
    {
        $lessons = ($this->outline ?? [])['lessons'] ?? [];

        return $lessons[$index] ?? ['title' => '', 'topic' => $this->prompt];
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    // ---------------------------------------------------------------- writing

    /**
     * Record the lesson one index produced, under a row lock so two lesson
     * jobs finishing together never lose each other's id.
     */
    public function recordLesson(int $index, int $lessonId): void
    {
        DB::transaction(function () use ($index, $lessonId): void {
            /** @var ContentGeneration $fresh */
            $fresh = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $map = $fresh->lesson_ids ?? [];
            $map[$index] = $lessonId;

            $fresh->lesson_ids = $map;
            $fresh->lessons_done = count($map);
            $fresh->save();

            $this->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /**
     * Append sentences whose audio was queued, without duplicates.
     *
     * @param  list<string>  $texts
     */
    public function recordAudioTexts(array $texts): void
    {
        if ($texts === []) {
            return;
        }

        DB::transaction(function () use ($texts): void {
            /** @var ContentGeneration $fresh */
            $fresh = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $fresh->audio_texts = array_values(array_unique(array_merge($fresh->audio_texts ?? [], $texts)));
            $fresh->save();

            $this->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    public function markRunning(string $stage): void
    {
        $this->forceFill([
            'status' => GenerationStatus::Running,
            'stage' => $stage,
            'failed_reason' => null,
            'started_at' => $this->started_at ?? Carbon::now(),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => GenerationStatus::Failed,
            'failed_reason' => mb_substr($reason, 0, 1000),
            'finished_at' => Carbon::now(),
        ])->save();
    }
}
