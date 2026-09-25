<?php

namespace App\Http\Requests\Admin\Messages;

use App\Enums\AutomationTrigger;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\ReminderTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields of an automation rule, shared by create and edit (REM-03).
 *
 * `days` is the inactivity window for `inactive_days` and the repeat window
 * for every trigger; blank falls back to the platform defaults the runner
 * reads (spec 0003 Part C).
 */
abstract class AutomationRuleRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'trigger' => ['required', Rule::enum(AutomationTrigger::class)],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'template_id' => ['required', 'integer', Rule::exists(ReminderTemplate::class, 'id')],
            'hotel_ids' => ['sometimes', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists(Hotel::class, 'id')],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists(Department::class, 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_id.exists' => __('Choose a reminder template for this rule.'),
        ];
    }

    /**
     * @return array{name: string, trigger: AutomationTrigger, days: int|null, template_id: int, hotel_ids: list<int>, department_ids: list<int>, is_active: bool}
     */
    public function ruleData(): array
    {
        $days = $this->validated('days');

        return [
            'name' => (string) $this->validated('name'),
            'trigger' => AutomationTrigger::from((string) $this->validated('trigger')),
            'days' => is_numeric($days) ? (int) $days : null,
            'template_id' => (int) $this->validated('template_id'),
            'hotel_ids' => $this->ids('hotel_ids'),
            'department_ids' => $this->ids('department_ids'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ];
    }

    /**
     * @return list<int>
     */
    private function ids(string $key): array
    {
        $ids = $this->validated($key);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
