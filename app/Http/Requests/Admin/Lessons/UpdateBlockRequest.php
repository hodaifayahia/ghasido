<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Block;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Save a block editor (BLD-03; spec 0003 B.10). `settings` is the block's
 * json contract for its type; the lists attach shared rows (lexicon items,
 * activities, scenarios) in the given order.
 */
class UpdateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $block = $this->route('block');

        return $block instanceof Block && ($this->user()?->can('update', $block) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'layout' => ['sometimes', 'string', Rule::in(['full', 'side', 'stack'])],
            'settings' => ['sometimes', 'array'],
            'lexicon_item_ids' => ['sometimes', 'array', 'max:60'],
            'lexicon_item_ids.*' => ['integer', Rule::exists('lexicon_items', 'id')],
            'activity_ids' => ['sometimes', 'array', 'max:40'],
            'activity_ids.*' => ['integer', Rule::exists('activities', 'id')],
            'scenario_ids' => ['sometimes', 'array', 'max:40'],
            'scenario_ids.*' => ['integer', 'distinct', Rule::exists('ai_scenarios', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blockData(): array
    {
        return $this->validated();
    }
}
