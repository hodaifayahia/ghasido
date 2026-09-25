<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\EmployeeBulkAction;
use Illuminate\Validation\Rule;

/**
 * Bulk Actions: activate, deactivate or remind the checked rows
 * (AUTH-08, REM-07; spec 0003 Part D, employees.bulk).
 */
class BulkEmployeesRequest extends EmployeeIdsRequest
{
    protected function ability(): string
    {
        return match ($this->action()) {
            EmployeeBulkAction::Activate => 'activate',
            EmployeeBulkAction::Deactivate => 'deactivate',
            EmployeeBulkAction::Remind => 'remind',
            null => 'update',
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'action' => ['required', Rule::enum(EmployeeBulkAction::class)],
        ];
    }

    /**
     * Null until validation has accepted the action; employees() only runs
     * after that, so the null branch of ability() is never reached in
     * practice and is only there to keep the match total.
     */
    public function action(): ?EmployeeBulkAction
    {
        return EmployeeBulkAction::tryFrom((string) $this->input('action'));
    }
}
