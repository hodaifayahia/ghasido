<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit an activity. A payload or scoring change writes a new version; old
 * attempts keep pointing at the version they answered (DATA-11, TEST-09).
 */
class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity && ($this->user()?->can('update', $activity) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = StoreActivityRequest::activityRules();
        $rules['payload'] = ['sometimes', 'array'];
        $rules['payload.items'] = ['required_with:payload', 'array', 'min:1', 'max:12'];
        $rules['prompt'] = ['sometimes', 'required', 'string', 'max:500'];

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function activityData(): array
    {
        $data = $this->validated();

        // Keep the full authored payload; validated() would drop every item
        // key except `id` (DATA-11, TEST-09).
        if ($this->has('payload')) {
            $data['payload'] = (array) $this->input('payload');
        }

        return $data;
    }
}
