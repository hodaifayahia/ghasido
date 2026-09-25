<?php

namespace App\Http\Requests\Admin\Users;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Create a back-office account (AUTH-01, AUTH-03, ROLE-01, SEC-01). */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');
        $email = $this->input('email');

        $this->merge([
            'username' => is_string($username) ? mb_strtolower(trim($username)) : $username,
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'max:72'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.regex' => __('Usernames use lowercase letters, digits, dots, dashes and underscores only.'),
            'username.unique' => __('That username is already taken.'),
            'role_id.exists' => __('Choose an available app role.'),
        ];
    }

    /** @return array{name: string, username: string, email: string|null, password: string, role_id: int} */
    public function accountData(): array
    {
        /** @var array{name: string, username: string, email?: string|null, password: string, role_id: int|string} $data */
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'role_id' => (int) $data['role_id'],
        ];
    }
}
