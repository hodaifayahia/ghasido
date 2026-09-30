<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Enums\TestAttemptStatus;
use App\Models\TestAttempt;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Meaning\HelperLanguages;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Show Meaning for any English text on the platform (CTRL-01..03; client
 * decision 2026-09-26): course, unit and lesson titles, descriptions,
 * objectives, instructions, activity and test questions.
 *
 * Read-only for the learner (user request 2026-09-26): translations are made
 * when the content is written, by an AI draft or by the admin, never by a
 * tap. A text nobody has translated yet is noted for the admin and the
 * learner is told the meaning is not ready; no AI call is made.
 *
 * The meaning is in the learner's helper language (Arabic, French or any
 * language the Super Admin added; client request 2026-09-30).
 *
 * The meaning never ships in the page: it leaves the server only here, after
 * the learner's tap (CTRL-02). While the learner sits a test whose admin
 * switched Show Meaning off, every request is refused, so a hidden button is
 * never the only guard (CTRL-04, SEC-01).
 */
final class MeaningController extends Controller
{
    public function __invoke(Request $request, MeaningTranslations $translations, HelperLanguages $languages): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
        ]);

        /** @var User $user */
        $user = $request->user('web');

        abort_if($this->sittingWithoutMeaning($user), 403, __('Show Meaning is switched off during this test.'));

        $text = TextTranslation::normalise((string) $data['text']);

        abort_if($text === '' || ! preg_match('/\p{L}/u', $text), 422, __('There is nothing to translate.'));

        // The learner's own helper language (client request 2026-09-30).
        $locale = $languages->forUser($user);
        $language = ['locale' => $locale, 'dir' => $languages->direction($locale)];

        $translation = TextTranslation::query()
            ->where('hash', TextTranslation::hashOf($text))
            ->where('locale', $locale)
            ->first()
            ?? $translations->request($text, $user, $locale);

        if ($translation->status === GenerationStatus::Done) {
            return response()->json(['status' => 'done', 'text' => $translation->translation, ...$language]);
        }

        // An AI draft is on its way: the button waits for it.
        if ($translation->source === MeaningTranslations::SOURCE_AI && in_array($translation->status, [GenerationStatus::Pending, GenerationStatus::Running], true)) {
            return response()->json(['status' => 'pending', 'text' => null, ...$language], 202);
        }

        return response()->json(['status' => 'missing', 'text' => null, ...$language]);
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
