<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit a course's title, description, tone or scope (CMS-01).
 */
class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course && ($this->user()?->can('update', $course) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tone' => ['sometimes', 'string', Rule::in(StoreCourseRequest::TONES)],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function courseData(): array
    {
        return $this->validated();
    }
}
