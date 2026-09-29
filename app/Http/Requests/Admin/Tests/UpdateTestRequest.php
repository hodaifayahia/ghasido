<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
use App\Enums\ResultsVisibility;
use App\Enums\TestType;
use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Save the editable assessment settings (TEST-04, TSTM-02).
 */
class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof Test && ($this->user()?->can(Permission::TestsManage->value) ?? false);
    }

    protected function prepareForValidation(): void
    {
        // The builder's "All departments" option.
        if (in_array($this->input('department_id'), ['all', ''], true)) {
            $this->merge(['department_id' => null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(TestType::class)],
            // Empty = all departments (client request 2026-09-29).
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')],
            'description' => ['nullable', 'string', 'max:300'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'question_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'shuffle_options' => ['sometimes', 'boolean'],
            'single_attempt' => ['sometimes', 'boolean'],
            'results_visibility' => ['required', Rule::enum(ResultsVisibility::class)],
            'show_answers' => ['sometimes', 'boolean'],
            'motivational_message' => ['sometimes', 'boolean'],
            'show_meaning' => ['sometimes', 'boolean'],
            'pass_mark' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function testData(): array
    {
        $validated = $this->validated();
        $minutes = $validated['time_limit_minutes'] ?? null;

        $validated['type'] = TestType::from((string) $validated['type']);
        $validated['department_id'] = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $validated['hotel_id'] = $this->filled('hotel_id') ? (int) $validated['hotel_id'] : null;
        $validated['title'] = trim((string) $validated['title']);
        $validated['time_limit_seconds'] = $minutes === null || (int) $minutes === 0 ? null : (int) $minutes * 60;
        $validated['intro'] = [
            'description' => trim((string) ($validated['description'] ?? '')),
        ];

        $validated['settings'] = [
            'time_limit_seconds' => $validated['time_limit_seconds'],
            'shuffle_questions' => (bool) ($validated['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($validated['shuffle_options'] ?? false),
            'single_attempt' => (bool) ($validated['single_attempt'] ?? false),
            'results_visibility' => (string) $validated['results_visibility'],
            'show_answers' => (bool) ($validated['show_answers'] ?? false),
            'motivational_message' => (bool) ($validated['motivational_message'] ?? false),
            // Show Meaning on questions (client decision 2026-09-26): on
            // unless the admin switches it off for this test.
            'show_meaning' => (bool) ($validated['show_meaning'] ?? true),
            'pass_score' => ($validated['pass_mark'] ?? null) === null ? null : (float) $validated['pass_mark'],
            // Other stored rules (e.g. `on_timeout`) are kept: TestService
            // merges these over the test's existing settings.
        ];

        unset(
            $validated['description'],
            $validated['time_limit_minutes'],
            $validated['question_count'],
            $validated['shuffle_questions'],
            $validated['shuffle_options'],
            $validated['single_attempt'],
            $validated['results_visibility'],
            $validated['show_answers'],
            $validated['motivational_message'],
            $validated['show_meaning'],
            $validated['pass_mark'],
            $validated['time_limit_seconds'],
        );

        return $validated;
    }
}
