<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Contracts\ImageProvider;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Services\Learning\PayloadResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Generate image" in an editor slot (GEN-01, GEN-04, MED-07; spec 0004).
 * Without a target the image lands in the library and the slot picks it up;
 * with one it is attached to that lesson cover, lexicon item or block, which
 * the user must be allowed to edit (ROLE-02, SEC-01).
 */
class GenerateImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Lesson::class)) {
            return false;
        }

        $target = $this->targetModel();

        return $target === null || $user->can('update', $target);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:1000'],
            'alt' => ['nullable', 'string', 'max:250'],
            'size' => ['nullable', 'string', Rule::in([ImageProvider::SIZE_LANDSCAPE, ImageProvider::SIZE_SQUARE])],
            'target_type' => ['nullable', 'string', Rule::in(['lesson', 'lexicon', 'block'])],
            'target_id' => ['nullable', 'required_with:target_type', 'integer', 'min:1'],
            'target_key' => ['nullable', 'string', Rule::in(PayloadResolver::MEDIA_KEYS)],
        ];
    }

    public function targetModel(): ?Model
    {
        $id = (int) $this->input('target_id');

        return match ($this->input('target_type')) {
            'lesson' => Lesson::query()->find($id),
            'lexicon' => LexiconItem::query()->find($id),
            'block' => Block::query()->find($id),
            default => null,
        };
    }

    /**
     * The hotel the new media row belongs to: the target's, else the admin's.
     */
    public function hotelId(): ?int
    {
        $target = $this->targetModel();

        if ($target instanceof Block) {
            return $target->lesson()->value('hotel_id');
        }

        if ($target instanceof Lesson || $target instanceof LexiconItem) {
            return $target->hotel_id;
        }

        return $this->user()?->hotel_id;
    }

    /**
     * @return array{prompt: string, alt: string, size: string, target_type: string|null, target_id: int|null, target_key: string|null}
     */
    public function imageData(): array
    {
        $type = $this->validated('target_type');

        return [
            'prompt' => (string) $this->validated('prompt'),
            'alt' => (string) ($this->validated('alt') ?? ''),
            'size' => (string) ($this->validated('size') ?? ImageProvider::SIZE_LANDSCAPE),
            'target_type' => is_string($type) ? $type : null,
            'target_id' => $type === null ? null : (int) $this->validated('target_id'),
            'target_key' => is_string($this->validated('target_key')) ? (string) $this->validated('target_key') : null,
        ];
    }
}
