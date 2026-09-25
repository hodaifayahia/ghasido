<?php

namespace App\Http\Requests\Learn;

use App\Models\LexiconItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Save to My Phrasebook (PHRASE-02, PHRASE-03; spec 0003 Part E).
 *
 * Either a lexicon item by id, or a custom phrase as `text` (+ optional
 * `arabic`) from a dialogue line or an expression that is not a lexicon row.
 */
class StorePhrasebookItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'lexicon_item_id' => [
                'required_without:text',
                'nullable',
                'integer',
                Rule::exists(LexiconItem::class, 'id'),
            ],
            'text' => ['required_without:lexicon_item_id', 'nullable', 'string', 'max:500'],
            'arabic' => ['nullable', 'string', 'max:500'],
            'source_lesson_id' => ['nullable', 'integer'],
        ];
    }

    public function lexiconItemId(): ?int
    {
        $id = $this->validated('lexicon_item_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function text(): ?string
    {
        $text = $this->validated('text');

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    public function arabic(): ?string
    {
        $arabic = $this->validated('arabic');

        return is_string($arabic) && trim($arabic) !== '' ? trim($arabic) : null;
    }

    public function sourceLessonId(): ?int
    {
        $id = $this->validated('source_lesson_id');

        return is_numeric($id) ? (int) $id : null;
    }
}
