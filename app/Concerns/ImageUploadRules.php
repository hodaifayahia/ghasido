<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\File;

/**
 * One rule and one set of messages for every image upload (MED-01, SEC-04;
 * client report 2026-11-05: "some images under the limit and of an
 * allowed type were refused, I did not understand why").
 *
 * The causes, each now named in its own message: the file is not really a
 * JPG/PNG/WebP (an iPhone HEIC or an AVIF renamed .jpg is refused by its
 * content, SEC-04), it is over the app's 5 MB, or it is over the server's
 * own PHP upload limit, which can be lower than 5 MB.
 */
trait ImageUploadRules
{
    /** The app's own image limit, in kilobytes. */
    protected const int IMAGE_MAX_KB = 5 * 1024;

    protected function imageFileRule(): File
    {
        return File::types(['jpg', 'jpeg', 'png', 'webp'])->max(self::IMAGE_MAX_KB);
    }

    /**
     * @return array<string, string>
     */
    protected function imageMessages(string $field): array
    {
        return [
            $field.'.uploaded' => __('The server refused this file before it arrived: its upload limit is :limit. Make the image smaller (for example under :limit) and try again.', [
                'limit' => self::serverUploadLimit(),
            ]),
            $field.'.mimes' => __('This file is not a real JPG, PNG or WebP image, even if its name says so (iPhone HEIC and AVIF photos are often renamed). Open it and save or export it as JPG or PNG, then upload it again.'),
            $field.'.max' => __('The file is too large. Images may be up to 5 MB.'),
        ];
    }

    /**
     * The smaller of PHP's upload_max_filesize and post_max_size, as the
     * server states it (e.g. "2M").
     */
    public static function serverUploadLimit(): string
    {
        $limits = array_filter([
            (string) ini_get('upload_max_filesize'),
            (string) ini_get('post_max_size'),
        ], fn (string $value): bool => $value !== '' && $value !== '0');

        if ($limits === []) {
            return '5M';
        }

        usort($limits, fn (string $a, string $b): int => self::bytes($a) <=> self::bytes($b));

        return $limits[0].'B';
    }

    private static function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
