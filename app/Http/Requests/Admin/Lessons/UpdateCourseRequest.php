<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\EnglishLevel;
use App\Enums\EssentialsIcon;
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
            'level' => ['sometimes', 'nullable', 'string', Rule::enum(EnglishLevel::class)],
            // English Essentials topics only (client request 2026-10-18):
            // who sees it (empty = every department) and its list icon.
            'audience_department_ids' => ['sometimes', 'array', 'max:100'],
            'audience_department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')],
            'icon' => ['sometimes', 'nullable', 'string', Rule::enum(EssentialsIcon::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function courseData(): array
    {
        $data = $this->validated();

        if (array_key_exists('level', $data) && $data['level'] === '') {
            $data['level'] = null;
        }

        unset($data['audience_department_ids']);

        return $data;
    }

    /**
     * The new audience of an Essentials topic, or null when not sent.
     *
     * @return list<int>|null
     */
    public function audience(): ?array
    {
        if (! $this->exists('audience_department_ids')) {
            return null;
        }

        /** @var array<int, int|string> $ids */
        $ids = $this->validated('audience_department_ids', []);

        return array_values(array_map('intval', $ids));
    }
}
