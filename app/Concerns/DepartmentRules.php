<?php

namespace App\Concerns;

use App\Enums\DepartmentStatus;
use App\Models\Department;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared validation for departments, so create and edit cannot drift apart.
 *
 * The composite unique index on (hotel_id, slug) cannot constrain the shared
 * catalogue, because both MySQL and SQLite allow repeated nulls in a unique
 * index. Uniqueness among global departments therefore lives here, in
 * validation, which is a known limitation recorded in spec 0002 rather than an
 * oversight.
 *
 * Used by the Departments form requests (spec 0003, Part D); DepartmentRulesTest
 * proves the slug rule behaves on its own.
 */
trait DepartmentRules
{
    /**
     * Slug must be unique within its own catalogue: among the global rows for
     * a shared department, or within the hotel for a hotel owned one.
     */
    protected function departmentSlugRule(?int $hotelId, ?int $ignoreId = null): Unique
    {
        $rule = Rule::unique(Department::class, 'slug');

        $rule = $hotelId === null
            ? $rule->whereNull('hotel_id')
            : $rule->where('hotel_id', $hotelId);

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
    }

    /**
     * The fields a person types: name, focus line and editorial status. The
     * slug is derived from the name and validated separately.
     *
     * @return array<string, list<mixed>>
     */
    protected function departmentFieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'focus' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(DepartmentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function departmentMessages(): array
    {
        return [
            'slug.unique' => __('A department with this name already exists in that catalogue.'),
            'hotel_id.required_if' => __('Choose the hotel this department belongs to.'),
        ];
    }
}
