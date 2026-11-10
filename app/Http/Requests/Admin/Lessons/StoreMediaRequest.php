<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Concerns\ImageUploadRules;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Upload an image, audio or video file into the library (MED-01, MED-02,
 * MED-07, SEC-04; spec 0003 Part C). Type and size are checked by MIME on
 * the server, never by extension alone; alt text is required on every
 * image (ACC-05).
 */
class StoreMediaRequest extends FormRequest
{
    use ImageUploadRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', MediaAsset::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $kind = (string) $this->input('kind', 'image');

        $file = match ($kind) {
            // webm / mp4 / ogg: a clip recorded in the browser (Chrome,
            // Safari, Firefox).
            'audio' => File::types(['mp3', 'wav', 'm4a', 'webm', 'mp4', 'ogg', 'oga'])->max(20 * 1024),
            // mov / m4v: an MP4 that AI video tools (Runway, Google Flow)
            // write with a QuickTime or M4V brand reads as that type
            // (client report 2026-11-10); it is stored and served as MP4.
            'video' => File::types(['mp4', 'webm', 'mov', 'm4v'])->max(200 * 1024),
            default => $this->imageFileRule(),
        };

        return [
            'kind' => ['sometimes', 'string', Rule::in(['image', 'audio', 'video'])],
            'file' => ['required', 'file', $file],
            'alt_text' => [$kind === 'image' ? 'required' : 'nullable', 'string', 'max:255'],
            'library' => ['sometimes', 'string', Rule::in([
                MediaLibrary::MyImages->value,
                MediaLibrary::GuesviaLibrary->value,
                MediaLibrary::IconsStickers->value,
            ])],
            'category' => ['nullable', 'string', 'max:60'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $kind = (string) $this->input('kind', 'image');

        return [
            ...($kind === 'image' ? $this->imageMessages('file') : [
                'file.uploaded' => __('The server refused this file before it arrived: its upload limit is :limit.', ['limit' => self::serverUploadLimit()]),
                'file.max' => $kind === 'audio'
                    ? __('The file is too large. Audio may be up to 20 MB.')
                    : __('The file is too large. Videos may be up to 200 MB.'),
            ]),
            'alt_text.required' => __('Describe the image for screen readers.'),
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->validated('file');

        return $file;
    }

    /**
     * @return array{alt_text: string|null, library: MediaLibrary, category: string|null, label: string|null, kind: MediaKind|null}
     */
    public function mediaData(): array
    {
        $library = $this->validated('library');
        $kind = $this->validated('kind');

        return [
            'kind' => is_string($kind) ? MediaKind::tryFrom($kind) : null,
            'alt_text' => is_string($this->validated('alt_text')) ? $this->validated('alt_text') : null,
            'library' => is_string($library) ? MediaLibrary::from($library) : MediaLibrary::MyImages,
            'category' => is_string($this->validated('category')) ? $this->validated('category') : null,
            'label' => is_string($this->validated('label')) ? $this->validated('label') : null,
        ];
    }
}
