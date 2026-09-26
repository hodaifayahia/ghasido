<?php

namespace App\Http\Requests\Learn;

use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
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
        $user = $this->user('web');
        $hotelId = $user?->hotel_id;

        return [
            // Only a shared item or one of the learner's own hotel: another
            // hotel's vocabulary (and its Arabic) never reaches this
            // learner's phrasebook (ROLE-02, CMS-04).
            'lexicon_item_id' => [
                'required_without:text',
                'nullable',
                'integer',
                Rule::exists(LexiconItem::class, 'id')->where(
                    fn (Builder $query) => $query->whereNull('hotel_id')->orWhere('hotel_id', $hotelId),
                ),
            ],
            'text' => ['required_without:lexicon_item_id', 'nullable', 'string', 'max:500'],
            'arabic' => ['nullable', 'string', 'max:500'],
            'source_lesson_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    $visible = $user instanceof User && is_numeric($value)
                        && Lesson::query()->whereKey((int) $value)->forLearner($user)->exists();

                    if (! $visible) {
                        $fail(__('That lesson is not available.'));
                    }
                },
            ],
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
