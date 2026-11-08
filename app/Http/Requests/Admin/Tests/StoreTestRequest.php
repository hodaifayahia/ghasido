<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\EnglishLevel;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create an assessment definition (TEST-01, TSTM-01, TSTM-02).
 */
class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        if ($user === null || ! $user->can(Permission::TestsManage->value)) {
            return false;
        }

        // Security audit 2026-11-08: below the Super Admin a new test belongs
        // to the actor's own hotel (shared only for staff with no hotel), so
        // nobody authors content into another hotel (ROLE-02, SEC-01).
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        return $this->hotelId() === $user->hotel_id;
    }

    /**
     * The hotel the new test belongs to. Left blank ("Shared") by hotel
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

    protected function prepareForValidation(): void
    {
        // The builder's "All departments" option.
        if (in_array($this->input('department_id'), ['all', ''], true)) {
            $this->merge(['department_id' => null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(TestType::class)],
            // Empty = all departments (client request 2026-09-29).
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')],
            'time_limit_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            // Empty = every level (client decision 2026-09-30).
            'level' => ['nullable', 'string', Rule::enum(EnglishLevel::class)],
        ];
    }

    /**
     * @return array{title: string, type: TestType, department_id: int|null, hotel_id: int|null, time_limit_seconds: int|null, level: string|null}
     */
    public function testData(): array
    {
        $time = $this->validated('time_limit_minutes');

        return [
            'title' => trim((string) $this->validated('title')),
            'type' => TestType::from((string) $this->validated('type')),
            'department_id' => $this->validated('department_id') === null ? null : (int) $this->validated('department_id'),
            'hotel_id' => $this->hotelId(),
            'time_limit_seconds' => $time === null || (int) $time === 0 ? null : (int) $time * 60,
            'level' => $this->filled('level') ? (string) $this->validated('level') : null,
        ];
    }
}
