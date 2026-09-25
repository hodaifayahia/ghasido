<?php

namespace App\Http\Requests\Admin\Users;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Update an app account without deleting its audit or learning history. */
class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', Password::min(8), 'max:72'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'status' => ['required', Rule::enum(AccountStatus::class)],
        ];
    }

    /** @return array{name: string, username: string, email: string|null, password: string|null, role_id: int, status: string} */
    public function accountChanges(): array
    {
        /** @var array{name: string, username: string, email?: string|null, password?: string|null, role_id: int|string, status: string} $data */
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?: null,
            'role_id' => (int) $data['role_id'],
            'status' => $data['status'],
        ];
    }
}
