<?php

namespace App\Services\Learning;

use App\Enums\Accent;
use App\Models\MediaAsset;
use App\Services\Audio\AudioLibrary;

/**
 * Turns the ids and sentences stored in a block's settings or an activity's
 * payload into what a page can render (MED-02, CTRL-05, TTS-02; spec 0003
 * B.9, B.10).
 *
 * One recursive walk, two passes and two queries:
 *
 * - a key named image, video, poster, cover or thumbnail holding a media id
 *   becomes `{id, url, alt}` (or null when the row is gone), and so does
 *   an `audio` key holding a media id (an uploaded clip);
 * - a string under a key named `text`, or ending in `_text`, gets a sibling
 *   `<key>_audio` = `{normal, slow}` from the stored clips, both null while
 *   the clip has not been generated. Nothing is synthesised here, ever.
 *
 * Everything else passes through untouched, so a page receives the same
 * shape the CMS stored, plus what it needs to play and show.
 */
class PayloadResolver
{
    /** @var list<string> */
    public const array MEDIA_KEYS = ['image', 'video', 'poster', 'cover', 'thumbnail', 'side_image'];

    /**
     * An uploaded, recorded or library clip on an activity prompt or answer
     * (client report 2026-09-29). Resolved only when it holds a media id, so
     * any other value stored under `audio` passes through untouched.
     */
    public const string AUDIO_MEDIA_KEY = 'audio';

    /** The lesson accent whose voice the audio URLs are read for (spec 0006 §3). */
    private ?Accent $accent = null;

    public function __construct(private readonly AudioLibrary $audio) {}

    /**
     * The same resolver, reading audio in one accent's voice. A copy, so a
     * shared instance is never left pointing at another lesson's accent.
     */
    public function forAccent(?Accent $accent): self
    {
        $copy = clone $this;
        $copy->accent = $accent;

        return $copy;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function resolve(array $data): array
    {
        $mediaIds = [];
        $texts = [];

        $this->collect($data, $mediaIds, $texts);

        $media = $this->mediaById(array_values(array_unique($mediaIds)));
        $audio = $texts === [] ? [] : $this->audio->urlsFor(array_values(array_unique($texts)), $this->accent);

        return $this->transform($data, $media, $audio);
    }

    /**
     * One media asset as a page embeds it.
     *
     * @return array{id: int, url: string, alt: string|null}|null
     */
    public function media(?MediaAsset $asset): ?array
    {
        if ($asset === null) {
            return null;
        }

        return [
            'id' => $asset->id,
            'url' => $asset->url(),
            'alt' => $asset->alt_text,
        ];
    }

    /**
     * Both speeds of one sentence, from the stored clips.
     *
     * @return array{normal: string|null, slow: string|null}
     */
    public function audioFor(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return ['normal' => null, 'slow' => null];
        }

        return $this->audio->urlsFor([$text], $this->accent)[$text] ?? ['normal' => null, 'slow' => null];
    }

    /**
     * Both speeds of many sentences in one query, keyed by sentence.
     *
     * @param  iterable<string|null>  $texts
     * @return array<string, array{normal: string|null, slow: string|null}>
     */
    public function audioForMany(iterable $texts): array
    {
        $clean = [];

        foreach ($texts as $text) {
            if (is_string($text) && trim($text) !== '') {
                $clean[] = $text;
            }
        }

        return $clean === [] ? [] : $this->audio->urlsFor(array_values(array_unique($clean)), $this->accent);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<int>  $mediaIds
     * @param  list<string>  $texts
     */
    private function collect(array $data, array &$mediaIds, array &$texts): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->collect($value, $mediaIds, $texts);

                continue;
            }

            if (is_string($key) && (self::isMediaKey($key) || $key === self::AUDIO_MEDIA_KEY) && self::isId($value)) {
                $mediaIds[] = (int) $value;

                continue;
            }

            if (is_string($key) && self::isTextKey($key) && is_string($value) && trim($value) !== '') {
                $texts[] = $value;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<int, MediaAsset>  $media
     * @param  array<string, array{normal: string|null, slow: string|null}>  $audio
     * @return array<array-key, mixed>
     */
    private function transform(array $data, array $media, array $audio): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->transform($value, $media, $audio);

                continue;
            }

            if (is_string($key) && self::isMediaKey($key)) {
                $out[$key] = self::isId($value) ? $this->media($media[(int) $value] ?? null) : null;

                continue;
            }

            if ($key === self::AUDIO_MEDIA_KEY && self::isId($value)) {
                $out[$key] = $this->media($media[(int) $value] ?? null);

                continue;
            }

            $out[$key] = $value;

            if (is_string($key) && self::isTextKey($key) && is_string($value)) {
                $out[$key.'_audio'] = $audio[$value] ?? ['normal' => null, 'slow' => null];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, MediaAsset>
     */
    private function mediaById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, MediaAsset> $byId */
        $byId = MediaAsset::query()->whereIn('id', $ids)->get()->keyBy('id')->all();

        return $byId;
    }

    private static function isMediaKey(string $key): bool
    {
        return in_array($key, self::MEDIA_KEYS, true);
    }

    private static function isTextKey(string $key): bool
    {
        return $key === 'text' || str_ends_with($key, '_text');
    }

    private static function isId(mixed $value): bool
    {
        return (is_int($value) && $value > 0) || (is_string($value) && ctype_digit($value) && (int) $value > 0);
    }
}
