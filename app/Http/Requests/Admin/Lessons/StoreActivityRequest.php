<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Block;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create an activity (PRAC-01..07, WRITE-05; spec 0003 B.9). The payload is
 * `{items: [...]}` in the per-type shape; the block editor builds it. With
 * `block_id` the activity is placed in that block in the same request.
 */
class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('create', Activity::class) ?? false)) {
            return false;
        }

        $blockId = $this->input('block_id');

        if ($blockId === null || $blockId === '') {
            return true;
        }

        $block = Block::query()->find((int) $blockId);

        return $block !== null && $this->user()->can('update', $block);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...self::activityRules(),
            'type' => ['required', Rule::enum(ActivityType::class)],
            'prompt' => ['required', 'string', 'max:500'],
            'payload' => ['required', 'array'],
            'block_id' => ['nullable', 'integer', Rule::exists('blocks', 'id')],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function activityRules(): array
    {
        return [
            'skill_label' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:120'],
            'prompt_arabic' => ['nullable', 'string', 'max:500'],
            'payload.items' => ['required', 'array', 'min:1', 'max:12'],
            'payload.items.*' => ['array'],
            'payload.items.*.id' => ['required', 'string', 'max:20'],
            'scoring' => ['nullable', 'array'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:3600'],
            'attempts_allowed' => ['sometimes', 'integer', 'min:0', 'max:99'],
            'show_meaning_enabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activityData(): array
    {
        $data = $this->validated();
        unset($data['block_id']);

        if (($block = $this->block()) !== null) {
            $lesson = $block->lesson()->with('course')->firstOrFail();
            // A lesson activity inherits its tenant scope from the lesson;
            // the browser cannot assign it to a different hotel/department.
            $data['department_id'] = $lesson->course?->department_id;
            $data['hotel_id'] = $lesson->hotel_id;
        }

        // The payload is authored content whose per-type item shape must round
        // trip verbatim (DATA-11); validated() would narrow items to their
        // `id` alone, so store the whole validated payload as submitted.
        if ($this->has('payload')) {
            $data['payload'] = (array) $this->input('payload');
        }

        return $data;
    }

    public function block(): ?Block
    {
        $blockId = $this->validated('block_id');

        return is_numeric($blockId) ? Block::query()->find((int) $blockId) : null;
    }
}
