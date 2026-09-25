<?php

namespace App\Services\Pronunciation;

use App\Contracts\SpeechToTextProvider;
use App\Contracts\TranscribedWord;
use App\Enums\Accent;
use App\Enums\AiFeature;
use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use App\Jobs\GeneratePronunciationGuide;
use App\Models\AudioClip;
use App\Models\PronunciationGuide;
use App\Services\Ai\UsageMeter;
use App\Services\Audio\AudioLibrary;
use App\Support\LocalMediaFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "What we know" about a text in an accent (spec 0006 §4): the guide row,
 * its Qwen draft and its calibration on our own reference audio.
 *
 * `ensure()` is what content writes and the first check call: it finds or
 * creates the row and queues the draft once. `calibrate()` runs inside the
 * check job, once per text + voice, and never fails the check it serves.
 */
class PronunciationGuides
{
    public function __construct(
        private readonly AudioLibrary $audio,
        private readonly WordAligner $aligner,
        private readonly UsageMeter $meter,
    ) {}

    /**
     * The guide row for this text and accent, queueing its draft when it is
     * new or failed before. An admin-edited guide is left alone.
     */
    public function ensure(string $text, Accent $accent): PronunciationGuide
    {
        $normalised = AudioClip::normalise($text);

        $guide = PronunciationGuide::query()->firstOrCreate(
            ['text_hash' => AudioClip::hashFor($normalised), 'accent' => $accent],
            ['text' => $normalised, 'status' => GenerationStatus::Pending, 'source' => PronunciationGuide::SOURCE_AI],
        );

        if ($guide->wasRecentlyCreated) {
            GeneratePronunciationGuide::dispatch($guide->id);
        } elseif ($guide->status === GenerationStatus::Failed && $guide->source === PronunciationGuide::SOURCE_AI) {
            $guide->forceFill(['status' => GenerationStatus::Pending, 'failed_reason' => null])->save();
            GeneratePronunciationGuide::dispatch($guide->id);
        }

        return $guide;
    }

    /**
     * Guides for many texts of one accent, queued where missing.
     *
     * @param  iterable<string>  $texts
     */
    public function ensureMany(iterable $texts, Accent $accent): int
    {
        $count = 0;

        foreach ($texts as $text) {
            if (trim($text) !== '') {
                $this->ensure($text, $accent);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Measure how the recogniser hears our own normal-speed reference clip
     * in this voice, word by word (spec 0006 §2 findings 3 and 4), and keep
     * it on the guide. Returns the guide unchanged when it is already
     * calibrated for this voice or the clip does not exist yet; a provider
     * failure is logged and the check goes on without calibration.
     */
    public function calibrate(PronunciationGuide $guide, string $voice, SpeechToTextProvider $stt): PronunciationGuide
    {
        if ($guide->isCalibratedFor($voice)) {
            return $guide;
        }

        $clip = $this->audio->doneClipInVoice($guide->text, AudioSpeed::Normal, $voice);

        if ($clip === null || $clip->mediaAsset === null) {
            return $guide;
        }

        try {
            $transcript = $stt->transcribeWords(LocalMediaFile::path($clip->mediaAsset), $clip->mediaAsset->mime ?? 'audio/mpeg');
        } catch (Throwable $e) {
            Log::warning('Pronunciation calibration failed', ['guide' => $guide->id, 'error' => $e->getMessage()]);

            return $guide;
        }

        $this->meter->record(null, AiFeature::PronunciationCheck, UsageMeter::audioUsage($transcript->provider, $transcript->model, $clip->mediaAsset->duration_ms), chargePoints: false);

        $reference = ReferenceText::from($guide->text);
        $keys = $reference->keys();
        $heard = [];

        foreach ($transcript->words as $word) {
            $key = SpokenWords::key($word->word);

            if ($key !== '' && ! SpokenWords::isFiller($key)) {
                $heard[] = ['key' => $key, 'word' => $word];
            }
        }

        $words = array_map(
            static fn (array $token): array => ['key' => $token['key'], 'confidence' => null, 'heard' => false],
            $reference->tokens,
        );

        $ops = $this->aligner->align(
            $keys,
            array_column($heard, 'key'),
            fn (int $index, string $heardKey): bool => SpokenWords::same($keys[$index], $heardKey),
        );

        foreach ($ops as $op) {
            if ($op['op'] === WordAligner::MATCH && $op['ref'] !== null && $op['heard'] !== null) {
                $words[$op['ref']]['confidence'] = $heard[$op['heard']]['word']->confidence;
                $words[$op['ref']]['heard'] = true;
            }
        }

        $timed = array_values(array_filter(
            $transcript->words,
            static fn (TranscribedWord $word): bool => $word->startMs !== null && $word->endMs !== null,
        ));

        $duration = $timed === [] ? null : (int) end($timed)->endMs - (int) $timed[0]->startMs;

        $guide->forceFill([
            'calibration' => [
                'voice' => $voice,
                'words' => $words,
                'duration_ms' => $duration,
                'provider' => $transcript->provider,
                'model' => $transcript->model,
                'measured_at' => Date::now()->toIso8601String(),
            ],
        ])->save();

        return $guide;
    }
}
