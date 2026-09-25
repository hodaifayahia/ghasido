<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\LexiconItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a word or expression (CMS-06, CTRL-03). Applying an AI draft goes
 * through lexicon.apply-draft instead, so the audit trail tells the two
 * apart (GEN-03).
 */
class UpdateLexiconItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof LexiconItem && ($this->user()?->can('update', $item) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...StoreLexiconItemRequest::itemRules(),
            'english_text' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        return $this->validated();
    }
}
