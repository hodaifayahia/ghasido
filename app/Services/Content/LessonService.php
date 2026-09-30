<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\PhrasebookItem;
use App\Models\PronunciationAttempt;
use App\Models\RoleplayAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pronunciation\LessonSpeech;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every write to a course, unit or lesson (CMS-01, CMS-04, CMS-05, LESSON-02,
 * BLD-07; spec 0003 Part D).
 *
 * The controller validates through a form request and hands the clean data
 * here. Each method changes the rows and writes exactly one audit row inside
 * one transaction (SEC-06, ADM-03). "Archive" is a draft; "Delete" removes a
 * lesson only while no learner has touched it, otherwise it hides the lesson
 * and keeps every answer (DATA-10).
 */
class LessonService
{
    // ------------------------------------------------------------- courses

    /**
     * @param  array{title: string, department_id: int, hotel_id: int|null, description: string|null, tone: string}  $data
     */
    public function createCourse(array $data, User $actor): Course
    {
        return DB::transaction(function () use ($data, $actor): Course {
            $course = new Course;
            $course->fill($data);
            $course->slug = $this->uniqueSlug(Course::class, $data['title'], 'course');
            $course->position = (int) Course::query()->where('department_id', $data['department_id'])->max('position') + 1;
            $course->status = ContentStatus::Draft;
            $course->created_by = $actor->id;
            $course->save();

            AuditLog::record($course, 'course.created', ['created' => $course->only(['title', 'department_id', 'hotel_id', 'tone'])]);

            return $course;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCourse(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data): Course {
            $course->fill($data);

            if (($data['status'] ?? null) === ContentStatus::Published->value && $course->published_at === null) {
                $course->published_at = Carbon::now();
            }

            AuditLog::record($course, 'course.updated');
            $course->save();

            return $course;
        });
    }

    /**
     * Archive = back to draft so no learner sees it; every lesson, block and
     * answer stays (DATA-10).
     */
    public function archiveCourse(Course $course): Course
    {
        return DB::transaction(function () use ($course): Course {
            $course->status = ContentStatus::Draft;
            AuditLog::record($course, 'course.archived');
            $course->save();

            $course->lessons()->update(['status' => ContentStatus::Draft->value]);

            return $course;
        });
    }

    // --------------------------------------------------------------- units

    public function createUnit(Course $course, string $title): Unit
    {
        return DB::transaction(function () use ($course, $title): Unit {
            $unit = new Unit;
            $unit->course_id = $course->id;
            $unit->title = $title;
            $unit->position = (int) $course->units()->max('position') + 1;
            $unit->status = ContentStatus::Published;
            $unit->save();

            AuditLog::record($unit, 'unit.created', ['created' => ['title' => $title, 'course_id' => $course->id]]);

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUnit(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data): Unit {
            $unit->fill($data);
            AuditLog::record($unit, 'unit.updated');
            $unit->save();

            return $unit;
        });
    }

    // ------------------------------------------------------------- lessons

    /**
     * A new lesson is a draft. With the default block set it carries the
     * nine-step template as a starting point the admin then reorders or trims
     * (LESSON-02, BLD-07); the template is a seed, never a hard-coded pipeline.
     */
    public function createLesson(Unit $unit, string $title, bool $withDefaultBlocks): Lesson
    {
        return DB::transaction(function () use ($unit, $title, $withDefaultBlocks): Lesson {
            $course = $unit->course()->firstOrFail();

            $lesson = new Lesson;
            $lesson->unit_id = $unit->id;
            $lesson->title = $title;
            $lesson->slug = $this->uniqueLessonSlug($course, $title);
            $lesson->objectives = [];
            $lesson->position = (int) $unit->lessons()->max('position') + 1;
            $lesson->status = ContentStatus::Draft;
            $lesson->save();

            if ($withDefaultBlocks) {
                foreach (BlockDefaults::DEFAULT_BLOCKS as $index => $type) {
                    Block::query()->create([
                        'lesson_id' => $lesson->id,
                        'type' => $type,
                        'position' => $index + 1,
                        'layout' => 'full',
                        'settings' => BlockDefaults::settings($type),
                        'is_visible' => true,
                    ]);
                }
            }

            AuditLog::record($lesson, 'lesson.created', [
                'created' => ['title' => $title, 'unit_id' => $unit->id, 'default_blocks' => $withDefaultBlocks],
            ]);

            return $lesson;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLesson(Lesson $lesson, array $data): Lesson
    {
        $accentChanged = false;

        $lesson = DB::transaction(function () use ($lesson, $data, &$accentChanged): Lesson {
            $lesson->fill($data);

            if (($data['status'] ?? null) === ContentStatus::Published->value && $lesson->published_at === null) {
                $lesson->published_at = Carbon::now();
            }

            $accentChanged = $lesson->isDirty('accent');

            AuditLog::record($lesson, 'lesson.updated');
            $lesson->save();

            return $lesson;
        });

        // A new accent needs its own voice's clips and guides (spec 0006
        // §4). Queued after the commit, so a worker never looks for a clip
        // row that is not there yet.
        if ($accentChanged) {
            app(LessonSpeech::class)->prepare($lesson);
        }

        return $lesson;
    }

    /**
     * Publish a lesson so the learners of its department see it (ORG-04,
     * CMS-05, JOURNEY-03). A lesson inside a draft unit or course would
     * stay invisible, so publishing it also publishes those parents, and the
     * return value says which ones so the admin is told.
     *
     * @return array{unit: bool, course: bool}
     */
    public function publish(Lesson $lesson): array
    {
        return DB::transaction(function () use ($lesson): array {
            $lesson->status = ContentStatus::Published;
            $lesson->published_at ??= Carbon::now();
            AuditLog::record($lesson, 'lesson.published');
            $lesson->save();

            $published = ['unit' => false, 'course' => false];

            $unit = $lesson->unit()->first();
            if ($unit !== null && ! $unit->isPublished()) {
                $unit->status = ContentStatus::Published;
                AuditLog::record($unit, 'unit.published', ['via_lesson_id' => $lesson->id]);
                $unit->save();
                $published['unit'] = true;
            }

            $course = $lesson->course()->first();
            if ($course !== null && ! $course->isPublished()) {
                $course->status = ContentStatus::Published;
                $course->published_at ??= Carbon::now();
                AuditLog::record($course, 'course.published', ['via_lesson_id' => $lesson->id]);
                $course->save();
                $published['course'] = true;
            }

            return $published;
        });
    }

    public function unpublish(Lesson $lesson): Lesson
    {
        return DB::transaction(function () use ($lesson): Lesson {
            $lesson->status = ContentStatus::Draft;
            AuditLog::record($lesson, 'lesson.unpublished');
            $lesson->save();

            return $lesson;
        });
    }

    /**
     * Archive: a draft that no learner can open; nothing is deleted (DATA-10).
     */
    public function archiveLesson(Lesson $lesson): Lesson
    {
        return DB::transaction(function () use ($lesson): Lesson {
            $lesson->status = ContentStatus::Draft;
            AuditLog::record($lesson, 'lesson.archived');
            $lesson->save();

            return $lesson;
        });
    }

    /**
     * How many learner rows point at each lesson: step and lesson
     * completions, answers, role-play and pronunciation attempts (not admin
     * previews) and phrasebook items saved from it. Any of them makes a
     * "Delete" a removal that keeps the rows (DATA-10).
     *
     * @param  list<int>  $lessonIds
     * @return array<int, int> lesson id => learner rows
     */
    public function learnerRecordCounts(array $lessonIds): array
    {
        $counts = array_fill_keys($lessonIds, 0);

        if ($lessonIds === []) {
            return $counts;
        }

        $blockLessons = Block::query()->whereIn('lesson_id', $lessonIds)->pluck('lesson_id', 'id')->all();
        $blockIds = array_keys($blockLessons);

        $add = function (iterable $rows) use (&$counts): void {
            foreach ($rows as $row) {
                $id = (int) $row->getAttribute('lesson_key');
                if (array_key_exists($id, $counts)) {
                    $counts[$id] += (int) $row->getAttribute('total');
                }
            }
        };

        $add(BlockCompletion::query()->whereIn('lesson_id', $lessonIds)->selectRaw('lesson_id as lesson_key, count(*) as total')->groupBy('lesson_id')->get());
        $add(LessonCompletion::query()->whereIn('lesson_id', $lessonIds)->selectRaw('lesson_id as lesson_key, count(*) as total')->groupBy('lesson_id')->get());
        $add(PhrasebookItem::query()->whereIn('source_lesson_id', $lessonIds)->selectRaw('source_lesson_id as lesson_key, count(*) as total')->groupBy('source_lesson_id')->get());

        // Answers may carry the lesson, the block, or both; count each row once.
        foreach ([Attempt::class, RoleplayAttempt::class, PronunciationAttempt::class] as $model) {
            $query = $model::query()
                ->where(function (Builder $inner) use ($lessonIds, $blockIds): void {
                    $inner->whereIn('lesson_id', $lessonIds);
                    if ($blockIds !== []) {
                        $inner->orWhereIn('block_id', $blockIds);
                    }
                });

            if ($model === RoleplayAttempt::class) {
                $query->where('is_preview', false);
            }

            foreach ($query->get(['id', 'lesson_id', 'block_id']) as $row) {
                $lessonId = $row->getAttribute('lesson_id');
                $lessonId = is_numeric($lessonId) && array_key_exists((int) $lessonId, $counts)
                    ? (int) $lessonId
                    : ($blockLessons[(int) $row->getAttribute('block_id')] ?? null);

                if ($lessonId !== null && array_key_exists((int) $lessonId, $counts)) {
                    $counts[(int) $lessonId]++;
                }
            }
        }

        return $counts;
    }

    /**
     * "Delete" from the Lesson Directory (CMS-01).
     *
     * With no learner rows the lesson, its blocks and their placements go;
     * an activity goes with them only when nobody answered it and no other
     * block or test uses it (ActivityService::remove). With learner rows
     * nothing that holds or explains an answer is deleted (DATA-10, DATA-11):
     * the lesson becomes a draft marked archived, leaves the admin library
     * and every learner list, and keeps its blocks and answers for reports.
     *
     * @return bool true when the lesson row was deleted, false when removed
     */
    public function deleteLesson(Lesson $lesson): bool
    {
        return DB::transaction(function () use ($lesson): bool {
            $records = $this->learnerRecordCounts([$lesson->id])[$lesson->id] ?? 0;
            $snapshot = ['id' => $lesson->id, 'title' => $lesson->title, 'unit_id' => $lesson->unit_id, 'course_id' => $lesson->course_id, 'hotel_id' => $lesson->hotel_id];

            if ($records > 0) {
                $lesson->status = ContentStatus::Draft;
                $lesson->archived_at = Carbon::now();
                AuditLog::record($lesson, 'lesson.removed', ['learner_records' => $records, 'kept_for_reports' => true]);
                $lesson->save();

                $this->renumberLessons($lesson->unit_id);

                return false;
            }

            AuditLog::record($lesson, 'lesson.deleted', ['deleted' => $snapshot]);

            $activities = app(ActivityService::class);
            foreach ($lesson->blocks()->get() as $block) {
                foreach ($block->placements()->get() as $placement) {
                    $activities->remove($placement);
                }

                $block->lexiconItems()->detach();
                $block->scenarios()->detach();
                $block->delete();
            }

            $unitId = $lesson->unit_id;
            $lesson->delete();

            $this->renumberLessons($unitId);

            return true;
        });
    }

    /**
     * Positions 1..n for the lessons still in the library of one unit.
     */
    private function renumberLessons(int $unitId): void
    {
        $position = 1;

        foreach (Lesson::query()->where('unit_id', $unitId)->notArchived()->orderBy('position')->orderBy('id')->get(['id', 'position']) as $row) {
            if ($row->position !== $position) {
                Lesson::query()->whereKey($row->id)->update(['position' => $position]);
            }

            $position++;
        }
    }

    /**
     * A copy of the lesson and its blocks, as a draft at the end of the same
     * unit. Shared rows (lexicon items, activities, scenarios) are attached
     * again rather than copied, so a fix in one place reaches both (CMS-06).
     */
    public function duplicate(Lesson $lesson): Lesson
    {
        return DB::transaction(function () use ($lesson): Lesson {
            $course = $lesson->course()->firstOrFail();

            $copy = $lesson->replicate(['slug', 'status', 'published_at', 'position']);
            $copy->title = __(':title (copy)', ['title' => $lesson->title], 'en');
            $copy->slug = $this->uniqueLessonSlug($course, $copy->title);
            $copy->status = ContentStatus::Draft;
            $copy->published_at = null;
            $copy->position = (int) Lesson::query()->where('unit_id', $lesson->unit_id)->max('position') + 1;
            $copy->save();

            foreach ($lesson->blocks()->get() as $block) {
                $blockCopy = $block->replicate();
                $blockCopy->lesson_id = $copy->id;
                $blockCopy->save();

                $this->reattach($block, $blockCopy);
            }

            AuditLog::record($copy, 'lesson.duplicated', ['source_lesson_id' => $lesson->id]);

            return $copy;
        });
    }

    /**
     * Attach the shared rows of one block to its copy (used by duplicate here
     * and by BlockService::duplicate).
     */
    public function reattach(Block $source, Block $copy): void
    {
        $lexicon = [];
        foreach ($source->lexiconItems()->get() as $item) {
            $pivot = $item->getRelationValue('pivot');
            $position = $pivot instanceof Model ? $pivot->getAttribute('position') : 0;
            $lexicon[$item->id] = ['position' => is_numeric($position) ? (int) $position : 0];
        }

        if ($lexicon !== []) {
            $copy->lexiconItems()->sync($lexicon);
        }

        foreach ($source->placements()->get() as $placement) {
            $copy->placements()->create([
                'activity_id' => $placement->activity_id,
                'position' => $placement->position,
                'overrides' => $placement->overrides,
            ]);
        }

        $scenarioIds = $source->scenarioIds();
        if ($scenarioIds !== []) {
            $scenarioPivot = [];
            foreach ($scenarioIds as $index => $scenarioId) {
                $scenarioPivot[$scenarioId] = ['position' => $index + 1];
            }

            $copy->scenarios()->sync($scenarioPivot);
        }
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param  class-string<Course>  $model
     */
    private function uniqueSlug(string $model, string $title, string $fallback): string
    {
        $base = Str::slug($title) ?: $fallback;
        $slug = $base;
        $counter = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function uniqueLessonSlug(Course $course, string $title): string
    {
        $base = Str::slug($title) ?: 'lesson';
        $slug = $base;
        $counter = 2;

        while (Lesson::query()->where('course_id', $course->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
