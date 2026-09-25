<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a course (CMS-01, CMS-04, ORG-04). `hotel_id` null = shared.
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
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', Rule::in(self::TONES)],
        ];
    }

    /**
     * @return array{title: string, department_id: int, hotel_id: int|null, description: string|null, tone: string}
     */
    public function courseData(): array
    {
        /** @var array{title: string, department_id: int|string, hotel_id?: int|string|null, description?: string|null, tone?: string|null} $data */
        $data = $this->validated();

        return [
            'title' => $data['title'],
            'department_id' => (int) $data['department_id'],
            'hotel_id' => isset($data['hotel_id']) && $data['hotel_id'] !== '' ? (int) $data['hotel_id'] : null,
            'description' => $data['description'] ?? null,
            'tone' => $data['tone'] ?? 'brand',
        ];
    }
}
