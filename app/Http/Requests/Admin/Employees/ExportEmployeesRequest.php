<?php

namespace App\Http\Requests\Admin\Employees;

use App\Exports\EmployeesExport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Export Employees: the filtered directory as .xlsx or .csv (REP-03, REP-08;
 * spec 0003 Part D, employees.export).
 */
class ExportEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export', User::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(EmployeesExport::FORMATS)],
        ];
    }

    public function exportFormat(): string
    {
        return (string) $this->validated('format');
    }
}
