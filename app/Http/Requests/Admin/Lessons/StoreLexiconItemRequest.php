<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\LexiconKind;
use App\Models\Block;
use App\Models\LexiconItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a word or expression by hand (CMS-06, CTRL-03). With `block_id` it
 * is appended to that block in the same request. Arabic and the explanation
 * are optional here: the AI draft may fill them later (GEN-01).
 */
class StoreLexiconItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('create', LexiconItem::class) ?? false)) {
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
            ...self::itemRules(),
            'kind' => ['required', Rule::enum(LexiconKind::class)],
            'english_text' => ['required', 'string', 'max:255'],
            'block_id' => ['nullable', 'integer', Rule::exists('blocks', 'id')],
        ];
    }

    /**
     * The fields both create and edit accept.
     *
     * @return array<string, list<mixed>>
     */
    public static function itemRules(): array
    {
        return [
            'ipa' => ['nullable', 'string', 'max:120'],
            'part_of_speech' => ['nullable', 'string', 'max:20'],
            'arabic_meaning' => ['nullable', 'string', 'max:500'],
            'simple_explanation' => ['nullable', 'string', 'max:1000'],
            'hotel_example' => ['nullable', 'string', 'max:500'],
            'hotel_example_arabic' => ['nullable', 'string', 'max:500'],
            'image_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            'show_meaning_enabled' => ['sometimes', 'boolean'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        $data = $this->validated();
        unset($data['block_id']);

        return $data;
    }

    public function block(): ?Block
    {
        $blockId = $this->validated('block_id');

        return is_numeric($blockId) ? Block::query()->find((int) $blockId) : null;
    }
}
