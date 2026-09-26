<?php

namespace App\Http\Requests\Admin\Subscriptions;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Add or edit an individual subscriber and their configuration (user request
 * 2026-09-25). Same username, email and password rules as an employee
 * (AUTH-01, PRIV-03); the department must be in the shared catalogue, since
 * an individual has no hotel of their own (ORG-04).
 */
class SaveIndividualRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only an individual subscriber is edited here; a hotel's employee
        // is not one, whatever the URL says.
        $editing = $this->editing();
        abort_if($editing !== null && ! $editing->isIndividual(), 404);

        return $this->user('web')?->can(Permission::SubscriptionsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');
        $email = $this->input('email');

        $this->merge([
            'username' => is_string($username) ? mb_strtolower(trim($username)) : $username,
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'ai_enabled' => $this->boolean('ai_enabled'),
            'voice_enabled' => $this->boolean('voice_enabled'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $ignore = $this->editing();

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique(User::class, 'username')->ignore($ignore?->id),
            ],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($ignore?->id),
            ],
            'password' => [$ignore === null ? 'required' : 'nullable', 'string', Password::min(8), 'max:72'],
            // One department or several; the first is their main one.
            'department_ids' => ['required', 'array', 'min:1', 'max:20'],
            'department_ids.*' => [
                'integer', 'distinct',
                Rule::exists(Department::class, 'id')->whereNull('hotel_id')->where('is_active', true),
            ],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'ai_points_allocated' => ['required', 'integer', 'min:0', 'max:1000000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'ai_enabled' => ['boolean'],
            'voice_enabled' => ['boolean'],
            'daily_ai_turns' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'ai_action_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'voice_points_per_10_minutes' => ['required', 'integer', 'min:0', 'max:100000'],
            'price_dzd' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('Usernames use lowercase letters, digits, dots, dashes and underscores only.'),
            'username.unique' => __('That username is already taken.'),
            'department_ids.required' => __('Choose at least one department.'),
            'department_ids.min' => __('Choose at least one department.'),
            'department_ids.*.exists' => __('Choose departments from the shared catalogue.'),
            'ends_on.after_or_equal' => __('The end date cannot be before the start date.'),
        ];
    }

    /**
     * @return array{name: string, username: string, email: string|null, password?: string, department_ids: list<int>, status: string, ai_points_allocated: int, starts_on: string|null, ends_on: string|null, ai_enabled: bool, voice_enabled: bool, daily_ai_turns: int|null, ai_action_points: int, voice_points_per_10_minutes: int, price_dzd: int|null, payment_reference: string|null, notes: string|null}
     */
    public function individualData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $text = static fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '' ? trim($data[$key]) : null;
        $number = static fn (string $key): ?int => isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;

        $shaped = [
            'name' => trim((string) $data['name']),
            'username' => (string) $data['username'],
            'email' => $text('email'),
            'department_ids' => array_values(array_map('intval', (array) $data['department_ids'])),
            'status' => (string) $data['status'],
            'ai_points_allocated' => (int) $data['ai_points_allocated'],
            'starts_on' => $text('starts_on'),
            'ends_on' => $text('ends_on'),
            'ai_enabled' => (bool) ($data['ai_enabled'] ?? false),
            'voice_enabled' => (bool) ($data['voice_enabled'] ?? false),
            'daily_ai_turns' => $number('daily_ai_turns'),
            'ai_action_points' => (int) $data['ai_action_points'],
            'voice_points_per_10_minutes' => (int) $data['voice_points_per_10_minutes'],
            'price_dzd' => $number('price_dzd'),
            'payment_reference' => $text('payment_reference'),
            'notes' => $text('notes'),
        ];

        if (isset($data['password']) && is_string($data['password']) && $data['password'] !== '') {
            $shaped['password'] = $data['password'];
        }

        return $shaped;
    }

    /** The subscriber being edited, or null when adding one. */
    private function editing(): ?User
    {
        $user = $this->route('individual');

        return $user instanceof User ? $user : null;
    }
}
