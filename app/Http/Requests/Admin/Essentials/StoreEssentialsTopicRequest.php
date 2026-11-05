<?php

namespace App\Http\Requests\Admin\Essentials;

use App\Enums\EssentialsIcon;
use App\Http\Requests\Admin\Lessons\StoreCourseRequest;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new English Essentials topic from the studio (client request
 * 2026-11-05): title, a short description, its list icon and who sees it
 * (no department = every department). Shared with every hotel for the
 * platform team, their own hotel for a hotel admin (CMS-04, ORG-04).
 */
class StoreEssentialsTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can('create', Course::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', 'string', Rule::enum(EssentialsIcon::class)],
            'tone' => ['nullable', 'string', Rule::in(StoreCourseRequest::TONES)],
            'audience_department_ids' => ['array', 'max:100'],
            'audience_department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function audience(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('audience_department_ids', []);

        return array_values(array_map('intval', $ids));
    }
}
