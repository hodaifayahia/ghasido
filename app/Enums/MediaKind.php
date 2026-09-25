<?php

namespace App\Enums;

/**
 * What kind of file a media_assets row points at (MED-01, spec 0003 B.3).
 */
enum MediaKind: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';

    /**
     * The kind a MIME type maps to, for uploads (SEC-04).
     */
    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'audio/') => self::Audio,
            str_starts_with($mime, 'video/') => self::Video,
            default => self::Document,
        };
    }
}
