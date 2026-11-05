<?php

namespace App\Http\Requests\Admin\Scenarios;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Enums\Permission;
use App\Models\AiScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * The scenario's cover image, saved the moment it is chosen (client report
 * 2026-11-05: "the image I uploaded is lost"). Type and size are checked by
 * MIME on the server (SEC-04, MED-01); alt text is required (MED-07,
 * ACC-05).
 */
class UpdateAiScenarioThumbnailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('scenario') instanceof AiScenario
            && ($this->user()?->can(Permission::ScenariosManage->value) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.max' => __('The file is too large. Images may be up to 5 MB.'),
            'alt_text.required' => __('Describe the image for screen readers.'),
        ];
    }

    public function imageFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->validated('image');

        return $file;
    }

    /**
     * @return array{alt_text: string, library: MediaLibrary, category: string, label: string|null, kind: MediaKind}
     */
    public function mediaData(): array
    {
        $scenario = $this->route('scenario');

        return [
            'kind' => MediaKind::Image,
            'alt_text' => trim((string) $this->validated('alt_text')),
            'library' => MediaLibrary::MyImages,
            'category' => 'scenarios',
            'label' => $scenario instanceof AiScenario ? $scenario->title : null,
        ];
    }
}
