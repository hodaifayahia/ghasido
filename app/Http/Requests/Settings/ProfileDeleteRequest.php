<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Only the Super Admin may remove their own account (DATA-10, PROG-06;
     * security audit 2026-11-08): a learner or manager deleting themselves
     * used to cascade-delete every answer, transcript and recording.
     * Employees and managers are removed by the Super Admin, who
     * anonymises them and keeps their data.
     */
    public function authorize(): bool
    {
        return $this->user('web')?->hasRole(Role::SuperAdmin->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
