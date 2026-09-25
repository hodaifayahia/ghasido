<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\ReminderTemplate;
use Illuminate\Validation\Rule;

/**
 * Send Reminder: one row's mail button, or the panel under the table for the
 * checked rows (REM-02, REM-07; spec 0003 Part D, employees.remind).
 *
 * With no template named, the default "Training comeback reminder" is used.
 */
class RemindEmployeesRequest extends EmployeeIdsRequest
{
    protected function ability(): string
    {
        return 'remind';
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'template_id' => ['nullable', 'integer', Rule::exists(ReminderTemplate::class, 'id')->where('is_active', true)],
        ];
    }

    public function templateId(): ?int
    {
        $id = $this->validated('template_id');

        return $id === null ? null : (int) $id;
    }
}
