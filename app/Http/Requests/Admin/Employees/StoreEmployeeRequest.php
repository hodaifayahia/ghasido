<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add New Employee (AUTH-02, AUTH-03, SUB-02; spec 0003 Part D).
 *
 * An Admin or Manager may only create accounts in their own hotel: the hotel named in
 * the form is checked against the policy before validation runs, so a foreign
 * hotel id is a 403, never a 422 (ROLE-02).
 */
class StoreEmployeeRequest extends FormRequest
{
    use EmployeeRules;

    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null || ! $actor->can('create', User::class)) {
            return false;
        }

        $hotel = Hotel::query()->withoutGlobalScopes()->find((int) $this->input('hotel_id', 0));

        // An unknown hotel is left to validation (422); a real one that is not
        // the actor's is the tenant boundary (403).
        // The ability is on UserPolicy, so the policy class is named first:
        // a bare Hotel argument would route the check to HotelPolicy.
        return $hotel === null || $actor->can('createIn', [User::class, $hotel]);
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
        return $this->employeeRules() + ['password' => $this->passwordRules(true)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->employeeMessages();
    }

    /**
     * @return array{name: string, username: string, email: string|null, hotel_id: int, department_id: int, status: string, allow_reminder_emails: bool, password: string}
     */
    public function newEmployee(): array
    {
        $data = $this->employeeData();

        return $data + ['password' => (string) $this->validated('password')];
    }
}
