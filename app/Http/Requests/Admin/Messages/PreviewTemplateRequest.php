<?php

namespace App\Http\Requests\Admin\Messages;

use App\Models\ReminderTemplate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Preview a template's text for one sample employee (REM-04).
 *
 * The text comes from the request, not from a stored row, so the editor can
 * preview before saving. The sample employee is the given recipient when it
 * is one the actor may address, else the first of their employees.
 */
class PreviewTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ReminderTemplate::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'recipient' => ['nullable', 'integer'],
        ];
    }

    public function subject(): string
    {
        return (string) ($this->validated('subject') ?? '');
    }

    public function body(): string
    {
        return (string) ($this->validated('body') ?? '');
    }

    public function recipientId(): ?int
    {
        $id = $this->validated('recipient');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
