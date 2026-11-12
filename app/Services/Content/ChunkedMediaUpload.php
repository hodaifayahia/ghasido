<?php

namespace App\Services\Content;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Http\Requests\Admin\Lessons\StoreMediaRequest;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Joins a large audio or video upload sent in pieces (client report
 * 2026-11-12: a whole video in one request was cut by the hosting before
 * it reached the app). Pieces wait on the private disk under
 * chunks/{user}/{upload}; when the last one arrives they are joined in
 * order, the whole file is checked by the same type and size rule as a
 * normal upload (SEC-04), and stored through MediaService. Pieces of an
 * upload left unfinished for a day are removed.
 */
class ChunkedMediaUpload
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * Store one piece. Returns the new asset once every piece is in, null
     * while more are expected.
     *
     * @param  array{upload_id: string, index: int, total: int, name: string, kind: string, alt_text: string|null, library: string|null, category: string|null, label: string|null}  $data
     *
     * @throws ValidationException
     */
    public function receive(User $user, UploadedFile $chunk, array $data): ?MediaAsset
    {
        $disk = Storage::disk(MediaAsset::DISK_LOCAL);
        $directory = sprintf('chunks/%d/%s', $user->id, strtolower($data['upload_id']));

        if ($data['index'] === 0) {
            $this->forgetStale($user);
        }

        $disk->putFileAs($directory, $chunk, sprintf('%05d.part', $data['index']));

        $limitBytes = ($data['kind'] === 'audio' ? StoreMediaRequest::MAX_AUDIO_KB : StoreMediaRequest::MAX_VIDEO_KB) * 1024;
        $parts = $this->parts($directory);
        $received = array_sum(array_map(fn (string $part): int => $disk->size($part), $parts));

        if ($received > $limitBytes) {
            $disk->deleteDirectory($directory);

            throw ValidationException::withMessages([
                'file' => $data['kind'] === 'audio'
                    ? __('The file is too large. Audio may be up to 20 MB.')
                    : __('The file is too large. Videos may be up to 200 MB.'),
            ]);
        }

        if (count($parts) < $data['total']) {
            return null;
        }

        $joined = $disk->path($directory.'/joined.bin');

        try {
            $this->join($disk->path($directory), $data['total'], $joined);

            $file = new UploadedFile($joined, $data['name'], null, null, true);

            Validator::make(
                ['file' => $file],
                ['file' => ['required', 'file', StoreMediaRequest::mediaFileRule($data['kind'])]],
                [
                    'file.max' => $data['kind'] === 'audio'
                        ? __('The file is too large. Audio may be up to 20 MB.')
                        : __('The file is too large. Videos may be up to 200 MB.'),
                ],
            )->validate();

            return $this->media->upload($file, [
                'kind' => MediaKind::tryFrom($data['kind']),
                'alt_text' => $data['alt_text'],
                'library' => MediaLibrary::tryFrom((string) $data['library']) ?? MediaLibrary::MyImages,
                'category' => $data['category'],
                'label' => $data['label'],
            ], $user);
        } finally {
            $disk->deleteDirectory($directory);
        }
    }

    /**
     * @return list<string>
     */
    private function parts(string $directory): array
    {
        return array_values(array_filter(
            Storage::disk(MediaAsset::DISK_LOCAL)->files($directory),
            fn (string $file): bool => str_ends_with($file, '.part'),
        ));
    }

    /**
     * Append the pieces 0..total-1 to one file, streamed (never read into
     * memory: the hosting's PHP memory limit is small).
     */
    private function join(string $directory, int $total, string $target): void
    {
        $out = fopen($target, 'wb');

        if ($out === false) {
            throw new RuntimeException('The upload could not be joined.');
        }

        try {
            for ($index = 0; $index < $total; $index++) {
                $part = sprintf('%s/%05d.part', $directory, $index);

                if (! is_file($part)) {
                    throw ValidationException::withMessages([
                        'file' => __('A piece of the file did not arrive. Please upload it again.'),
                    ]);
                }

                $in = fopen($part, 'rb');

                if ($in === false) {
                    throw new RuntimeException('The upload could not be joined.');
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }
    }

    /** Remove this user's unfinished uploads older than a day. */
    private function forgetStale(User $user): void
    {
        $disk = Storage::disk(MediaAsset::DISK_LOCAL);

        foreach ($disk->directories('chunks/'.$user->id) as $directory) {
            if ($disk->lastModified($directory) < now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
