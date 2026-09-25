<?php

namespace App\Http\Requests\Learn;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Submit the sitting: save the last answer, then score everything (TEST-06,
 * TIME-01). Ownership is enforced in the controller.
 */
class FinishTestRequest extends FormRequest
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
            'number' => ['required', 'integer', 'min:1'],
            'answer' => ['nullable', 'array'],
            'time_taken_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function number(): int
    {
        return (int) $this->validated('number');
    }

    /**
     * @return array<string, mixed>
     */
    public function answer(): array
    {
        $answer = $this->validated('answer');

        return is_array($answer) ? $answer : [];
    }

    public function timeTakenMs(): ?int
    {
        $value = $this->validated('time_taken_ms');

        return is_numeric($value) ? (int) $value : null;
    }
}
