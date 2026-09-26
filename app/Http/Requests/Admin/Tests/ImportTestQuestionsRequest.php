<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Import questions into a test from a CSV file or pasted rows (TEST-05,
 * TSTM-01; client request 2026-09-26). Checked by type and size on the
 * server (SEC-04).
 */
class ImportTestQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof Test && ($this->user()?->can(Permission::TestsManage->value) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'required_without:rows', 'file', 'mimes:csv,txt', 'max:2048'],
            'rows' => ['nullable', 'required_without:file', 'string', 'max:200000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => __('Choose a CSV file or paste your questions.'),
            'rows.required_without' => __('Choose a CSV file or paste your questions.'),
            'file.mimes' => __('The file must be a CSV file (.csv). In Excel use File → Save As → CSV.'),
        ];
    }

    public function content(): string
    {
        $file = $this->file('file');

        if ($file instanceof UploadedFile) {
            return (string) file_get_contents($file->getRealPath());
        }

        return (string) $this->validated('rows', '');
    }
}
