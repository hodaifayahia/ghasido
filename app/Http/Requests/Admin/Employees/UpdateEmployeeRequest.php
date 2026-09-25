<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit an employee (AUTH-06, ORG-03; spec 0003 Part D).
 *
 * The password is optional here: leaving it blank keeps the current one, and
 * a reset has its own action so it is audited as one.
 */
class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeRules;

    public function authorize(): bool
    {
        $actor = $this->user();
        $employee = $this->route('employee');

        if ($actor === null || ! $employee instanceof User || ! $actor->can('update', $employee)) {
            return false;
        }

        // Moving the account to another hotel is refused by the tenant
        // boundary (ROLE-02). Editing is EmployeesManage, not EmployeesCreate,
        // so a Manager who may edit but not add is not blocked here.
        $hotel = Hotel::query()->withoutGlobalScopes()->find((int) $this->input('hotel_id', 0));

        // The ability is on UserPolicy, so the policy class is named first:
        // a bare Hotel argument would route the check to HotelPolicy.
        return $hotel === null || $actor->can('assignToHotel', [User::class, $hotel]);
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmployeeInput();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');

        return $this->employeeRules($employee instanceof User ? $employee : null)
            + ['password' => $this->passwordRules(false)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->employeeMessages();
    }

    /**
     * @return array{name: string, username: string, email: string|null, hotel_id: int, department_id: int, status: string, allow_reminder_emails: bool, password?: string}
     */
    public function changes(): array
    {
        return $this->employeeData();
    }
}
