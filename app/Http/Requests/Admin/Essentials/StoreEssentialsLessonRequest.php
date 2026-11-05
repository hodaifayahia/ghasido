<?php

namespace App\Http\Requests\Admin\Essentials;

use App\Enums\EssentialsLessonTemplate;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new lesson in an English Essentials topic, from a beginner template
 * (client request 2026-11-05). Only an Essentials topic the admin may edit
 * takes one (CoursePolicy::update, SEC-01).
 */
class StoreEssentialsLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            && $course->isEssentials()
            && ($this->user('web')?->can('update', $course) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'template' => ['required', 'string', Rule::enum(EssentialsLessonTemplate::class)],
            'open_editor' => ['sometimes', 'boolean'],
        ];
    }

    public function template(): EssentialsLessonTemplate
    {
        return EssentialsLessonTemplate::from((string) $this->validated('template'));
    }
}
