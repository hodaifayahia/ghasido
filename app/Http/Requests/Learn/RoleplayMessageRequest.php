<?php

namespace App\Http\Requests\Learn;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One employee turn in a role-play (RP-02, RP-03): typed text, an uploaded
 * voice recording, or both. Ownership and the attempt's state are enforced in
 * the controller.
 */
class RoleplayMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:2000', 'required_without:recording_media_id'],
            'recording_media_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }

    public function text(): string
    {
        $text = trim((string) $this->validated('text'));

        return $text !== '' ? $text : __('[voice message]');
    }

    public function recordingMediaId(): ?int
    {
        $value = $this->validated('recording_media_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
