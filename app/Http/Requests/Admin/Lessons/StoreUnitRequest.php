<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a unit to a course the user may edit (CMS-01).
 */
class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = Course::query()->find((int) $this->input('course_id'));

        return $course !== null && ($this->user()?->can('update', $course) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'title' => ['required', 'string', 'max:120'],
        ];
    }

    public function course(): Course
    {
        return Course::query()->findOrFail((int) $this->validated('course_id'));
    }

    public function title(): string
    {
        return (string) $this->validated('title');
    }
}
