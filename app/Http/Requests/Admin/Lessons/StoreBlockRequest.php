<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\BlockType;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add one block of a type to a lesson, from the palette (BLD-02, BLD-03).
 */
class StoreBlockRequest extends FormRequest
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
            'type' => ['required', Rule::enum(BlockType::class)],
            'title' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function type(): BlockType
    {
        return BlockType::from((string) $this->validated('type'));
    }

    public function title(): ?string
    {
        $title = $this->validated('title');

        return is_string($title) && trim($title) !== '' ? $title : null;
    }

    public function position(): ?int
    {
        $position = $this->validated('position');

        return is_numeric($position) ? (int) $position : null;
    }
}
