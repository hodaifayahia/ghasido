<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Move a lesson to a unit and position (client request 2026-11-07). The
 * admin must be able to edit the lesson's course and the target unit's
 * course (CoursePolicy::update, SEC-01); a removed unit is not a target.
 */
class MoveLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');
        $user = $this->user('web');

        if (! $lesson instanceof Lesson || $user === null || ! $user->can('update', $lesson)) {
            return false;
        }

        $target = Unit::query()->with('course')->find($this->integer('unit_id'));

        // An unknown unit fails validation below, not authorization.
        return $target === null || ($target->course !== null && $user->can('update', $target->course));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->whereNull('archived_at')],
            'position' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function target(): Unit
    {
        return Unit::query()->findOrFail($this->integer('unit_id'));
    }

    public function position(): ?int
    {
        $position = $this->validated('position');

        return $position === null ? null : (int) $position;
    }
}
