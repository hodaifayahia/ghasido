<?php

namespace App\Http\Requests\Admin\Scenarios;

use App\Enums\Permission;
use App\Enums\ScenarioDifficulty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create a draft scenario from the author's small setup form (RP-01, GEN-01). */
class StoreAiScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ScenariosManage->value) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'difficulty' => ['required', Rule::enum(ScenarioDifficulty::class)],
        ];
    }

    /** @return array{title: string, department_id: int, difficulty: ScenarioDifficulty} */
    public function scenarioData(): array
    {
        return [
            'title' => trim((string) $this->validated('title')),
            'department_id' => (int) $this->validated('department_id'),
            'difficulty' => ScenarioDifficulty::from((string) $this->validated('difficulty')),
        ];
    }
}
