<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Block;
use App\Models\Lesson;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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
            // A department lesson step may point at one English Essentials
            // lesson in the same hotel scope (client request 2026-10-18).
            'help_lesson_id' => ['sometimes', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->helpLessonAllowed((int) $value)) {
                    $fail(__('Choose an English Essentials lesson.'));
                }
            }],
            'help_prompt' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }

    private function helpLessonAllowed(int $lessonId): bool
    {
        $block = $this->route('block');

        if (! $block instanceof Block) {
            return false;
        }

        $from = $block->lesson()->with('course')->first();

        // No help link inside English Essentials itself.
        if ($from === null || $from->isEssentials()) {
            return false;
        }

        return Lesson::query()
            ->whereKey($lessonId)
            ->notArchived()
            ->whereHas('course', function (Builder $course) use ($from): void {
                $course->essentials()->sharedOrFor($from->hotel_id);
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function blockData(): array
    {
        return $this->validated();
    }
}
