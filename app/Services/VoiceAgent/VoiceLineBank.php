<?php

namespace App\Services\VoiceAgent;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Jobs\RecordVoiceLine;
use App\Jobs\WarmVoiceLines;
use App\Models\AiScenario;
use App\Models\AudioClip;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VoiceLine;
use App\Services\Ai\UsageMeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The fast engine's stored guest voice (spec 0009): every line the AI guest
 * says is voiced once and kept, and the AI is offered the reusable lines of
 * the scenario so it can answer with a recording instead of a new line —
 * instant for the learner and no speech bill for the Super Admin (TTS-02,
 * AIL-04, API-03).
 *
 * The audio is an ordinary `audio_clips` row keyed by text and voice, so the
 * same sentence is one file however many scenarios use it, and the admin
 * audio tools can replace it (TTS-03).
 */
final class VoiceLineBank
{
    /** How many recorded lines the AI is offered on one turn. */
    public const CANDIDATES = 24;

    /**
     * Lines a guest needs in every scenario, recorded ahead of the first
     * call that could use them.
     */
    public const GENERIC_LINES = [
        'Sorry, could you say that again, please?',
        'Sorry, I did not catch that. Could you say it more slowly, please?',
        'Okay, thank you.',
        'Yes, please.',
        'No, thank you.',
        'Perfect, thank you very much.',
        'Thank you for your help. Goodbye!',
    ];

    public function __construct(
        private readonly VoiceLineSpeech $speech,
        private readonly UsageMeter $meter,
    ) {}

    /**
     * The recorded lines the AI may reuse in this scenario: its own lines
     * first by how often they were spoken, then the generic ones.
     *
     * @return Collection<int, VoiceLine>
     */
    public function candidates(AiScenario $scenario, string $voice): Collection
    {
        return VoiceLine::query()
            ->with('audioClip.mediaAsset')
            ->where('voice', $voice)
            ->where('reusable', true)
            ->where(fn (Builder $query) => $query->where('ai_scenario_id', $scenario->id)->orWhereNull('ai_scenario_id'))
            ->whereHas('audioClip', fn (Builder $clip) => $clip->done()->activeProvider($this->speech->provider()))
            ->orderByRaw('case when ai_scenario_id is null then 1 else 0 end')
            ->orderByDesc('uses')
            ->orderByDesc('last_used_at')
            ->limit(self::CANDIDATES)
            ->get();
    }

    /**
     * The stored line for this text. When it has no recording yet, one is
     * queued and the URL is null: the browser streams the line from
     * Deepgram meanwhile (first sound in about 0.2 s, where waiting for the
     * whole file took 2.4 s), and the next call plays the recording.
     */
    public function lineFor(?AiScenario $scenario, string $voice, string $text, bool $reusable, ?User $user = null): VoiceLine
    {
        $normalised = AudioClip::normalise($text);

        $line = VoiceLine::query()->createOrFirst(
            [
                'ai_scenario_id' => $scenario?->id,
                'voice' => $voice,
                'text_hash' => AudioClip::hashFor($normalised),
            ],
            [
                'text' => $normalised,
                'reusable' => $reusable,
                'uses' => 0,
                'reuses' => 0,
            ],
        );

        // Whether a line is offered for reuse is decided once, when it is
        // first said: a line first judged personal (a name, a number the
        // employee gave) never enters the bank later.
        $clip = AudioClip::query()->createOrFirst(
            ['text_hash' => AudioClip::hashFor($normalised), 'voice' => $voice, 'speed' => AudioSpeed::Normal],
            ['text' => $normalised, 'status' => GenerationStatus::Pending],
        );

        if ($line->audio_clip_id !== $clip->id) {
            $line->forceFill(['audio_clip_id' => $clip->id])->save();
        }

        if (! $this->playable($clip)) {
            $this->recordLater($clip, $user);
            // A synchronous queue (tests, a local worker-less setup) has
            // recorded it already.
            $clip->refresh();
        }

        return $line->setRelation('audioClip', $clip->loadMissing('mediaAsset'));
    }

    /**
     * Count one spoken use; `reused` when the AI picked it from the bank
     * instead of writing a new line.
     */
    public function markUsed(VoiceLine $line, bool $reused): void
    {
        $line->forceFill([
            'uses' => $line->uses + 1,
            'reuses' => $line->reuses + ($reused ? 1 : 0),
            'last_used_at' => Date::now(),
        ])->save();
    }

    /**
     * The greeting's recording: recorded after the first call that needs it
     * (which streams it) and played from storage by every call after.
     */
    public function greetingUrl(AiScenario $scenario, string $voice, string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $line = $this->lineFor($scenario, $voice, $text, false);
        $this->markUsed($line, $line->uses > 0);

        return $line->url();
    }

