<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\CourseTrack;
use App\Enums\EnglishLevel;
use App\Enums\EssentialsIcon;
use App\Enums\Role;
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
        $user = $this->user('web');

        if ($user === null || ! $user->can('create', Course::class)) {
            return false;
        }

        // Security audit 2026-11-08: below the Super Admin a new course
        // belongs to the actor's own hotel (shared only for staff with no
        // hotel), never to another hotel (ROLE-02, SEC-01, CMS-04).
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        return $this->hotelId() === $user->hotel_id;
    }

    /**
     * The hotel the new course belongs to. Left blank ("Shared") by hotel
     * staff, it falls back to their own hotel: only the Super Admin and
     * staff with no hotel author shared content (security audit 2026-11-08).
     */
    public function hotelId(): ?int
    {
        if ($this->filled('hotel_id')) {
            return $this->integer('hotel_id');
        }

        $user = $this->user('web');

        return $user === null || $user->hasRole(Role::SuperAdmin->value) ? null : $user->hotel_id;
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
            'hotel_id' => $this->hotelId(),
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
