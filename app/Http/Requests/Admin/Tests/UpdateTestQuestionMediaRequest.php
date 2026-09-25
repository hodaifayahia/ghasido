<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Attach or remove question media without exposing answer data (MED-01..03,
 * TEST-05, TEST-09).
 */
class UpdateTestQuestionMediaRequest extends FormRequest
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
            'kind' => ['required', Rule::in(['image', 'audio', 'video'])],
            'media_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }

    /**
     * @return array{kind: string, media_id: int|null}
     */
    public function mediaData(): array
    {
        return [
            'kind' => (string) $this->validated('kind'),
            'media_id' => $this->filled('media_id') ? (int) $this->validated('media_id') : null,
        ];
    }
}
