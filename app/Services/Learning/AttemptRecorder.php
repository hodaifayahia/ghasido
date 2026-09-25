<?php

namespace App\Services\Learning;

use App\Enums\ActivityType;
use App\Enums\GenerationStatus;
use App\Jobs\EvaluateWrittenAnswer;
use App\Jobs\TranscribeAndEvaluateSpokenAnswer;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes one practice answer as an `attempts` row (TEST-06, PRAC-04,
 * DATA-01, DATA-02, DATA-03, DATA-08, DATA-11, WRITE-04; spec 0003 B.9).
 *
 * The research row: the raw answer exactly as posted, the version it was
 * given against, the time taken whether or not a timer ran, the recording
 * for a spoken answer, and for a written one the queued AI evaluation that
 * lands on the same row. Never only a score.
 */
class AttemptRecorder
{
    public function __construct(
        private readonly ActivityScorer $scorer,
        private readonly ActivityPresenter $presenter,
        private readonly ProgressService $progress,
    ) {}

    /**
     * @param  array<array-key, mixed>  $answers  keyed by item id (spec 0003 B.9)
     */
    public function record(
        User $user,
        Lesson $lesson,
        Block $block,
        ActivityPlacement $placement,
        array $answers,
        ?CarbonInterface $startedAt = null,
    ): Attempt {
        $activity = $placement->activity()->firstOrFail();
        $version = $this->presenter->currentVersion($activity);
        $result = $this->scorer->scoreItems($activity->type, $version->items(), $answers);

        $submittedAt = now();
        $startedAt ??= $submittedAt;

        if ($startedAt->greaterThan($submittedAt)) {
            $startedAt = $submittedAt;
        }

        $recordingId = $this->recordingIdIn($user, $activity->type, $answers);
        $spoken = $activity->type === ActivityType::Speaking && $recordingId !== null;

        $attempt = DB::transaction(function () use ($user, $lesson, $block, $placement, $activity, $version, $answers, $result, $startedAt, $submittedAt, $recordingId, $spoken): Attempt {
            $attemptNo = (int) Attempt::query()
                ->where('user_id', $user->id)
                ->where('placement_id', $placement->id)
                ->whereNull('test_attempt_id')
                ->max('attempt_no') + 1;

            $attempt = Attempt::query()->create([
                'user_id' => $user->id,
                'activity_id' => $activity->id,
                'activity_version_id' => $version->id,
                'placement_id' => $placement->id,
                'test_attempt_id' => null,
                'lesson_id' => $lesson->id,
                'block_id' => $block->id,
                'attempt_no' => $attemptNo,
                'raw_answer' => $answers,
                'response_media_id' => $recordingId,
                'ai_status' => $activity->type === ActivityType::Writing || $spoken ? GenerationStatus::Pending : null,
                'started_at' => $startedAt,
                'submitted_at' => $submittedAt,
                'time_taken_ms' => max(0, (int) $startedAt->diffInMilliseconds($submittedAt)),
                ...$result->toAttemptColumns(),
            ]);

            $this->progress->touch($user);

            return $attempt;
        });

        if ($activity->type === ActivityType::Writing) {
            EvaluateWrittenAnswer::dispatch($attempt->id);
        }

        // Transcribe + judge a recorded answer (TEST-07, AIE-01, spec 0004).
        // A failing evaluation must never lose the learner's saved answer
        // (PROG-04): on a sync queue the job's error is reported, not thrown.
        if ($spoken) {
            // A void closure: PendingDispatch sends on destruct, inside rescue().
            rescue(function () use ($attempt): void {
                TranscribeAndEvaluateSpokenAnswer::dispatch($attempt->id);
            });
        }

        return $attempt;
    }

    /**
     * What the practice page shows after an answer: the verdict per item and
     * the correct answers revealed (ACC-02 needs icon + text, so the page
     * gets booleans, not colours).
     *
     * @return array<string, mixed>
     */
    public function resultFor(Attempt $attempt): array
    {
        $version = $attempt->activityVersion()->firstOrFail();
        $type = $attempt->activity()->firstOrFail()->type;
        $result = $this->scorer->scoreItems($type, $version->items(), $attempt->raw_answer ?? []);

        return [
            'attemptId' => $attempt->id,
            'attemptNo' => $attempt->attempt_no,
            'score' => $attempt->score === null ? null : (float) $attempt->score,
            'maxScore' => $attempt->max_score === null ? null : (float) $attempt->max_score,
            'isCorrect' => $attempt->is_correct,
            'perItem' => $result->perItem,
            'correct' => $this->presenter->answersOf($version),
            'timeTakenMs' => $attempt->time_taken_ms,
            'aiStatus' => $attempt->ai_status?->value,
        ];
    }

    /**
     * The uploaded recording a spoken answer points at, verified to be this
     * learner's own upload so an id cannot claim someone else's file.
     *
     * @param  array<array-key, mixed>  $answers
     */
    private function recordingIdIn(User $user, ActivityType $type, array $answers): ?int
    {
        if ($type !== ActivityType::Speaking) {
            return null;
        }

        foreach ($answers as $value) {
            $id = is_array($value) ? ($value['recording_media_id'] ?? null) : null;

            if (! is_numeric($id)) {
                continue;
            }

            $owned = MediaAsset::query()
                ->whereKey((int) $id)
                ->where('uploaded_by', $user->id)
                ->exists();

            if ($owned) {
                return (int) $id;
            }
        }

        return null;
    }
}
