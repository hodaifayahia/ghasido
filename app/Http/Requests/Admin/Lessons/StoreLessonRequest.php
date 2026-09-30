<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\EnglishLevel;
use App\Models\Course;
use App\Models\Department;
use App\Models\Unit;
use App\Models\User;
use App\Services\Content\LessonService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a lesson (CMS-01). It lands as a draft with the default block set
 * (LESSON-02, BLD-07) unless `blank` is set.
 *
 * The course and unit are optional (client request 2026-09-29): with only a
 * department, the lesson goes into that department's "General" course and
 * unit, created once and reused; with a course but no unit, into the
 * course's first unit (or a new "General" one).
 */
class StoreLessonRequest extends FormRequest
{
    /** The title of the course and unit a lesson falls back to. */
    public const string DEFAULT_TITLE = 'General';

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($this->filled('unit_id')) {
            $unit = Unit::query()->find($this->integer('unit_id'));

            return $unit !== null && $user->can('update', $unit->course()->firstOrFail());
        }

        if ($this->filled('course_id') && ! $this->filled('new_course_title')) {
            $course = Course::query()->find($this->integer('course_id'));

            return $course !== null && $user->can('update', $course);
        }

        return $user->can('create', Course::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'department_id' => ['nullable', 'required_without_all:unit_id,course_id', 'required_with:new_course_title', 'integer', Rule::exists('departments', 'id')],
            // A brand-new course and/or unit typed on the create page
            // (client request 2026-09-29).
            'new_course_title' => ['nullable', 'string', 'max:120'],
            'new_unit_title' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'blank' => ['sometimes', 'boolean'],
            // Department + level decide which learners see the lesson
            // (client decision 2026-09-30). Empty = every level.
            'level' => ['nullable', 'string', Rule::enum(EnglishLevel::class)],
        ];
    }

    /**
     * The unit the lesson goes into, creating the fallback when needed.
     *
     * A lesson is made for one level (client request 2026-09-30). Choosing
     * a course (or a unit of one) made for another level puts the lesson in
     * the same course for the chosen level: a course with the same title,
     * department and hotel at that level, created on first use.
     */
    public function unit(LessonService $lessons): Unit
    {
        if ($this->filled('unit_id') && ! $this->filled('new_course_title') && ! $this->filled('new_unit_title')) {
            $unit = Unit::query()->findOrFail($this->integer('unit_id'));
            $course = $unit->course()->firstOrFail();

            if (! $this->differentLevel($course)) {
                return $unit;
            }

            $sibling = $this->levelCourse($course, $lessons);

            return $sibling->units()->where('title', $unit->title)->orderBy('id')->first()
                ?? $lessons->createUnit($sibling, $unit->title);
        }

        $course = match (true) {
            $this->filled('new_course_title') => $this->newCourse($lessons),
            $this->filled('course_id') => Course::query()->findOrFail($this->integer('course_id')),
            default => $this->defaultCourse($lessons),
        };

        if ($this->differentLevel($course)) {
            $course = $this->levelCourse($course, $lessons);
        }

        if ($this->filled('new_unit_title')) {
            return $lessons->createUnit($course, trim((string) $this->input('new_unit_title')));
        }

        $unit = $course->units()->orderBy('position')->orderBy('id')->first();

        return $unit instanceof Unit ? $unit : $lessons->createUnit($course, self::DEFAULT_TITLE);
    }

    public function title(): string
    {
        return (string) $this->validated('title');
    }

    public function withDefaultBlocks(): bool
    {
        return ! $this->boolean('blank');
    }

    /** A new course in the chosen department, in the user's own scope. */
    private function newCourse(LessonService $lessons): Course
    {
        /** @var User $user */
        $user = $this->user();
        $department = Department::query()->active()->visibleTo($user)->find($this->integer('department_id'));
        abort_if($department === null, 403);

        return $lessons->createCourse([
            'title' => trim((string) $this->input('new_course_title')),
            'department_id' => $department->id,
            'hotel_id' => $user->hotel_id,
            'description' => null,
            'tone' => 'brand',
            'level' => $this->level(),
        ], $user);
    }

    private function differentLevel(Course $course): bool
    {
        return $this->level() !== null && $course->level?->value !== $this->level();
    }

    /** The same course (title, department, hotel) for the chosen level. */
    private function levelCourse(Course $course, LessonService $lessons): Course
    {
        /** @var User $user */
        $user = $this->user();
        abort_unless($user->can('create', Course::class), 403);

        $existing = Course::query()
            ->where('department_id', $course->department_id)
            ->where('title', $course->title)
            ->where('level', $this->level())
            ->when(
                $course->hotel_id === null,
                fn ($query) => $query->whereNull('hotel_id'),
                fn ($query) => $query->where('hotel_id', $course->hotel_id),
            )
            ->orderBy('id')
            ->first();

        return $existing ?? $lessons->createCourse([
            'title' => $course->title,
            'department_id' => $course->department_id,
            'hotel_id' => $course->hotel_id,
            'description' => $course->description,
            'tone' => $course->tone,
            'level' => $this->level(),
        ], $user);
    }

    /** The chosen level's value, or null for every level. */
    public function level(): ?string
    {
        $level = $this->input('level');

        return is_string($level) && EnglishLevel::tryFrom($level) !== null ? $level : null;
    }

    /**
     * The department's "General" course in the user's own scope (shared for
     * the platform team, their hotel otherwise), created on first use.
     */
    private function defaultCourse(LessonService $lessons): Course
    {
        /** @var User $user */
        $user = $this->user();
        $department = Department::query()->active()->visibleTo($user)->find($this->integer('department_id'));
        abort_if($department === null, 403);

        $existing = Course::query()
            ->where('department_id', $department->id)
            ->when(
                $user->hotel_id === null,
                fn ($query) => $query->whereNull('hotel_id'),
                fn ($query) => $query->where('hotel_id', $user->hotel_id),
            )
            ->where('title', self::DEFAULT_TITLE)
            ->when(
                $this->level() === null,
                fn ($query) => $query->whereNull('level'),
                fn ($query) => $query->where('level', $this->level()),
            )
            ->orderBy('id')
            ->first();

        return $existing ?? $lessons->createCourse([
            'title' => self::DEFAULT_TITLE,
            'department_id' => $department->id,
            'hotel_id' => $user->hotel_id,
            'description' => null,
            'tone' => 'brand',
            'level' => $this->level(),
        ], $user);
    }
}
