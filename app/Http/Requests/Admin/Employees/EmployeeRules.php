<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\AccountStatus;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The rules an employee account is created and edited with (AUTH-01,
 * AUTH-03, ORG-03, PRIV-03; spec 0003 B.1).
 *
 * Shared by the store and update requests so the two forms can never drift:
 * a username is `[a-z0-9._-]{3,40}` and unique, an email is optional and
 * unique, and the department must be one the hotel holds seats in.
 */
trait EmployeeRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function employeeRules(?User $ignore = null): array
    {
        $hotelId = (int) $this->input('hotel_id', 0);

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique(User::class, 'username')->ignore($ignore?->id),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($ignore?->id),
            ],
            'hotel_id' => ['required', 'integer', Rule::exists(Hotel::class, 'id')],
            // Only a department this hotel bought seats in: the create form
            // lists exactly those (SUB-01, SUB-02).
            'department_id' => [
                'required',
                'integer',
                Rule::exists(Department::class, 'id'),
                Rule::exists('seat_quotas', 'department_id')->where('hotel_id', $hotelId),
            ],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'allow_reminder_emails' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function passwordRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            Password::min(8),
            'max:72',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function employeeMessages(): array
    {
        return [
            'username.regex' => __('Usernames use lowercase letters, digits, dots, dashes and underscores only.'),
            'username.unique' => __('That username is already taken.'),
            'department_id.exists' => __('This hotel has no seats in that department. Add a seat quota first.'),
        ];
    }

    /**
     * Usernames are stored lowercase (AUTH-01); an empty email is null, not
     * an empty string, so the unique index never trips on ''.
     */
    protected function normaliseEmployeeInput(): void
    {
        $username = $this->input('username');
        $email = $this->input('email');

        $this->merge([
            'username' => is_string($username) ? mb_strtolower(trim($username)) : $username,
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'allow_reminder_emails' => $this->boolean('allow_reminder_emails'),
        ]);
    }

    /**
     * @return array{name: string, username: string, email: string|null, hotel_id: int, department_id: int, status: string, allow_reminder_emails: bool, password?: string}
     */
    protected function employeeData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $shaped = [
            'name' => (string) $data['name'],
            'username' => (string) $data['username'],
            'email' => isset($data['email']) && is_string($data['email']) ? $data['email'] : null,
            'hotel_id' => (int) $data['hotel_id'],
            'department_id' => (int) $data['department_id'],
            'status' => (string) $data['status'],
            'allow_reminder_emails' => (bool) ($data['allow_reminder_emails'] ?? false),
        ];

        if (isset($data['password']) && is_string($data['password']) && $data['password'] !== '') {
            $shaped['password'] = $data['password'];
        }

        return $shaped;
    }
}
