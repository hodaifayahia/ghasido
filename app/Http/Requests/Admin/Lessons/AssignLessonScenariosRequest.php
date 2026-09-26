<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The lesson's "AI Role-play" tab: the full, ordered list of scenarios the
 * lesson offers (RP-01). BlockService::syncScenarios still checks the
 * department and hotel scope of every id (ROLE-02).
 */
class AssignLessonScenariosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson && ($this->user('web')?->can('update', $lesson) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'scenario_ids' => ['present', 'array', 'max:40'],
            'scenario_ids.*' => ['integer', 'distinct', Rule::exists('ai_scenarios', 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function scenarioIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('scenario_ids', []);

        return array_values(array_map('intval', $ids));
    }
}
