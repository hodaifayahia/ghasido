<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The new order of a lesson's blocks after a drag (BLD-03). Every id must
 * belong to this lesson; the service refuses a foreign one.
 */
class ReorderBlocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson && ($this->user()?->can('update', $lesson) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct', Rule::exists('blocks', 'id')],
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
