<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add one reusable activity to a test (PRAC-05, TEST-05, TEST-06).
 */
class StoreTestQuestionRequest extends FormRequest
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
            'kind' => ['required', Rule::in(['multiple_choice', 'true_false', 'fill_blank', 'matching', 'short_answer', 'audio', 'image', 'video', 'speaking', 'ordering', 'writing'])],
            'text' => ['required', 'string', 'max:500'],
            'options' => ['sometimes', 'array', 'max:12'],
            'options.*.id' => ['required_with:options', 'string', 'max:20'],
            'options.*.text' => ['required_with:options', 'string', 'max:300'],
            'options.*.correct' => ['sometimes', 'boolean'],
            'options.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where('kind', 'image')],
            'options.*.audio_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where('kind', 'audio')],
            'options.*.audio_text' => ['nullable', 'string', 'max:300'],
            'pairs' => ['sometimes', 'array', 'max:12'],
            'pairs.*.left' => ['required_with:pairs', 'string', 'max:300'],
            'pairs.*.right' => ['required_with:pairs', 'string', 'max:300'],
            'accepted_answers' => ['sometimes', 'array', 'max:12'],
            'accepted_answers.*' => ['required_with:accepted_answers', 'string', 'max:300'],
            'audio_text' => ['nullable', 'string', 'max:500'],
            'request_text' => ['nullable', 'string', 'max:1000'],
            'information' => ['sometimes', 'array', 'max:12'],
            'information.*' => ['string', 'max:300'],
            'speaking_seconds' => ['nullable', 'integer', 'min:5', 'max:180'],
            'media' => ['sometimes', 'array'],
            'media.image' => ['nullable', 'integer', 'exists:media_assets,id'],
            'media.audio' => ['nullable', 'integer', 'exists:media_assets,id'],
            'media.video' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }

    /**
     * Readable names for the builder's error toast ("The answer text field
     * is required", not "options.2.text").
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'text' => __('question text'),
            'options.*.text' => __('answer text'),
            'options.*.id' => __('answer'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionData(): array
    {
        $options = array_values(array_map(
            static fn (array $option): array => [
                'id' => (string) $option['id'],
                'text' => trim((string) $option['text']),
                'correct' => (bool) ($option['correct'] ?? false),
                'image_id' => isset($option['image_id']) ? (int) $option['image_id'] : null,
                'audio_id' => isset($option['audio_id']) ? (int) $option['audio_id'] : null,
                'audio_text' => trim((string) ($option['audio_text'] ?? '')),
            ],
            $this->validated('options', []),
        ));

        return [
            'kind' => (string) $this->validated('kind'),
            'text' => trim((string) $this->validated('text')),
            'options' => $options,
            'pairs' => array_values(array_map(static fn (array $pair): array => [
                'left' => trim((string) $pair['left']),
                'right' => trim((string) $pair['right']),
            ], $this->validated('pairs', []))),
            'accepted_answers' => array_values(array_filter(array_map('trim', $this->validated('accepted_answers', [])), static fn (string $answer): bool => $answer !== '')),
            'audio_text' => trim((string) $this->validated('audio_text', '')),
            'request_text' => trim((string) $this->validated('request_text', '')),
            'information' => array_values(array_filter(array_map('trim', $this->validated('information', [])), static fn (string $line): bool => $line !== '')),
            'speaking_seconds' => (int) ($this->validated('speaking_seconds') ?? 30),
            'media' => array_map(static fn (mixed $id): ?int => is_numeric($id) && (int) $id > 0 ? (int) $id : null, $this->validated('media', [])),
        ];
    }
}
