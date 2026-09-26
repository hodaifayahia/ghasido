<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Enums\TestAttemptStatus;
use App\Jobs\TranslateText;
use App\Models\TestAttempt;
use App\Models\TextTranslation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Show Meaning for any English text on the platform (CTRL-01..03; client
 * decision 2026-09-26): course, unit and lesson titles, descriptions,
 * objectives, instructions, activity and test questions.
 *
 * The Arabic never ships in the page: it leaves the server only here, after
 * the learner's tap (CTRL-02). The first tap on a text queues its
 * translation; the button polls this same endpoint until it is done. While
 * the learner sits a test whose admin switched Show Meaning off, every
 * request is refused, so a hidden button is never the only guard (CTRL-04,
 * SEC-01).
 */
final class MeaningController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
        ]);

        /** @var User $user */
        $user = $request->user('web');

        abort_if($this->sittingWithoutMeaning($user), 403, __('Show Meaning is switched off during this test.'));

        $text = TextTranslation::normalise((string) $data['text']);

        abort_if($text === '' || ! preg_match('/\p{L}/u', $text), 422, __('There is nothing to translate.'));

        $translation = TextTranslation::query()->firstOrCreate(
            ['hash' => TextTranslation::hashOf($text)],
            [
                'source_text' => $text,
                'status' => GenerationStatus::Pending,
                'source' => 'ai',
                'requested_by' => $user->id,
            ],
        );

        if ($translation->wasRecentlyCreated) {
            TranslateText::dispatch($translation->id);
        } elseif ($translation->status === GenerationStatus::Failed && $translation->updated_at?->lt(now()->subMinute())) {
            // One retry a minute at most, so a provider outage is not hammered.
            $translation->forceFill(['status' => GenerationStatus::Pending, 'failed_reason' => null])->save();
            TranslateText::dispatch($translation->id);
        }

        $translation->refresh();

        return response()->json([
            'status' => $translation->status->value,
            'arabic' => $translation->status === GenerationStatus::Done ? $translation->arabic : null,
        ], $translation->status === GenerationStatus::Done ? 200 : 202);
    }

    private function sittingWithoutMeaning(User $user): bool
    {
        return TestAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', TestAttemptStatus::InProgress)
            ->with('test')
            ->get()
            ->contains(fn (TestAttempt $attempt): bool => $attempt->isOpen() && $attempt->test !== null && ! $attempt->test->showsMeaning());
    }
}
