<?php

namespace App\Services\Content;

use App\Enums\AudioSpeed;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Services\Audio\AudioLibrary;
use App\Services\Audio\PlayableTextCollector;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shapes a lesson's blocks for the "Lesson Blocks" list and the block
 * editors (BLD-03; spec 0003 B.10). Raw settings ride along untouched so an
 * editor posts back exactly the contract it received; every media id in
 * them is resolved once into a `media` map for the slots (MED-02), and every
 * lexicon item carries the state of its audio clips (TTS-03, TTS-06).
 */
class BlockShaper
{
    public function __construct(
        private readonly AudioLibrary $audio,
        private readonly PlayableTextCollector $playable,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forLesson(Lesson $lesson): array
    {
        /** @var Collection<int, Block> $blocks */
        $blocks = $lesson->blocks()
            ->with(['lexiconItems.image', 'placements.activity', 'scenarios'])
            ->get();

        $mediaIds = [];
        $texts = [];

        foreach ($blocks as $block) {
            $this->collectMediaIds($block->settings ?? [], $mediaIds);

            $texts = [...$texts, ...$this->playable->forBlock($block)];

            foreach ($block->lexiconItems as $item) {
                if ($item->image_media_id !== null) {
                    $mediaIds[] = $item->image_media_id;
                }
            }

            foreach ($this->activityMediaIds($block) as $id) {
                $mediaIds[] = $id;
            }
        }

        $media = $this->mediaMap(array_values(array_unique($mediaIds)));
        $audio = $this->audioMap(array_values(array_unique($texts)));

        return array_values($blocks->map(fn (Block $block): array => $this->row($block, $media, $audio))->all());
    }

    /**
     * @param  array<int, array{id: int, url: string, thumbUrl: string, alt: string, label: string, kind: string}>  $media
     * @param  array<string, array{normal: array{status: string, url: string|null}, slow: array{status: string, url: string|null}}>  $audio
     * @return array<string, mixed>
     */
    private function row(Block $block, array $media, array $audio): array
    {
        $type = $block->type;

        return [
            'id' => $block->id,
            'type' => $type->value,
            'label' => $block->heading(),
            'stepLabel' => $type->stepLabel(),
            'description' => $type->description(),
            'tone' => $type->tone(),
            'icon' => $type->icon(),
            'position' => $block->position,
            'isVisible' => $block->is_visible,
            'title' => $block->title,
            'layout' => $block->layout,
            'settings' => $block->settings ?? [],
            'media' => array_intersect_key($media, array_flip($this->blockMediaIds($block))),
            'audio' => $audio,
            'lexiconItems' => $block->lexiconItems->map(fn (LexiconItem $item): array => $this->lexiconRow($item, $audio))->values()->all(),
            'activities' => $block->placements->map(fn (ActivityPlacement $placement): ?array => $placement->activity === null ? null : $this->activityRow($placement->activity, $placement))->filter()->values()->all(),
            'scenarioIds' => $block->scenarioIds(),
        ];
    }

    /**
     * @param  array<string, array{normal: array{status: string, url: string|null}, slow: array{status: string, url: string|null}}>  $audio
     * @return array<string, mixed>
     */
    public function lexiconRow(LexiconItem $item, array $audio = []): array
    {
        $clips = [];
        $missing = false;

        foreach ($item->playableTexts() as $text) {
            $state = $audio[$text] ?? [
                'normal' => ['status' => 'missing', 'url' => null],
                'slow' => ['status' => 'missing', 'url' => null],
            ];
            $clips[$text] = $state;

            if ($state['normal']['status'] !== 'done' || $state['slow']['status'] !== 'done') {
                $missing = true;
            }
        }

        return [
            'id' => $item->id,
            'kind' => $item->kind->value,
            'englishText' => $item->english_text,
            'ipa' => $item->ipa,
            'partOfSpeech' => $item->part_of_speech,
            'arabicMeaning' => $item->arabic_meaning,
            'simpleExplanation' => $item->simple_explanation,
            'hotelExample' => $item->hotel_example,
            'hotelExampleArabic' => $item->hotel_example_arabic,
            'imageId' => $item->image_media_id,
            'imageUrl' => $item->image?->variantUrl('thumb'),
            'showMeaningEnabled' => $item->show_meaning_enabled,
            'source' => $item->source,
            'aiStatus' => $item->ai_status?->value,
            'aiDraft' => $item->ai_draft,
            'audio' => $clips,
            'missingAudio' => $missing,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activityRow(Activity $activity, ?ActivityPlacement $placement = null): array
    {
        return [
            'id' => $activity->id,
            'placementId' => $placement?->id,
            'type' => $activity->type->value,
            'label' => $activity->type->label(),
            'skillLabel' => $activity->skill_label,
            'title' => $activity->title,
            'prompt' => $activity->prompt,
            'promptArabic' => $activity->prompt_arabic,
            'payload' => $activity->payload,
            'attemptsAllowed' => $activity->attempts_allowed,
            'timeLimitSeconds' => $activity->time_limit_seconds,
            'showMeaningEnabled' => $activity->show_meaning_enabled,
            'status' => $activity->status->value,
            'version' => $activity->current_version,
            'itemCount' => $activity->itemCount(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{id: int, url: string, thumbUrl: string, alt: string, label: string, kind: string}>
     */
    public function mediaMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $map = [];

        foreach (MediaAsset::query()->whereIn('id', $ids)->get() as $asset) {
            $map[$asset->id] = [
                'id' => $asset->id,
                'url' => $asset->url(),
                'thumbUrl' => $asset->variantUrl('thumb'),
                'alt' => $asset->alt_text ?? '',
                'label' => $asset->label ?? $asset->original_name ?? basename($asset->path),
                'kind' => $asset->kind->value,
            ];
        }

        return $map;
    }

    /**
     * One query for every clip of every text (CTRL-05).
     *
     * @param  list<string>  $texts
     * @return array<string, array{normal: array{status: string, url: string|null}, slow: array{status: string, url: string|null}}>
     */
    public function audioMap(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $byHash = [];
        foreach ($texts as $text) {
            $byHash[AudioClip::hashFor($text)] = $text;
        }

        $normal = [];
        $slow = [];

        $clips = AudioClip::query()
            ->with('mediaAsset')
            ->forVoice($this->audio->voice())
            ->whereIn('text_hash', array_keys($byHash))
            ->get();

        foreach ($clips as $clip) {
            $state = ['status' => $clip->status->value, 'url' => $clip->url()];

            if ($clip->speed === AudioSpeed::Slow) {
                $slow[$clip->text_hash] = $state;
            } else {
                $normal[$clip->text_hash] = $state;
            }
        }

        $missing = ['status' => 'missing', 'url' => null];
        $result = [];

        foreach ($byHash as $hash => $text) {
            $result[$text] = [
                'normal' => $normal[$hash] ?? $missing,
                'slow' => $slow[$hash] ?? $missing,
            ];
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function blockMediaIds(Block $block): array
    {
        $ids = [];
        $this->collectMediaIds($block->settings ?? [], $ids);

        foreach ($block->lexiconItems as $item) {
            if ($item->image_media_id !== null) {
                $ids[] = $item->image_media_id;
            }
        }

        foreach ($this->activityMediaIds($block) as $id) {
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function activityMediaIds(Block $block): array
    {
        $ids = [];

        foreach ($block->placements as $placement) {
            if ($placement->activity !== null) {
                $this->collectMediaIds($placement->activity->payload ?? [], $ids);
            }
        }

        return $ids;
    }

    /**
     * Media references in a settings or payload contract are integers under
     * the keys image / video / poster, at any depth (spec 0003 B.9, B.10).
     *
     * @param  array<mixed>  $node
     * @param  list<int>  $ids
     */
    private function collectMediaIds(array $node, array &$ids): void
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->collectMediaIds($value, $ids);

                continue;
            }

            if (in_array($key, ['image', 'video', 'poster'], true) && is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }
    }
}
