<?php

namespace App\Jobs;

use App\Contracts\SpeechToTextProvider;
use App\Contracts\WordTranscript;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\PronunciationAttempt;
use App\Services\Ai\UsageMeter;
use App\Services\Audio\AudioLibrary;
use App\Services\Pronunciation\PronunciationGuides;
use App\Services\Pronunciation\PronunciationKnowledge;
use App\Services\Pronunciation\PronunciationOutcome;
use App\Services\Pronunciation\PronunciationScorer;
use App\Services\Pronunciation\ReferenceText;
use App\Support\LocalMediaFile;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Check one pronunciation recording (spec 0006 §5).
 *
 * 1. free listen (no hints) → 2. align + judge → 3. only when a word could
 * still be found, a hinted second listen → 4. score v1 → 5. queue the coach
 * unless every word was right. Both listens are stored on the row as soon as
 * they exist, so a retry never pays for the same listen twice (DATA-01).
 * The guide and calibration only sharpen the verdict: without them the check
 * still runs. Every path ends in `done` or `failed` (PERF-04).
 */
class CheckPronunciation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [3, 10, 30];

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(
        SpeechToTextProvider $stt,
        PronunciationGuides $guides,
        PronunciationScorer $scorer,
        AudioLibrary $audio,
        UsageMeter $meter,
    ): void {
        $attempt = PronunciationAttempt::query()->with('recording.media', 'user', 'lesson')->find($this->attemptId);

        if ($attempt === null || $attempt->status->isTerminal()) {
            return;
        }

        $media = $attempt->recording?->media;

        if ($media === null) {
            throw new RuntimeException('The recording is missing.');
        }

        $attempt->forceFill(['status' => GenerationStatus::Running, 'failed_reason' => null])->save();

        $reference = ReferenceText::from($attempt->reference_text);
        // The voice the lesson's reference audio is rendered in.
        $audioAccent = $attempt->lesson?->accent;
        $voice = $audio->voiceFor($audioAccent);

        // "What we know": the sentence's guide (queued if new) and, once its
        // reference clip exists, how the recogniser hears it. A drill of one
        // word uses the sentence's word entries but not its calibration,
        // which is measured on the whole sentence.
        $guide = $guides->ensure($attempt->guideText(), $attempt->accent);

        if ($attempt->isWordDrill()) {
            $knowledge = PronunciationKnowledge::fromGuide($guide, $voice)->withoutCalibration();
        } else {
            $guide = $guides->calibrate($guide, $voice, $stt);
            $knowledge = PronunciationKnowledge::fromGuide($guide, $voice);
        }

        $path = LocalMediaFile::path($media);
        $mime = $media->mime ?? 'audio/webm';
        $raw = $attempt->stt_raw ?? [];

        $free = $this->listen($attempt, $raw, 'free', fn (): WordTranscript => $stt->transcribeWords($path, $mime), $meter);
        $outcome = $scorer->score($reference, $free, null, $knowledge);

        if ($outcome->needsHintedListen() && $reference->keyterms() !== []) {
            $hinted = $this->listen($attempt, $raw, 'hinted', fn (): WordTranscript => $stt->transcribeWords($path, $mime, $reference->keyterms()), $meter);
            $outcome = $scorer->score($reference, $free, $hinted, $knowledge);
        }

        $needsCoach = $outcome->heardAnything && ! $outcome->isPerfect();

        $attempt->forceFill([
            'status' => GenerationStatus::Done,
            'stt_provider' => $free->provider,
            'stt_model' => $free->model,
            'words' => $outcome->words,
            'extras' => $outcome->extras,
            'fillers' => $outcome->fillers,
            'long_pauses' => $outcome->longPauses,
            'speed_ratio' => $outcome->speedRatio,
            'score' => $outcome->score,
            'words_score' => $outcome->wordsScore,
            'clarity_score' => $outcome->clarityScore,
            'flow_score' => $outcome->flowScore,
            'level' => $outcome->level,
            'scoring_version' => $outcome->version,
            'feedback' => $needsCoach ? null : self::fixedFeedback($outcome),
            'feedback_status' => $needsCoach ? PronunciationAttempt::FEEDBACK_PENDING : PronunciationAttempt::FEEDBACK_SKIPPED,
            'checked_at' => Date::now(),
        ])->save();

        // The drill plays each weak word on its own: queue its clips now,
        // ahead of bulk lesson audio, so they are ready when it is tapped.
        foreach (array_slice($outcome->weakWords(), 0, 4) as $word) {
            $audio->ensureBoth($word['text'], accent: $audioAccent, queue: Queues::INTERACTIVE);
        }

        if ($needsCoach) {
            CoachPronunciation::dispatch($attempt->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        PronunciationAttempt::query()
            ->whereKey($this->attemptId)
            ->update([
                'status' => GenerationStatus::Failed->value,
                'failed_reason' => Str::limit($exception?->getMessage() ?? 'The recording could not be checked.', 500),
            ]);
    }

    /**
     * One listen, reused from the row when a previous try already paid for
     * it; stored and metered when new.
     *
     * @param  array<string, mixed>  $raw
     * @param  callable(): WordTranscript  $call
     */
    private function listen(PronunciationAttempt $attempt, array &$raw, string $key, callable $call, UsageMeter $meter): WordTranscript
    {
        if (is_array($raw[$key] ?? null)) {
            return WordTranscript::fromArray($raw[$key]);
        }

        $transcript = $call();
        $raw[$key] = $transcript->toArray();
        $attempt->forceFill(['stt_raw' => $raw])->save();

        // STT is billed per audio minute: the row records the call and the
        // model for the cost report, never the learner's points (AIL-04).
        $meter->record($attempt->user, AiFeature::PronunciationCheck, UsageMeter::audioUsage(
            $transcript->provider,
            $transcript->model,
            $attempt->duration_ms ?? $attempt->recording->duration_ms ?? $attempt->recording->media->duration_ms ?? null,
        ), chargePoints: false);

        return $transcript;
    }

    /**
     * The message when no coaching call is needed: every word right, or
     * nothing heard at all.
     *
     * @return array{headline: string, tips: list<array{word: string, tip: string}>, next: string, arabic: string|null}
     */
    private static function fixedFeedback(PronunciationOutcome $outcome): array
    {
        if (! $outcome->heardAnything) {
            return [
                'headline' => 'We could not hear you.',
                'tips' => [],
                'next' => 'Hold the phone a little closer, tap the microphone and say the sentence clearly.',
                'arabic' => 'لم نتمكن من سماعك. قرّب الهاتف قليلاً ثم تكلّم بوضوح.',
            ];
        }

        return [
            'headline' => $outcome->level === 'excellent' ? 'Excellent! Every word was clear.' : 'Well done! Every word was clear.',
            'tips' => [],
            'next' => ($outcome->flowScore ?? 100) < 75
                ? 'Now try to say it more smoothly, without stopping.'
                : 'Try the next sentence.',
            'arabic' => null,
        ];
    }
}
