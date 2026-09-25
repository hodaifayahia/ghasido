<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Admin accounts give their full contact profile (owner request
     * 2026-09-25): first and last name, email, phone and address. Everyone
     * else keeps name and email (PRIV-03: no phone or address for learners).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user('web');

        if ($user instanceof User && $user->needsAdminProfile()) {
            return [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => $this->emailRules($user->id),
                'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ().\-]{6,40}$/'],
                'address' => ['required', 'string', 'max:255'],
            ];
        }

        return $this->profileRules($user?->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a phone number with digits, spaces and an optional leading +.'),
        ];
    }
}
