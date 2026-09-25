<?php

namespace App\Http\Requests\Learn;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

/**
 * One practice answer (PRAC-04, TEST-06, TIME-04; spec 0003 B.9).
 *
 * `answers` is keyed by item id and shaped per activity type: an option id,
 * a prompt→target map, an ordered id list, `{recording_media_id, duration_ms}`
 * or `{text}`. The shape is validated loosely here and judged by
 * ActivityScorer; a malformed item answer is simply wrong, never a 500.
 * Authorization is the LessonPolicy check in the controller.
 */
class AnswerActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable'],
            // The hidden field the page sets when the activity opens, so
            // time taken is measured from when the learner saw it.
            'started_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function answers(): array
    {
        /** @var array<array-key, mixed> $answers */
        $answers = $this->validated('answers');

        return $answers;
    }

    public function startedAt(): ?CarbonInterface
    {
        $raw = $this->validated('started_at');

        return is_string($raw) && $raw !== '' ? Date::parse($raw) : null;
    }
}
