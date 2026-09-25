<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename or (un)publish a unit; authorized through its course (CMS-01).
 */
class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit
            && ($this->user()?->can('update', $unit->course()->firstOrFail()) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function unitData(): array
    {
        return $this->validated();
    }
}
