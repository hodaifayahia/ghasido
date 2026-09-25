<?php

namespace App\Http\Controllers\Learn;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\StorePronunciationCheckRequest;
use App\Jobs\CheckPronunciation;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\PronunciationAttempt;
use App\Models\User;
use App\Services\Learning\RecordingStore;
use App\Services\Pronunciation\PronunciationPresenter;
use App\Services\Pronunciation\PronunciationTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The pronunciation check of a speaking step (spec 0006 §5, §7).
 *
 * `store` takes the recording, resolves what was to be said from the step
 * itself, stores both and queues the check; `show` is what the step polls.
 * JSON endpoints: the step page stays where it is while the learner tries
 * again.
 */
class PronunciationController extends Controller
{
    public function __construct(private readonly PronunciationPresenter $presenter) {}

    public function store(
        StorePronunciationCheckRequest $request,
        Lesson $lesson,
        Block $block,
        PronunciationTargets $targets,
        RecordingStore $recordings,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        // The lesson must be in the learner's reach and past the Pre-test
        // gate (ROLE-02, JOURNEY-01): another hotel's step is a 403.
        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $target = $targets->resolve($lesson, $block, $request->item(), $request->word());

        if ($target === null) {
            throw ValidationException::withMessages(['item' => __('There is nothing to say on this step.')]);
        }

        $cap = (int) config('guesvia.pronunciation.daily_checks_per_employee', 300);
        $today = PronunciationAttempt::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Date::now()->startOfDay())
            ->count();

        if ($today >= $cap) {
            return response()->json([
                'message' => __('You have practised a lot today. Pronunciation checks open again tomorrow.'),
            ], 429);
        }

        $recording = $recordings->store($user, $request->audio(), $block, $request->durationMs());

        $previous = (int) PronunciationAttempt::query()
            ->where('user_id', $user->id)
            ->where('block_id', $block->id)
            ->where('item_index', $target->itemIndex)
            ->where('lexicon_item_id', $target->lexiconItemId)
            ->where('word_index', $target->wordIndex)
            ->max('attempt_no');

        $attempt = PronunciationAttempt::query()->create([
            'user_id' => $user->id,
            'hotel_id' => $user->hotel_id,
            'department_id' => $user->department_id,
            'lesson_id' => $lesson->id,
            'block_id' => $block->id,
            'lexicon_item_id' => $target->lexiconItemId,
            'item_index' => $target->itemIndex,
            'word_index' => $target->wordIndex,
            'voice_recording_id' => $recording->id,
            'reference_text' => $target->text,
            'sentence_text' => $target->isWordDrill() ? $target->sentence : null,
            'accent' => $lesson->speakingAccent(),
            'attempt_no' => $previous + 1,
            'status' => GenerationStatus::Pending,
            'duration_ms' => $request->durationMs(),
        ]);

        CheckPronunciation::dispatch($attempt->id);

        return response()->json($this->presenter->present($attempt->refresh()), 202);
    }

    public function show(PronunciationAttempt $attempt): JsonResponse
    {
        Gate::authorize('view', $attempt);

        return response()->json($this->presenter->present($attempt));
    }
}
