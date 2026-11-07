<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\Lesson;
use App\Services\Content\LessonGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Fill this lesson with AI" (client request 2026-11-07): a detailed brief
 * for one existing lesson the admin may edit (LessonPolicy::update,
 * SEC-01), with the same length as the course generator's prompt.
 */
class FillLessonWithAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson && ($this->user('web')?->can('update', $lesson) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:5', 'max:'.StoreLessonGenerationRequest::MAX_PROMPT],
            'level' => ['nullable', 'string', Rule::in(LessonGenerator::LEVELS)],
            'images' => ['sometimes', 'boolean'],
            'audio' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{prompt: string, level: string, images: bool, audio: bool}
     */
    public function generationData(Lesson $lesson): array
    {
        $level = $this->validated('level');
        $courseLevel = $lesson->course()->value('level');

        return [
            'prompt' => (string) $this->validated('prompt'),
            'level' => is_string($level) && $level !== '' ? $level : (is_string($courseLevel) && $courseLevel !== '' ? $courseLevel : 'beginner'),
            'images' => $this->boolean('images', true),
            'audio' => $this->boolean('audio', true),
        ];
    }
}
