<?php

namespace App\Http\Requests;

use App\Concerns\HelperLanguageRequestRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Public hotel request input.
 *
 * The hotel details mirror the admin-side create form, but the contract stays
 * with the Super Admin and the manager credentials are collected here so the
 * account can be activated on approval.
 */
class HotelSignupRequest extends FormRequest
{
    use HelperLanguageRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('manager_username');
        $email = $this->input('manager_email');

        $this->merge([
            'manager_username' => is_string($username)
                ? mb_strtolower(trim($username))
                : $username,
            'manager_email' => is_string($email)
                ? mb_strtolower(trim($email))
                : $email,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'manager_name' => ['required', 'string', 'max:120'],
            'manager_email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'manager_username' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique(User::class, 'username'),
            ],
            'password' => [
                'required',
                'confirmed',
                'string',
                Password::min(8),
                'max:72',
            ],
            ...$this->helperLanguageRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_username.regex' => __('Usernames use lowercase letters, digits, dots, dashes and underscores only.'),
            'manager_username.unique' => __('That username is already taken.'),
            'manager_email.unique' => __('That email address is already in use.'),
        ];
    }

    /**
     * @return array{name: string, city: string, manager_name: string, manager_email: string}
     */
    public function hotelData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'city' => (string) $data['city'],
            'manager_name' => (string) $data['manager_name'],
            'manager_email' => (string) $data['manager_email'],
        ];
    }

    /**
     * @return array{name: string, username: string, email: string, password: string}
     */
    public function managerData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return [
            'name' => (string) $data['manager_name'],
            'username' => (string) $data['manager_username'],
            'email' => (string) $data['manager_email'],
            'password' => (string) $data['password'],
        ];
    }
}
