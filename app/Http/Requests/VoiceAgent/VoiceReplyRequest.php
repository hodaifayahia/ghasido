<?php

namespace App\Http\Requests\VoiceAgent;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One finished sentence of the employee in a fast-engine call (RP-03,
 * RP-11; spec 0009). `turn` is Flux's turn index and `rev` counts the
 * requests made for that turn, so a speculative reply overtaken by the
 * confirmed one can never overwrite it. Ownership is checked by the
 * controller.
 */
class VoiceReplyRequest extends FormRequest
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
            'turn' => ['required', 'integer', 'min:0', 'max:10000'],
            'rev' => ['required', 'integer', 'min:0', 'max:10000'],
            'text' => ['required', 'string', 'max:2000'],
        ];
    }

    public function turn(): int
    {
        return (int) $this->validated('turn');
    }

    public function rev(): int
    {
        return (int) $this->validated('rev');
    }

    public function text(): string
    {
        return (string) $this->validated('text');
    }
}
