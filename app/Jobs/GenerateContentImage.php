<?php

namespace App\Jobs;

use App\Contracts\ImageProvider;
use App\Enums\AiFeature;
use App\Models\Block;
use App\Models\ContentGeneration;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Services\Ai\UsageMeter;
use App\Services\Content\LessonGenerator;
use App\Services\Content\MediaService;
use App\Services\Learning\PayloadResolver;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generate one image and put it where it belongs (GEN-01, GEN-04, MED-01,
 * MED-07; spec 0004): a lesson cover, a lexicon item's picture, a block's
 * image setting, or — for an editor slot — just the generation row, which
 * the slot then picks up.
 *
 * The image is stored as a media_assets row on the public disk with its alt
 * text (MED-07) and metered in ai_usages (AIL-04). Unique per target, so a
 * double click never bills two pictures for one slot.
 */
class GenerateContentImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; the provider polls a task for up to four minutes. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    /**
     * @param  array{type?: string, id?: int, key?: string, prompt?: string, alt?: string, size?: string}|null  $target  null = read it from the generation row
     */
    public function __construct(
        public readonly int $generationId,
        public readonly ?array $target = null,
    ) {
        $this->onQueue(Queues::MEDIA);
    }

    public function uniqueId(): string
    {
        $target = $this->target ?? [];

        return sprintf('%d:%s:%d', $this->generationId, $target['type'] ?? 'slot', $target['id'] ?? 0);
    }

    public function handle(ImageProvider $images, UsageMeter $meter, MediaService $media, LessonGenerator $generator): void
    {
        $generation = ContentGeneration::query()->find($this->generationId);

        if ($generation === null) {
            return;
        }

        $target = $this->target ?? $generation->target ?? [];
        $prompt = trim((string) ($target['prompt'] ?? $generation->prompt));
        $alt = trim((string) ($target['alt'] ?? ''));
        $size = ($target['size'] ?? '') === ImageProvider::SIZE_SQUARE ? ImageProvider::SIZE_SQUARE : ImageProvider::SIZE_LANDSCAPE;

        if (! $this->targetExists($target)) {
            $generator->imageFinished($generation->id, false, __('The item this image was for no longer exists.'));

            return;
        }

        if ($generation->started_at === null) {
            $generation->forceFill(['started_at' => now()])->save();
        }

        $image = $images->generate($prompt, $size);
        $meter->record($generation->user, AiFeature::ImageGenerate, $image->usage);

        $asset = $media->storeGenerated(
            $image,
            $alt !== '' ? $alt : $prompt,
            $alt !== '' ? $alt : $prompt,
            $generation->user,
            $generation->hotel_id,
        );

        $this->attach($target, $asset, $generation);

        $generator->imageFinished($generation->id, true);
    }

    /**
     * @param  array{type?: string, id?: int, key?: string}  $target
     */
    private function targetExists(array $target): bool
    {
        $id = (int) ($target['id'] ?? 0);

        return match ($target['type'] ?? 'none') {
            'lesson' => Lesson::query()->whereKey($id)->exists(),
            'lexicon' => LexiconItem::query()->whereKey($id)->exists(),
            'block' => Block::query()->whereKey($id)->exists(),
            default => true,
        };
    }

    /**
     * @param  array{type?: string, id?: int, key?: string}  $target
     */
    private function attach(array $target, MediaAsset $asset, ContentGeneration $generation): void
    {
        $id = (int) ($target['id'] ?? 0);

        match ($target['type'] ?? 'none') {
            'lesson' => Lesson::query()->whereKey($id)->update(['cover_media_id' => $asset->id]),
            'lexicon' => LexiconItem::query()->whereKey($id)->update(['image_media_id' => $asset->id]),
            'block' => $this->attachToBlock($id, (string) ($target['key'] ?? 'image'), $asset),
            default => null,
        };

        $generation->forceFill(['media_asset_id' => $asset->id])->save();
    }

    private function attachToBlock(int $blockId, string $key, MediaAsset $asset): void
    {
        $block = Block::query()->find($blockId);

        if ($block === null || ! in_array($key, PayloadResolver::MEDIA_KEYS, true)) {
            return;
        }

        $settings = $block->settings ?? [];
        $settings[$key] = $asset->id;
        $block->settings = $settings;
        $block->save();
    }

    public function failed(?Throwable $exception): void
    {
        app(LessonGenerator::class)->imageFinished(
            $this->generationId,
            false,
            $exception?->getMessage() ?? __('Image generation failed.'),
        );
    }
}
