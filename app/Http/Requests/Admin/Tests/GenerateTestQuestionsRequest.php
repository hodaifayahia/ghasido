<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\TestQuestionSkill;
use App\Services\Tests\TestQuestionGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Generate questions with AI" on the test builder (GEN-01, TEST-05,
 * TSTM-03). Authorization is the route's TestsManage permission plus the
 * controller's policy check on the test.
 */
class GenerateTestQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:1000'],
            'count' => ['required_unless:paired,true', 'nullable', 'integer', 'min:1', 'max:'.TestQuestionGenerator::MAX_QUESTIONS],
            'skills' => ['required_unless:paired,true', 'array'],
            'skills.*' => ['string', Rule::in(TestQuestionSkill::values())],
            'level' => ['required', 'string', Rule::in(['A1', 'A2', 'B1', 'B2'])],
            'paired' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{count: int, skills: list<string>, level: string, prompt: string, paired: bool}
     */
    public function generationData(): array
    {
        /** @var list<string> $skills */
        $skills = array_values(array_map('strval', (array) ($this->validated('skills') ?? [])));

        return [
            'count' => (int) ($this->validated('count') ?? 5),
            'skills' => $skills,
            'level' => (string) $this->validated('level'),
            'prompt' => trim((string) ($this->validated('prompt') ?? '')),
            'paired' => $this->boolean('paired'),
        ];
    }
}
