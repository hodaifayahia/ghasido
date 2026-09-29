<?php

namespace App\Services\Content;

use App\Contracts\GeneratedImage;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use GdImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The image library: uploads with GD resizing and the pickable listing
 * (MED-01..03, MED-07, SEC-04, PERF-01; spec 0003 Part C).
 *
 * Files land on the public disk under content/{kind}/{yyyy}/{mm}/{uuid}.{ext}
 * with a UUID name; the original filename is metadata only. Images are
 * resized to 1600px wide at most and get a 320px `thumb` variant, so the
 * learner never downloads a phone photo at full size (PERF-01).
 */
class MediaService
{
    public const int MAX_WIDTH = 1600;

    public const int THUMB_WIDTH = 320;

    /**
     * @param  array{alt_text: string|null, library: MediaLibrary, category: string|null, label: string|null}  $data
     */
    public function upload(UploadedFile $file, array $data, User $actor): MediaAsset
    {
        $mime = $file->getMimeType() ?? $file->getClientMimeType();
        $kind = MediaKind::fromMime($mime);
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        $now = now();
        $directory = sprintf('content/%s/%s/%s', $kind->value, $now->format('Y'), $now->format('m'));
        $uuid = (string) Str::uuid();
        $path = sprintf('%s/%s.%s', $directory, $uuid, $extension);
        $disk = Storage::disk(MediaAsset::DISK_PUBLIC);

        $width = null;
        $height = null;
        $variants = null;
        $sizeBytes = $file->getSize() ?: null;

        $binary = (string) file_get_contents($file->getRealPath());

        if ($kind === MediaKind::Image) {
            $resized = $this->resize($binary, $mime, self::MAX_WIDTH);

            if ($resized !== null) {
                [$binary, $width, $height] = $resized;
                $sizeBytes = strlen($binary);
            }

            $thumb = $this->resize($binary, $mime, self::THUMB_WIDTH);

            if ($thumb !== null) {
                $thumbPath = sprintf('%s/%s-thumb.%s', $directory, $uuid, $extension);
                $disk->put($thumbPath, $thumb[0]);
                $variants = ['thumb' => $thumbPath];
            }
        }

        $disk->put($path, $binary);

        return DB::transaction(function () use ($path, $file, $mime, $kind, $data, $width, $height, $sizeBytes, $variants, $actor): MediaAsset {
            $asset = MediaAsset::query()->create([
                'disk' => MediaAsset::DISK_PUBLIC,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'kind' => $kind,
                'alt_text' => $data['alt_text'],
                'width' => $width,
                'height' => $height,
                'duration_ms' => null,
                'size_bytes' => $sizeBytes,
                'variants' => $variants,
                'uploaded_by' => $actor->id,
                'hotel_id' => $actor->hotel_id,
                'library' => $data['library'],
                'category' => $data['category'],
                'label' => $data['label'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);

            AuditLog::record($asset, 'media.uploaded', [
                'created' => ['path' => $path, 'mime' => $mime, 'size_bytes' => $sizeBytes, 'library' => $data['library']->value],
            ]);

            return $asset;
        });
    }

    /**
     * Store an AI-generated image like an upload: public disk,
     * content/images/{yyyy}/{mm}/{uuid}.{ext}, resized with a thumb variant,
     * in the "My Images" shelf so the picker offers it again (GEN-01, MED-01,
     * MED-02, PERF-01). `alt_text` is required (MED-07, ACC-05).
     */
    public function storeGenerated(GeneratedImage $image, string $altText, string $label, ?User $actor, ?int $hotelId): MediaAsset
    {
        $altText = trim($altText);

        if ($altText === '') {
            throw new InvalidArgumentException('A generated image needs alt text (MED-07).');
        }

        $now = now();
        $directory = sprintf('content/images/%s/%s', $now->format('Y'), $now->format('m'));
        $uuid = (string) Str::uuid();
        $extension = ltrim($image->extension, '.') ?: 'png';
        $path = sprintf('%s/%s.%s', $directory, $uuid, $extension);
        $disk = Storage::disk(MediaAsset::DISK_PUBLIC);

        $binary = $image->binary;
        $width = $image->width;
        $height = $image->height;
        $variants = null;

        $resized = $this->resize($binary, $image->mime, self::MAX_WIDTH);
        if ($resized !== null) {
            [$binary, $width, $height] = $resized;
        }

        $thumb = $this->resize($binary, $image->mime, self::THUMB_WIDTH);
        if ($thumb !== null) {
            $thumbPath = sprintf('%s/%s-thumb.%s', $directory, $uuid, $extension);
            $disk->put($thumbPath, $thumb[0]);
            $variants = ['thumb' => $thumbPath];
        }

        $disk->put($path, $binary);

        $asset = MediaAsset::query()->create([
            'disk' => MediaAsset::DISK_PUBLIC,
            'path' => $path,
            'original_name' => null,
            'mime' => $image->mime,
            'kind' => MediaKind::Image,
            'alt_text' => mb_substr($altText, 0, 250),
            'width' => $width,
            'height' => $height,
            'duration_ms' => null,
            'size_bytes' => strlen($binary),
            'variants' => $variants,
            'uploaded_by' => $actor?->id,
            'hotel_id' => $hotelId,
            'library' => MediaLibrary::MyImages,
            'category' => 'ai-generated',
            'label' => Str::limit($label, 80),
        ]);

        AuditLog::record($asset, 'media.generated', [
            'created' => ['path' => $path, 'mime' => $image->mime, 'size_bytes' => strlen($binary)],
        ]);

        return $asset;
    }

    /**
     * The pickable images an admin-side user may see: shared rows and their
     * own hotel's, in one library tab, optionally one category, matching a
     * search term (MED-02).
     *
     * @param  MediaLibrary|list<MediaLibrary>  $library
     * @return Builder<MediaAsset>
     */
    public function library(User $actor, MediaLibrary|array $library, ?string $category, string $search, MediaKind $kind = MediaKind::Image): Builder
    {
        $libraries = is_array($library) ? $library : [$library];

        $query = MediaAsset::query()
            ->ofKind($kind)
            ->whereIn('library', array_map(fn (MediaLibrary $item): string => $item->value, $libraries))
            ->where(function (Builder $inner) use ($actor): void {
                $inner->whereNull('hotel_id');
                if ($actor->hotel_id !== null) {
                    $inner->orWhere('hotel_id', $actor->hotel_id);
                }
            })
            ->latest('id');

        if ($category !== null && $category !== '' && $category !== 'all-categories') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('label', 'like', '%'.$search.'%')
                    ->orWhere('original_name', 'like', '%'.$search.'%')
                    ->orWhere('alt_text', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * The distinct categories of the pickable libraries, for the select.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        /** @var list<string> $categories */
        $categories = MediaAsset::query()
            ->ofKind(MediaKind::Image)
            ->whereIn('library', [MediaLibrary::MyImages, MediaLibrary::GuesviaLibrary, MediaLibrary::IconsStickers, MediaLibrary::Seed])
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        return $categories;
    }

    /**
     * The picker row for one asset.
     *
     * @return array{id: string, label: string, url: string, thumbUrl: string, alt: string, width: int|null, height: int|null, category: string|null}
     */
    public function row(MediaAsset $asset): array
    {
        return [
            'id' => (string) $asset->id,
            'label' => $asset->label ?? $asset->original_name ?? basename($asset->path),
            'kind' => $asset->kind->value,
            'url' => $asset->url(),
            'thumbUrl' => $asset->variantUrl('thumb'),
            'alt' => $asset->alt_text ?? '',
            'width' => $asset->width,
            'height' => $asset->height,
            'category' => $asset->category,
        ];
    }

    /**
     * Scale an image down to a width with GD, keeping the format. Returns
     * null when GD cannot read it (or it is already small enough for a
     * main image) so the original bytes are kept.
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function resize(string $binary, string $mime, int $maxWidth): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if (! $source instanceof GdImage) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= $maxWidth) {
            $target = $source;
            $newWidth = $width;
            $newHeight = $height;
        } else {
            $newWidth = max(1, $maxWidth);
            $newHeight = max(1, (int) round($height * $maxWidth / $width));
            $target = imagecreatetruecolor($newWidth, $newHeight);

            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
            }

            imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        }

        ob_start();
        $written = match ($mime) {
            'image/png' => imagepng($target, null, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($target, null, 82) : imagejpeg($target, null, 82),
            default => imagejpeg($target, null, 85),
        };
        $out = (string) ob_get_clean();

        if (! $written || $out === '') {
            return null;
        }

        return [$out, $newWidth, $newHeight];
    }
}
