<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\AudioClip;
use App\Models\PronunciationAttempt;
use App\Models\PronunciationGuide;
use App\Services\Ai\UsageMeter;
use App\Services\Pronunciation\PronunciationKnowledge;
use App\Services\Pronunciation\PronunciationOutcome;
use App\Services\Pronunciation\ReferenceText;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * The coach's words on one checked recording (spec 0006 §5): the LLM reads
 * the structured result — never the audio — plus the guide entries of the
 * weak words, and writes praise, up to two tips and the next step.
 *
 * It never changes a score (AIE-04). A coaching failure never fails the
 * check: the page shows the word verdicts without a tip (PERF-04).
 */
class CoachPronunciation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $attempt = PronunciationAttempt::query()->with('user')->find($this->attemptId);

        if (
            $attempt === null
            || $attempt->status !== GenerationStatus::Done
            || $attempt->feedback_status !== PronunciationAttempt::FEEDBACK_PENDING
        ) {
            return;
        }

        $words = array_column(ReferenceText::from($attempt->reference_text)->tokens, 'display');

        $coaching = $ai->coachPronunciation(
            self::context($attempt),
            $words,
            $attempt->accent,
            $attempt->user?->english_level,
        );

        $meter->record($attempt->user, AiFeature::PronunciationCoach, $coaching->usage, chargePoints: false);

        $attempt->forceFill([
            'feedback' => $coaching->toArray(),
            'feedback_status' => PronunciationAttempt::FEEDBACK_DONE,
            'coached_at' => Date::now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        PronunciationAttempt::query()
            ->whereKey($this->attemptId)
            ->where('feedback_status', PronunciationAttempt::FEEDBACK_PENDING)
            ->update(['feedback_status' => PronunciationAttempt::FEEDBACK_FAILED]);
    }

    /**
     * What the coach is given: the verdict per word, the scores, the
     * hesitations, and what the guide says about the weak words.
     *
     * @return array<string, mixed>
     */
    public static function context(PronunciationAttempt $attempt): array
    {
        $guide = PronunciationGuide::query()
            ->where('text_hash', AudioClip::hashFor($attempt->guideText()))
            ->where('accent', $attempt->accent->value)
            ->first();
        $knowledge = PronunciationKnowledge::fromGuide($guide);

        $words = [];
        $weak = [];

        foreach ($attempt->words ?? [] as $word) {
            $status = is_string($word['status'] ?? null) ? $word['status'] : '';
            $text = is_string($word['text'] ?? null) ? $word['text'] : '';
            $key = is_string($word['key'] ?? null) ? $word['key'] : '';

            $row = [
                'word' => $text,
                'status' => $status,
                'heard_as' => $word['heard'] ?? null,
                'sound' => $word['sound'] ?? null,
            ];
            $words[] = $row;

            if (! in_array($status, [PronunciationOutcome::CORRECT, PronunciationOutcome::SKIPPED], true)) {
                $weak[] = $row + ['guide' => $knowledge->entry($key)];
            }
        }

        return [
            'text' => $attempt->reference_text,
            'sentence' => $attempt->sentence_text,
            'accent' => $attempt->accent->label(),
            'level' => $attempt->level,
            'scores' => [
                'overall' => $attempt->score,
                'words' => $attempt->words_score,
                'clarity' => $attempt->clarity_score,
                'flow' => $attempt->flow_score,
            ],
            'words' => $words,
            'weak_words' => $weak,
            'hesitations' => $attempt->fillers,
            'long_pauses' => $attempt->long_pauses,
            'speed_vs_reference' => $attempt->speed_ratio,
            'extra_words' => array_column($attempt->extras ?? [], 'word'),
        ];
    }
}
