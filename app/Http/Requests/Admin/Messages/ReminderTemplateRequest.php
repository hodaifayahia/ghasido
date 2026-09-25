<?php

namespace App\Http\Requests\Admin\Messages;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The fields of a reminder template, shared by create and edit (REM-04).
 *
 * The body keeps whatever placeholders were typed: TemplateRenderer leaves
 * an unknown one visible rather than dropping it, so the preview is where a
 * typo shows up.
 */
abstract class ReminderTemplateRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'audience_label' => ['nullable', 'string', 'max:120'],
            'trigger_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, subject: string, body: string, audience_label: string|null, trigger_label: string|null, is_active: bool}
     */
    public function templateData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'subject' => (string) $this->validated('subject'),
            'body' => (string) $this->validated('body'),
            'audience_label' => $this->nullable('audience_label'),
            'trigger_label' => $this->nullable('trigger_label'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ];
    }

    private function nullable(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
