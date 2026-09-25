<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add one reusable activity to a test (PRAC-05, TEST-05, TEST-06).
 */
class StoreTestQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof Test && ($this->user()?->can(Permission::TestsManage->value) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'max:40'],
            'text' => ['required', 'string', 'max:500'],
            'options' => ['sometimes', 'array', 'max:12'],
            'options.*.id' => ['required_with:options', 'string', 'max:20'],
            'options.*.text' => ['required_with:options', 'string', 'max:300'],
            'options.*.correct' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}
     */
    public function questionData(): array
    {
        $options = array_values(array_map(
            static fn (array $option): array => [
                'id' => (string) $option['id'],
                'text' => trim((string) $option['text']),
                'correct' => (bool) ($option['correct'] ?? false),
            ],
            $this->validated('options', []),
        ));

        return [
            'kind' => (string) $this->validated('kind'),
            'text' => trim((string) $this->validated('text')),
            'options' => $options,
        ];
    }
}
