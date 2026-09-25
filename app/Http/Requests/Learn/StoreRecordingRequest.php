<?php

namespace App\Http\Requests\Learn;

use App\Models\Block;
use App\Models\TestAttempt;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A voice recording upload (TEST-07, DATA-02, RESP-05, SEC-04; spec 0003
 * Part C, Part E).
 *
 * Type and size are checked on the server, never trusted from the browser:
 * the mobile recorders produce webm, m4a, ogg or wav, and 20 MB covers the
 * longest capped answer. What it was recorded for arrives as a morph pair.
 */
class StoreRecordingRequest extends FormRequest
{
    /**
     * A lesson step (Listen & Repeat, a practice speaking item) or the
     * learner's open test sitting (a speaking question, TEST-07).
     *
     * @var array<string, class-string<Model>>
     */
    public const array RECORDABLES = [
        'block' => Block::class,
        'test_attempt' => TestAttempt::class,
    ];

    /**
     * What iOS Safari, Android Chrome and desktop MediaRecorder produce for
     * webm, wav, mp3, m4a and ogg, as their content is detected server side.
     *
     * @var list<string>
     */
    public const array MIME_TYPES = [
        'audio/webm',
        'video/webm',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/vnd.wave',
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/aac',
        'audio/ogg',
        'video/ogg',
        'application/ogg',
    ];

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
            // Checked by detected MIME type, not the browser's extension: the
            // `mimes:` rule maps audio/mpeg to "mpga" and audio/webm to
            // "weba", which would refuse every real mp3 and webm upload.
            'audio' => [
                'required',
                'file',
                'max:'.(20 * 1024),
                'mimetypes:'.implode(',', self::MIME_TYPES),
            ],
            'recordable_type' => ['required', 'string', Rule::in(array_keys(self::RECORDABLES))],
            // The recording must hang off something this learner may reach:
            // a step of a lesson they can open, or their own open sitting
            // (ROLE-02, TEST-07; spec 0005 §1.6).
            'recordable_id' => [
                'required',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->recordableIsReachable((int) $value)) {
                        $fail(__('This recording has nowhere to go.'));
                    }
                },
            ],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ];
    }

    public function audio(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('audio');

        return $file;
    }

    /**
     * @return class-string<Model>
     */
    public function recordableClass(): string
    {
        /** @var string $type */
        $type = $this->validated('recordable_type');

        return self::RECORDABLES[$type];
    }

    private function recordableIsReachable(int $id): bool
    {
        $user = $this->user();
        $type = $this->input('recordable_type');

        if (! $user instanceof User) {
            return false;
        }

        return match ($type) {
            'block' => Block::query()
                ->whereKey($id)
                ->where('is_visible', true)
                ->whereHas('lesson', fn (Builder $lesson) => $lesson->forLearner($user))
                ->exists(),
            'test_attempt' => TestAttempt::query()
                ->whereKey($id)
                ->where('user_id', $user->id)
                ->whereNull('submitted_at')
                ->exists(),
            default => false,
        };
    }

    public function recordableId(): int
    {
        return (int) $this->validated('recordable_id');
    }

    public function durationMs(): ?int
    {
        $duration = $this->validated('duration_ms');

        return is_numeric($duration) ? (int) $duration : null;
    }
}
