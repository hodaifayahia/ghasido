<?php

namespace App\Http\Requests\Admin\Scenarios;

use App\Concerns\ImageUploadRules;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Enums\Permission;
use App\Models\AiScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The scenario's cover image, saved the moment it is chosen (client report
 * 2026-11-05: "the image I uploaded is lost"). Type and size are checked by
 * MIME on the server (SEC-04, MED-01); alt text is required (MED-07,
 * ACC-05).
 */
class UpdateAiScenarioThumbnailRequest extends FormRequest
{
    use ImageUploadRules;

    public function authorize(): bool
    {
        return $this->route('scenario') instanceof AiScenario
            && ($this->user()?->can(Permission::ScenariosManage->value) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', $this->imageFileRule()],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->imageMessages('image'),
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
