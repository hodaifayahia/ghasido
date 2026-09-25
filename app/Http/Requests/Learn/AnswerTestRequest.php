<?php

namespace App\Http\Requests\Learn;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Save the answer to one test question and move on (TEST-06, DATA-01,
 * DATA-08). The answer is stored verbatim, so it is only shape-validated;
 * the sitting's ownership is enforced in the controller.
 */
class AnswerTestRequest extends FormRequest
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
            'answer' => ['nullable', 'array'],
            'to' => ['required', 'integer', 'min:1'],
            'time_taken_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function answer(): array
    {
        $answer = $this->validated('answer');

        return is_array($answer) ? $answer : [];
    }

    /** The 1-based question to open after saving. */
    public function target(): int
    {
        return (int) $this->validated('to');
    }

    public function timeTakenMs(): ?int
    {
        $value = $this->validated('time_taken_ms');

        return is_numeric($value) ? (int) $value : null;
    }
}
