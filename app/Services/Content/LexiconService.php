<?php

namespace App\Services\Content;

use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use App\Jobs\GenerateLexiconDraft;
use App\Models\AudioClip;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\LexiconItem;
use App\Models\User;
use App\Services\Audio\AudioLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Words and expressions: create, edit, reuse, AI draft, audio (CMS-06,
 * CTRL-03, GEN-01, GEN-03, GEN-04, TTS-01..03, TTS-06; spec 0003 Part D).
 *
 * The AI never publishes: generate() only stores a draft on the row, and
 * applyDraft() is the admin's explicit act of copying it into the columns
 * (GEN-03). Audio is generated once per (text, voice, speed) through
 * AudioLibrary and served from the stored file afterwards (CTRL-05, TTS-02).
 */
class LexiconService
{
    public function __construct(private readonly AudioLibrary $audio) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?Block $block = null): LexiconItem
    {
        return DB::transaction(function () use ($data, $actor, $block): LexiconItem {
            $item = new LexiconItem;
            $item->fill($data);
            $item->source = LexiconItem::SOURCE_MANUAL;
            $item->show_meaning_enabled = (bool) ($data['show_meaning_enabled'] ?? true);
            $item->created_by = $actor->id;
            $item->hotel_id ??= null;
            $item->save();

            AuditLog::record($item, 'lexicon.created', ['created' => $item->only(['kind', 'english_text'])]);

            if ($block !== null) {
                $this->attach($block, $item);
            }

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LexiconItem $item, array $data): LexiconItem
    {
        return DB::transaction(function () use ($item, $data): LexiconItem {
            $item->fill($data);
            AuditLog::record($item, 'lexicon.updated');
            $item->save();

            return $item;
        });
    }

    /**
     * Append an existing item to a block (reuse, CMS-06). Idempotent.
     */
    public function attach(Block $block, LexiconItem $item): void
    {
        if ($block->lexiconItems()->whereKey($item->id)->exists()) {
            return;
        }

        /** @var int|null $max */
        $max = $block->lexiconItems()->max('block_lexicon_item.position');
        $block->lexiconItems()->attach($item->id, ['position' => ((int) $max) + 1]);
    }

    /**
     * Ask the AI for the Arabic meaning, the explanation and a hotel example.
     * The row is marked pending and the job fills `ai_draft`; the editor
     * polls until the status is terminal (GEN-01, PERF-04).
     */
    public function generate(LexiconItem $item): LexiconItem
    {
        return DB::transaction(function () use ($item): LexiconItem {
            $item->ai_status = GenerationStatus::Pending;
            $item->ai_draft = null;
            AuditLog::record($item, 'lexicon.generate_requested');
            $item->save();

            GenerateLexiconDraft::dispatch($item->id);

            return $item->refresh();
        });
    }

    /**
     * Copy the draft into the columns, or the admin's edited version of it
     * (GEN-03: reviewed and applied explicitly, never auto-published).
     *
     * @param  array<string, mixed>|null  $edited  fields the admin changed before applying
     */
    public function applyDraft(LexiconItem $item, ?array $edited = null): LexiconItem
    {
        return DB::transaction(function () use ($item, $edited): LexiconItem {
            $draft = $item->ai_draft ?? [];
            $values = array_intersect_key(
                array_merge($draft, $edited ?? []),
                array_flip(['arabic_meaning', 'simple_explanation', 'hotel_example', 'hotel_example_arabic', 'ipa', 'part_of_speech']),
            );

            foreach ($values as $key => $value) {
                if ($value !== null && $value !== '') {
                    $item->setAttribute($key, $value);
                }
            }

            $item->source = LexiconItem::SOURCE_AI;
            $item->ai_draft = null;
            $item->ai_status = null;
            AuditLog::record($item, 'lexicon.draft_applied');
            $item->save();

            return $item;
        });
    }

    /**
     * Generate (or re-queue) the normal and slow clips for the item's
     * playable texts (TTS-01, TTS-02). Returns the clips keyed by speed for
     * the English text.
     *
     * @return array{normal: AudioClip, slow: AudioClip}
     */
    public function generateAudio(LexiconItem $item): array
    {
        $clips = $this->audio->ensureBoth($item->english_text);

        if ($item->hotel_example !== null && trim($item->hotel_example) !== '') {
            $this->audio->ensureBoth($item->hotel_example);
        }

        AuditLog::record($item, 'lexicon.audio_requested', ['text' => $item->english_text]);

        return $clips;
    }

    /**
     * Search the shared catalogue (and the actor's hotel) to reuse an item
     * in another block (CMS-06).
     *
     * @return Builder<LexiconItem>
     */
    public function search(User $actor, string $term, ?string $kind = null): Builder
    {
        $query = LexiconItem::query()
            ->with('image')
            ->where(function (Builder $inner) use ($actor): void {
                $inner->whereNull('hotel_id');
                if ($actor->hotel_id !== null) {
                    $inner->orWhere('hotel_id', $actor->hotel_id);
                }
            })
            ->orderBy('english_text');

        if ($kind !== null && $kind !== '') {
            $query->where('kind', $kind);
        }

        if ($term !== '') {
            $query->where('english_text', 'like', '%'.$term.'%');
        }

        return $query;
    }

    /**
     * The audio state of every text the item plays, for the status chips
     * (TTS-03, TTS-06: a missing clip is flagged, never synthesised on play).
     *
     * @return array<string, array{normal: array{status: string, url: string|null}, slow: array{status: string, url: string|null}}>
     */
    public function audioStatus(LexiconItem $item): array
    {
        $texts = $item->playableTexts();

        if ($texts === []) {
            return [];
        }

        $hashes = array_map(fn (string $text): string => AudioClip::hashFor($text), $texts);

        $clips = AudioClip::query()
            ->with('mediaAsset')
            ->forVoice($this->audio->voice())
            ->whereIn('text_hash', $hashes)
            ->get();

        $result = [];
        foreach ($texts as $text) {
            $hash = AudioClip::hashFor($text);
            $result[$text] = [
                'normal' => ['status' => 'missing', 'url' => null],
                'slow' => ['status' => 'missing', 'url' => null],
            ];

            foreach ($clips as $clip) {
                if ($clip->text_hash !== $hash) {
                    continue;
                }
                $key = $clip->speed === AudioSpeed::Slow ? 'slow' : 'normal';
                $result[$text][$key] = ['status' => $clip->status->value, 'url' => $clip->url()];
            }
        }

        return $result;
    }
}
