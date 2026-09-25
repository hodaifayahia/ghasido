<?php

namespace App\Http\Requests\Learn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * One recording to check for pronunciation (spec 0006 §5, §7; SEC-04).
 *
 * The audio is checked by detected MIME type and size, like every voice
 * recording. What was to be said is only an address — the sentence index or
 * lexicon item of the step, and optionally one word of it — resolved on the
 * server; the browser never sends the text itself.
 */
class StorePronunciationCheckRequest extends FormRequest
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
        $maxSeconds = (int) config('guesvia.pronunciation.max_recording_seconds', 30);

        return [
            'audio' => [
                'required',
                'file',
                'max:'.(20 * 1024),
                'mimetypes:'.implode(',', StoreRecordingRequest::MIME_TYPES),
            ],
            'item' => ['required', 'integer', 'min:0'],
            'word' => ['nullable', 'integer', 'min:0', 'max:200'],
            // A little over the cap: the recorder stops on its own at it.
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:'.(($maxSeconds + 2) * 1000)],
        ];
    }

    public function audio(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('audio');

        return $file;
    }

    public function item(): int
    {
        return (int) $this->validated('item');
    }

    public function word(): ?int
    {
        $word = $this->validated('word');

        return is_numeric($word) ? (int) $word : null;
    }

    public function durationMs(): ?int
    {
        $duration = $this->validated('duration_ms');

        return is_numeric($duration) ? (int) $duration : null;
    }
}
