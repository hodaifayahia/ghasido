<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\CourseTrack;
use App\Enums\EnglishLevel;
use App\Enums\EssentialsIcon;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a course (CMS-01, CMS-04, ORG-04). `hotel_id` null = shared.
 *
 * `department_id` = "essentials" (the English Essentials entry of the
 * builder's department list) creates an English Essentials topic instead
 * (client request 2026-10-18): no department of its own, seen by
 * `audience_department_ids` (empty = every department).
 */
class StoreCourseRequest extends FormRequest
{
    public const array TONES = ['brand', 'aqua', 'success', 'warning', 'gold', 'danger'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', Course::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'department_id' => $this->isEssentials()
                ? ['required', 'string']
                : ['required', 'integer', Rule::exists('departments', 'id')],
            'audience_department_ids' => ['sometimes', 'array', 'max:100'],
            'audience_department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')],
            'icon' => ['sometimes', 'nullable', 'string', Rule::enum(EssentialsIcon::class)],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', Rule::in(self::TONES)],
            // Empty = every level (client decision 2026-09-30).
            'level' => ['nullable', 'string', Rule::enum(EnglishLevel::class)],
        ];
    }

    public function isEssentials(): bool
    {
        return $this->input('department_id') === CourseTrack::Essentials->value;
    }

    /**
     * @return array{title: string, department_id: int|null, track: string, icon: string|null, hotel_id: int|null, description: string|null, tone: string, level: string|null}
     */
    public function courseData(): array
    {
        /** @var array{title: string, department_id: int|string, hotel_id?: int|string|null, description?: string|null, tone?: string|null, level?: string|null, icon?: string|null} $data */
        $data = $this->validated();
        $essentials = $this->isEssentials();

        return [
            'title' => $data['title'],
            'department_id' => $essentials ? null : (int) $data['department_id'],
            'track' => $essentials ? CourseTrack::Essentials->value : CourseTrack::Department->value,
            'icon' => $essentials ? ($data['icon'] ?? null) : null,
            'hotel_id' => isset($data['hotel_id']) && $data['hotel_id'] !== '' ? (int) $data['hotel_id'] : null,
            'description' => $data['description'] ?? null,
            'tone' => $data['tone'] ?? 'brand',
            // Essentials are the beginner basics for every level.
            'level' => $essentials ? null : (isset($data['level']) && $data['level'] !== '' ? $data['level'] : null),
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
