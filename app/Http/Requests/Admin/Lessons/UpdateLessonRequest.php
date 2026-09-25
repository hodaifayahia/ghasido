<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\Accent;
use App\Models\Lesson;
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

        return $lesson instanceof Lesson && ($this->user()?->can('update', $lesson) ?? false);
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
