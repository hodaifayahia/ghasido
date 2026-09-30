<?php

namespace App\Http\Requests\Settings;

use App\Services\Mail\MailSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Settings → Email's "Send test email": the address, and the form as it is
 * on screen (saved or not), so a typed password is tested before it is
 * saved (client report 2026-09-30). Every field but `to` is optional: a
 * missing one falls back to the saved value.
 */
class MailSettingsTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-mail-settings');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'email', 'max:255'],
            'mailer' => ['sometimes', 'string', Rule::in(MailSettings::MAILERS)],
            'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['sometimes', 'string', Rule::in(MailSettings::ENCRYPTIONS)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'fromAddress' => ['nullable', 'string', 'email', 'max:255'],
            'fromName' => ['nullable', 'string', 'max:100'],
            'replyTo' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
