<?php

namespace App\Http\Requests\Admin\Scenarios;

use App\Enums\GuestMood;
use App\Enums\Permission;
use App\Enums\ScenarioDifficulty;
use App\Models\AiScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Save reviewed authoring fields without allowing a raw prompt (RP-04). */
class UpdateAiScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scenario = $this->route('scenario');

        return $scenario instanceof AiScenario
            && ($this->user()?->can(Permission::ScenariosManage->value) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'difficulty' => ['required', Rule::enum(ScenarioDifficulty::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'situation' => ['nullable', 'string', 'max:2000'],
            'ai_role' => ['nullable', 'string', 'max:2000'],
            'employee_role' => ['nullable', 'string', 'max:2000'],
            'objective' => ['nullable', 'string', 'max:500'],
            'goals' => ['sometimes', 'array', 'max:12'],
            'goals.*' => ['string', 'max:200'],
            'useful_phrases' => ['sometimes', 'array', 'max:20'],
            'useful_phrases.*' => ['string', 'max:300'],
            'attempts_allowed' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'min_turns' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'max_turns' => ['sometimes', 'integer', 'min:1', 'max:50'],
            // The guest's first line in a call (client report 2026-10-02:
            // every call opened with the same staff-like greeting).
            'opening_line' => ['nullable', 'string', 'max:200'],
            // How the guest behaves: friendly, angry, impatient… (client
            // request 2026-10-02).
            'guest_mood' => ['nullable', Rule::enum(GuestMood::class)],
            'settings' => ['sometimes', 'array'],
            'settings.attempts_allowed' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'settings.feedback_style' => ['sometimes', 'string', 'max:50'],
            'settings.focus_areas' => ['sometimes', 'array', 'max:20'],
            'settings.focus_areas.*.label' => ['required_with:settings.focus_areas', 'string', 'max:100'],
            'settings.focus_areas.*.checked' => ['required_with:settings.focus_areas', 'boolean'],
            'settings.allow_hints' => ['sometimes', 'boolean'],
            'settings.show_suggestions' => ['sometimes', 'boolean'],
            'settings.tags' => ['sometimes', 'array', 'max:30'],
            'settings.tags.*' => ['string', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function scenarioData(): array
    {
        $data = $this->validated();

        if (isset($data['difficulty'])) {
            $data['difficulty'] = ScenarioDifficulty::from((string) $data['difficulty']);
        }

        foreach (['title', 'description', 'situation', 'ai_role', 'employee_role', 'objective'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        if (array_key_exists('department_id', $data)) {
            $data['department_id'] = (int) $data['department_id'];
        }

        if (isset($data['settings']['attempts_allowed'])) {
            $data['attempts_allowed'] = (int) $data['settings']['attempts_allowed'];
            unset($data['settings']['attempts_allowed']);
        }

        // Settings are merged into what the scenario already holds, so a
        // save from one panel never drops another panel's (the voice agent
        // overrides, the feedback options).
        $scenario = $this->route('scenario');
        $current = $scenario instanceof AiScenario && is_array($scenario->settings) ? $scenario->settings : [];

        if (array_key_exists('settings', $data) || array_key_exists('opening_line', $data) || array_key_exists('guest_mood', $data)) {
            $settings = array_replace($current, is_array($data['settings'] ?? null) ? $data['settings'] : []);

            if (array_key_exists('guest_mood', $data)) {
                $settings['guest_mood'] = $data['guest_mood'] ?? GuestMood::Friendly->value;
            }

            if (array_key_exists('opening_line', $data)) {
                $voice = is_array($settings['voice_agent'] ?? null) ? $settings['voice_agent'] : [];
                $line = trim((string) $data['opening_line']);
                $voice['greeting'] = $line !== '' ? $line : null;
                $settings['voice_agent'] = $voice;
            }

            $data['settings'] = $settings;
        }

        unset($data['opening_line'], $data['guest_mood']);

        return $data;
    }
}
