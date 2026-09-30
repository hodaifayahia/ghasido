<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Block;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new order of the activities (practice) or questions (quiz) inside a
 * block (BLD-03). Every id must be one of this block's placements; the
 * service refuses a foreign or missing one.
 */
class ReorderBlockActivitiesRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1', 'max:40'],
            'order.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function order(): array
    {
        /** @var array<int, int|string> $order */
        $order = $this->validated('order');

        return array_values(array_map('intval', $order));
    }
}
