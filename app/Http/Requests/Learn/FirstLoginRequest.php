<?php

namespace App\Http\Requests\Learn;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The first-login screen (AUTH-04, AUTH-05, AUTH-06, PRIV-01, PRIV-02;
 * spec 0003 Part E).
 *
 * Email is required only when the hotel says so (`settings.require_email`);
 * the research notice must be acknowledged; reminder consent is a choice.
 */
class FirstLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole(Role::Employee->value);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'email' => [
                $this->emailRequired($user) ? 'required' : 'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user->id),
            ],
            'reminder_consent' => ['nullable', 'boolean'],
            'research_notice_acknowledged' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'research_notice_acknowledged.accepted' => __('Please confirm you have read the notice to continue.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => is_string($email) && trim($email) !== '' ? strtolower(trim($email)) : null,
            'reminder_consent' => $this->boolean('reminder_consent'),
        ]);
    }

    /**
     * Does this employee's hotel make an email address mandatory (AUTH-05)?
     */
    public function emailRequired(User $user): bool
    {
        $settings = $user->hotel()->first()->settings ?? [];

        return (bool) ($settings['require_email'] ?? false);
    }
}
