<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * One piece of a large audio or video upload (client report 2026-11-12:
 * a video sent in one request was cut by the hosting before it reached the
 * app). The browser sends the file in pieces of 1.5 MB (below even a 2 MB PHP upload limit); the last
 * one carries the same details, and the server joins them and checks the whole
 * file exactly like a normal upload (StoreMediaRequest, SEC-04).
 */
class StoreMediaChunkRequest extends FormRequest
{
    /** Upper bound of one piece, in kilobytes (the browser sends 1.5 MB). */
    public const int MAX_CHUNK_KB = 8 * 1024;

    /** Enough pieces for the 200 MB video limit at 1.5 MB each. */
    public const int MAX_CHUNKS = 160;

    public function authorize(): bool
    {
        return $this->user()?->can('create', MediaAsset::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'uuid'],
            'index' => ['required', 'integer', 'min:0', 'lt:total'],
            'total' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CHUNKS],
            'chunk' => ['required', 'file', 'max:'.self::MAX_CHUNK_KB],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', Rule::in(['audio', 'video'])],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'library' => ['sometimes', 'string', Rule::in(StoreMediaRequest::libraries())],
            'category' => ['nullable', 'string', 'max:60'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chunk.uploaded' => __('The server refused this file before it arrived: its upload limit is :limit.', ['limit' => StoreMediaRequest::serverUploadLimit()]),
        ];
    }

    public function chunk(): UploadedFile
    {
        /** @var UploadedFile $chunk */
        $chunk = $this->validated('chunk');

        return $chunk;
    }
}
