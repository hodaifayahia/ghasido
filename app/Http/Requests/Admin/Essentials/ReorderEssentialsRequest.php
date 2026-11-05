<?php

namespace App\Http\Requests\Admin\Essentials;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new order for the English Essentials topics, or for one topic's
 * lessons (client request 2026-11-05). Ids outside the list being ordered
 * are ignored by the service, so only rows of that list move.
 */
class ReorderEssentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        $user = $this->user('web');

        if ($course instanceof Course) {
            return $course->isEssentials() && ($user?->can('update', $course) ?? false);
        }

        return $user?->can('create', Course::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1', 'max:500'],
            'order.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function order(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('order');

        return array_values(array_map('intval', $ids));
    }
}