    /**
     * Record the generic lines in this voice in the background, once.
     */
    public function warmLater(string $voice): void
    {
        $recorded = VoiceLine::query()
            ->whereNull('ai_scenario_id')
            ->where('voice', $voice)
            ->whereHas('audioClip', fn (Builder $clip) => $clip->done()->activeProvider($this->speech->provider()))
            ->count();

        if ($recorded < count(self::GENERIC_LINES)) {
            WarmVoiceLines::dispatch($voice);
        }
    }

    /**
     * Record every generic line in this voice (WarmVoiceLines).
     */
    public function warm(string $voice): void
    {
        foreach (self::GENERIC_LINES as $text) {
            $exists = VoiceLine::query()
                ->whereNull('ai_scenario_id')
                ->where('voice', $voice)
                ->where('text_hash', AudioClip::hashFor($text))
                ->first();

            // Generic lines have no scenario, and a unique index does not
            // stop two NULL rows: look first, then create or re-record.
            $exists === null
                ? $this->lineFor(null, $voice, $text, true)
                : $this->lineFor(null, $voice, $exists->text, $exists->reusable);
        }
    }

    /**
     * Voice one clip in its voice now and store it (RecordVoiceLine). False
     * when voicing failed; the clip stays pending so the next use queues it
     * again.
     */
    public function record(AudioClip $clip, ?User $user = null): bool
    {
        if ($this->playable($clip)) {
            return true;
        }

        $provider = $this->speech->provider();

        try {
            $audio = $this->speech->synthesise($clip->text, $clip->voice);
        } catch (Throwable $e) {
            Log::warning('Voice line could not be recorded', ['clip' => $clip->id, 'voice' => $clip->voice, 'error' => $e->getMessage()]);

            return false;
        }

        $now = Date::now();
        $path = sprintf('content/audio/%s/%s/%s.%s', $now->format('Y'), $now->format('m'), (string) Str::uuid(), ltrim($audio->extension, '.'));

        Storage::disk(MediaAsset::DISK_PUBLIC)->put($path, $audio->binary);

        $asset = MediaAsset::query()->create([
            'disk' => MediaAsset::DISK_PUBLIC,
            'path' => $path,
            'original_name' => null,
            'mime' => $audio->mime,
            'kind' => MediaKind::Audio,
            'alt_text' => null,
            'duration_ms' => $audio->durationMs,
            'size_bytes' => strlen($audio->binary),
            'uploaded_by' => null,
            'hotel_id' => null,
            'library' => MediaLibrary::Generated,
            'category' => 'voice_line',
            'label' => Str::limit($clip->text, 80),
        ]);

        // Speech is metered in characters, billed to the Deepgram account
        // (spec 0005 §4.3, spec 0007).
        $this->meter->record($user, AiFeature::Tts, new AiUsageInfo(
            promptTokens: mb_strlen($clip->text),
            completionTokens: 0,
            model: $clip->voice,
            provider: $provider,
        ), chargePoints: false);

        $clip->markDone($asset, $provider);

        return true;
    }

    /**
     * What the bank has saved so far, for the voice call settings.
     *
     * @return array{lines: int, reusable: int, reuses: int, charactersSaved: int}
     */
    public function stats(): array
    {
        $row = VoiceLine::query()
            ->toBase()
            ->selectRaw('count(*) as lines_count, sum(case when reusable then 1 else 0 end) as reusable_count, coalesce(sum(reuses), 0) as reuses_count, coalesce(sum(reuses * length(text)), 0) as saved_count')
            ->first();

        return [
            'lines' => (int) ($row->lines_count ?? 0),
            'reusable' => (int) ($row->reusable_count ?? 0),
            'reuses' => (int) ($row->reuses_count ?? 0),
            'charactersSaved' => (int) ($row->saved_count ?? 0),
        ];
    }

    /**
     * A recording of the active speech provider (or an admin upload) that
     * can be played now.
     */
    private function playable(AudioClip $clip): bool
    {
        return $clip->isDone() && in_array($clip->provider, [$this->speech->provider(), 'upload'], true);
    }

    /**
     * Queue the recording. A failure to queue (or, on a synchronous queue,
     * to record) is logged, never thrown: the guest's reply is already on
     * its way to the learner (RP-03).
     */
    private function recordLater(AudioClip $clip, ?User $user): void
    {
        try {
            RecordVoiceLine::dispatch($clip->id, $user?->id);
        } catch (Throwable $e) {
            Log::warning('Voice line recording was not queued', ['clip' => $clip->id, 'error' => $e->getMessage()]);
        }
    }
}
