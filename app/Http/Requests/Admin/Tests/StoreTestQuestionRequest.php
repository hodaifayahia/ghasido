<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\ActivityType;
use App\Enums\Permission;
use App\Models\ActivityPlacement;
use App\Models\Test;
use App\Services\Content\ActivityPayloadValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add one reusable activity to a test (PRAC-05, TEST-05, TEST-06).
 *
 * Two shapes are accepted. The shared activity editor sends the activity
 * itself: `type` (one of the client's ten, client report 2026-09-29),
 * `prompt` and a one-item `payload` checked by ActivityPayloadValidator
 * exactly like a lesson activity. The older short form (`kind`, `text`,
 * `options`) is still read for callers that send it.
 */
class StoreTestQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof Test && ($this->user()?->can(Permission::TestsManage->value) ?? false);
    }

    /**
     * Does the request carry an activity from the shared editor?
     */
    public function isActivity(): bool
    {
        return $this->has('payload');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        if ($this->isActivity()) {
            return [
                'type' => [$this->typeIsFixed() ? 'prohibited' : 'required', Rule::enum(ActivityType::class)->only(ActivityType::builderTypes())],
                'title' => ['nullable', 'string', 'max:120'],
                'prompt' => ['required', 'string', 'max:500'],
                'payload' => ['required', 'array'],
                // A test question is one item (spec 0003 B.9).
                'payload.items' => ['required', 'array', 'size:1'],
                'payload.items.*' => ['array'],
                'payload.items.*.id' => ['required', 'string', 'max:20'],
            ];
        }

        return [
            'kind' => ['required', 'string', 'max:40'],
            'text' => ['required', 'string', 'max:500'],
            'options' => ['sometimes', 'array', 'max:12'],
            'options.*.id' => ['required_with:options', 'string', 'max:20'],
            'options.*.text' => ['required_with:options', 'string', 'max:300'],
            'options.*.correct' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The payload must be answerable and scorable for its type.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->activityType();
                $payload = $this->input('payload');

                if (! $this->isActivity() || $type === null || ! is_array($payload) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (ActivityPayloadValidator::errors($type, $payload) as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
        ];
    }

    /**
     * The type being written: the posted one for a new question, the
     * stored one for an edit (the type never changes, DATA-11).
     */
    public function activityType(): ?ActivityType
    {
        $placement = $this->route('placement');

        if ($placement instanceof ActivityPlacement) {
            return $placement->activity?->type;
        }

        return ActivityType::tryFrom((string) $this->input('type'));
    }

    /**
     * @return array{type: ActivityType, prompt: string, title: string|null, payload: array<string, mixed>}
     */
    public function activityData(): array
    {
        $type = $this->activityType() ?? ActivityType::MultipleChoice;
        $title = $this->validated('title');

        /** @var array<string, mixed> $payload */
        $payload = (array) $this->input('payload');

        return [
            'type' => $type,
            'prompt' => trim((string) $this->validated('prompt')),
            'title' => is_string($title) ? $title : null,
            // Authored content round-trips verbatim; validated() would keep
            // only each item's id (DATA-11).
            'payload' => ['items' => array_values((array) ($payload['items'] ?? []))],
        ];
    }

    /**
     * @return array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}
     */
    public function questionData(): array
    {
        $options = array_values(array_map(
            static fn (array $option): array => [
                'id' => (string) $option['id'],
                'text' => trim((string) $option['text']),
                'correct' => (bool) ($option['correct'] ?? false),
            ],
            $this->validated('options', []),
        ));

        return [
            'kind' => (string) $this->validated('kind'),
            'text' => trim((string) $this->validated('text')),
            'options' => $options,
        ];
    }

    protected function typeIsFixed(): bool
    {
        return false;
    }
}
