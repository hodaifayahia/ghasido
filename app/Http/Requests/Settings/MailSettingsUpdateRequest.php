<?php

namespace App\Http\Requests\Settings;

use App\Services\Mail\MailSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Settings → Email (client request 2026-09-29): the SMTP mailbox. The From
 * address must be the mailbox that signs in, or Gmail and Outlook treat the
 * mail as spoofed; a blank password keeps the stored one.
 */
class MailSettingsUpdateRequest extends FormRequest
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
            'mailer' => ['required', 'string', Rule::in(MailSettings::MAILERS)],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'string', Rule::in(MailSettings::ENCRYPTIONS)],
            'username' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'fromAddress' => ['required', 'string', 'email', 'max:255'],
            'fromName' => ['required', 'string', 'max:100'],
            'replyTo' => ['nullable', 'string', 'email', 'max:255'],
            'sendImmediately' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'host' => __('SMTP host'),
            'port' => __('Port'),
            'username' => __('Username'),
            'password' => __('Password'),
            'fromAddress' => __('From address'),
            'fromName' => __('From name'),
            'replyTo' => __('Reply-To address'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => __('Enter a server name such as smtp.hostinger.com.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('mailer') !== 'smtp') {
                    return;
                }

                $username = trim((string) $this->input('username'));
                $from = trim((string) $this->input('fromAddress'));

                if (filter_var($username, FILTER_VALIDATE_EMAIL) && strcasecmp($username, $from) !== 0) {
                    $validator->errors()->add('fromAddress', __('Use the same address as the SMTP username (:username), or Gmail and Outlook may mark the emails as spam.', ['username' => $username]));
                }

                $settings = app(MailSettings::class);

                if (trim((string) $this->input('password')) === '' && $settings->row()?->secret() === null) {
                    $validator->errors()->add('password', __('Enter the mailbox password.'));
                }
            },
        ];
    }
}
