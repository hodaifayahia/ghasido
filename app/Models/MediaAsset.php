<?php

namespace App\Models;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Policies\MediaAssetPolicy;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A stored file: lesson image, generated audio, video, or a learner's
 * recording (MED-01, MED-02, DATA-02, PRIV-04, spec 0003 B.3).
 *
 * `disk` decides how it is served. Public files get a plain storage URL that
 * browsers may cache; local files only ever reach a client through
 * `media.show`, which asks MediaAssetPolicy first. Nothing here builds a
 * `/storage/...` string by hand.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string $mime
 * @property MediaKind $kind
 * @property string|null $alt_text
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_ms
 * @property int|null $size_bytes
 * @property array<string, string>|null $variants
 * @property int|null $uploaded_by
 * @property int|null $hotel_id
 * @property MediaLibrary $library
 * @property string|null $category
 * @property string|null $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'disk',
    'path',
    'original_name',
    'mime',
    'kind',
    'alt_text',
    'width',
    'height',
    'duration_ms',
    'size_bytes',
    'variants',
    'uploaded_by',
    'hotel_id',
    'library',
    'category',
    'label',
])]
#[UsePolicy(MediaAssetPolicy::class)]
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    public const string DISK_PUBLIC = 'public';

    public const string DISK_LOCAL = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'library' => MediaLibrary::class,
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'size_bytes' => 'integer',
            'variants' => 'array',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return HasMany<AudioClip, $this> */
    public function audioClips(): HasMany
    {
        return $this->hasMany(AudioClip::class);
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  Builder<MediaAsset>  $query
     */
    public function scopeInLibrary(Builder $query, MediaLibrary $library): void
    {
        $query->where('library', $library);
    }

    /**
     * @param  Builder<MediaAsset>  $query
     */
    public function scopeOfKind(Builder $query, MediaKind $kind): void
    {
        $query->where('kind', $kind);
    }

    // ------------------------------------------------------------------- urls

    /**
     * The URL a page may embed (spec 0003 B.3).
     *
     * Public disk: the storage URL, cacheable. Anything else: the authorized
     * serve route, so a learner recording never becomes a guessable link
     * (PRIV-04, SEC-04).
     */
    public function url(): string
    {
        if ($this->isPublic()) {
            return $this->publicUrl($this->path);
        }

        return route('media.show', $this);
    }

    /**
     * The URL of a named derivative (`thumb`, `card`, `cover`), falling back
     * to the original when it has not been generated (MED-04, PERF-01).
     */
    public function variantUrl(string $name): string
    {
        $path = $this->variants[$name] ?? null;

        if ($path === null || ! $this->isPublic()) {
            return $this->url();
        }

        return $this->publicUrl($path);
    }

    /**
     * Prefer the cacheable storage URL when the public link exists, but keep
     * seed assets and fresh local uploads usable when `storage:link` has not
     * been run yet (MED-01/02; ACC-05).
     */
    private function publicUrl(string $path): string
    {
        $storageUrl = Storage::disk(self::DISK_PUBLIC)->url($path);

        if (is_file(public_path('storage/'.$path))) {
            return $storageUrl;
        }

        // Seed crops also ship in public/content/seed, so they can be
        // displayed even on an existing database created before the seeder
        // copied them to storage/app/public.
        if (is_file(public_path($path))) {
            return asset($path);
        }

        // A valid file on disk still needs a response route if the symbolic
        // link is missing. This makes an upload immediately pickable instead
        // of returning a broken image URL.
        if (Storage::disk(self::DISK_PUBLIC)->exists($path)) {
            return route('media.show', $this);
        }

        // Keep model/unit expectations stable for a not-yet-materialised
        // public asset (and for factories that only exercise URL shaping).
        return $storageUrl;
    }

    public function isPublic(): bool
    {
        return $this->disk === self::DISK_PUBLIC;
    }

    public function isImage(): bool
    {
        return $this->kind === MediaKind::Image;
    }

    public function isAudio(): bool
    {
        return $this->kind === MediaKind::Audio;
    }

    public function isVideo(): bool
    {
        return $this->kind === MediaKind::Video;
    }

    /**
     * The absolute path on disk, for jobs that read the bytes (STT, export).
     */
    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }
}
