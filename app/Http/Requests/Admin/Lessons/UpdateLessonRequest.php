<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\Accent;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every field of the lesson editor and the Settings tab (CMS-01, CMS-05,
 * BLD-08). Partial: the editor autosaves one field at a time on blur, so
 * every rule is `sometimes`.
 */
class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');
        $user = $this->user('web');

        if (! $lesson instanceof Lesson || $user === null || ! $user->can('update', $lesson)) {
            return false;
        }

        if (! $this->has('unit_id')) {
            return true;
        }

        // Security audit 2026-11-08: moving the lesson re-homes it to the
        // target unit's hotel (the lesson hook copies the course's
        // hotel_id), so the target course must be one the actor may edit,
        // exactly as MoveLessonRequest requires (ROLE-02, SEC-01). An
        // unknown unit fails validation below, not authorization.
        $target = Unit::query()->with('course')->find($this->integer('unit_id'));

        return $target === null || ($target->course !== null && $user->can('update', $target->course));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:100'],
            'introduction' => ['sometimes', 'nullable', 'string', 'max:500'],
            'objectives' => ['sometimes', 'array', 'max:12'],
            'objectives.*' => ['required', 'string', 'max:160'],
            'cover_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media_assets', 'id')],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'completion_condition' => ['sometimes', 'nullable', 'array'],
            'completion_condition.rule' => ['sometimes', 'string', Rule::in(['all_steps', 'last_step', 'practice_passed'])],
            'completion_condition.min_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
            // British or American English: the lesson's voice and the accent
            // a learner's speech is judged in (spec 0006 §3).
            'accent' => ['sometimes', 'nullable', Rule::enum(Accent::class)],
            'unit_id' => ['sometimes', 'integer', Rule::exists('units', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lessonData(): array
    {
        return $this->validated();
    }
}
