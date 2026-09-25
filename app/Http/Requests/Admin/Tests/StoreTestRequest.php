<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
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
        return $this->user()?->can(Permission::TestsManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(TestType::class)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')],
            'time_limit_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
    }

    /**
     * @return array{title: string, type: TestType, department_id: int, hotel_id: int|null, time_limit_seconds: int|null}
     */
    public function testData(): array
    {
        $time = $this->validated('time_limit_minutes');

        return [
            'title' => trim((string) $this->validated('title')),
            'type' => TestType::from((string) $this->validated('type')),
            'department_id' => (int) $this->validated('department_id'),
            'hotel_id' => $this->filled('hotel_id') ? (int) $this->validated('hotel_id') : null,
            'time_limit_seconds' => $time === null || (int) $time === 0 ? null : (int) $time * 60,
        ];
    }
}
