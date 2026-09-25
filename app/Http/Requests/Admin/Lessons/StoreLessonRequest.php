<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a lesson under a unit the user may edit (CMS-01). It lands as a draft
 * with the default block set (LESSON-02, BLD-07) unless `blank` is set.
 */
class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = Unit::query()->find((int) $this->input('unit_id'));

        return $unit !== null
            && ($this->user()?->can('update', $unit->course()->firstOrFail()) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'title' => ['required', 'string', 'max:120'],
            'blank' => ['sometimes', 'boolean'],
        ];
    }

    public function unit(): Unit
    {
        return Unit::query()->findOrFail((int) $this->validated('unit_id'));
    }

    public function title(): string
    {
        return (string) $this->validated('title');
    }

    public function withDefaultBlocks(): bool
    {
        return ! $this->boolean('blank');
    }
}
