<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * A readable local path for a stored file, for the providers that read the
 * audio from disk (speech-to-text). A local disk has one already; any other
 * driver is copied to a temporary file first.
 */
final class LocalMediaFile
{
    public static function path(MediaAsset $media): string
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            throw new RuntimeException('The audio file is missing from storage.');
        }

        try {
            $path = $disk->path($media->path);

            if (is_readable($path)) {
                return $path;
            }
        } catch (Throwable) {
            // Not a local driver; fall through to a temporary copy.
        }

        $temporary = tempnam(sys_get_temp_dir(), 'rec');

        if ($temporary === false || file_put_contents($temporary, (string) $disk->get($media->path)) === false) {
            throw new RuntimeException('The audio file could not be copied for transcription.');
        }

        return $temporary;
    }
}
