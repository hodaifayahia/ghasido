<?php

namespace App\Http\Requests\VoiceAgent;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One caption from a live voice call (RP-11, DATA-05; spec 0004). The
 * sequence number makes the write idempotent and ordered; ownership is
 * checked by the controller.
 */
class VoiceCallTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seq' => ['required', 'integer', 'min:0', 'max:100000'],
            'role' => ['required', 'string', 'in:user,assistant,agent'],
            'content' => ['required', 'string', 'max:4000'],
        ];
    }

    public function seq(): int
    {
        return (int) $this->validated('seq');
    }

    public function role(): string
    {
        return (string) $this->validated('role');
    }

    public function content(): string
    {
        return (string) $this->validated('content');
    }
}
