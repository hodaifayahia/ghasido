<?php

namespace App\Http\Controllers\Learn;

use App\Enums\EnglishLevel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LevelChangeRequest;
use App\Models\User;
use App\Services\Learning\LevelLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The learner's level (client decision 2026-09-30): Beginner, Intermediate
 * or Advanced, chosen by the learner. It stays locked until they finish its
 * Post-test; before that a change is a request the Super Admin decides
 * (client request 2026-10-04). It decides which
 * lessons and tests they see (courses.level, tests.level).
 *
 * After a strong Pre-test the next level is suggested; the learner moves up
 * or stays at their level to review. Nothing here changes the level unasked.
 */
class LevelController extends Controller
{
    /** Choose or change the level. */
    public function update(Request $request, LevelLock $lock): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'string', Rule::enum(EnglishLevel::class)],
        ]);

        $user = $this->learner($request);

        // Locked until the current level's Post-test (client request
        // 2026-10-04); before that, the learner sends a request instead.
        if (! $lock->canChange($user)) {
            return back()->withErrors(['level' => __('Your level is locked until you finish its Post-test. Send a level change request instead.')]);
        }

        $level = EnglishLevel::from((string) $data['level']);
        $from = $user->english_level;

        $user->forceFill([
            'english_level' => $level,
            'english_level_assessed_at' => Date::now(),
            'level_suggestion' => null,
        ])->save();

        AuditLog::record($user, 'learner.level.changed', ['from' => $from?->value, 'to' => $level->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your level is now :level.', ['level' => $level->label()])]);

        return back();
    }

    /** Move up to the suggested level, or stay to review. */
    public function answer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'string', Rule::in(['move', 'stay'])],
        ]);

        $user = $this->learner($request);
        $suggestion = $user->level_suggestion;

        if ($suggestion === null) {
            return back();
        }

        if ($data['decision'] === 'move') {
            $from = $user->english_level;
            $user->forceFill([
                'english_level' => $suggestion,
                'english_level_assessed_at' => Date::now(),
                'level_suggestion' => null,
            ])->save();

            AuditLog::record($user, 'learner.level.moved_up', ['from' => $from?->value, 'to' => $suggestion->value]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Well done! You moved up to :level.', ['level' => $suggestion->label()])]);

            return back();
        }

        $user->forceFill(['level_suggestion' => null])->save();

        AuditLog::record($user, 'learner.level.stayed', ['level' => $user->english_level?->value, 'declined' => $suggestion->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You are staying at :level to review.', ['level' => $user->english_level?->label() ?? ''])]);

        return back();
    }

    /**
     * Ask the Super Admin to change level before the Post-test, with the
     * reason (client request 2026-10-04): a level chosen by mistake, or
     * lessons too hard or too easy.
     */
    public function request(Request $request, LevelLock $lock): RedirectResponse
    {
        $user = $this->learner($request);

        $data = $request->validate([
            'level' => ['required', 'string', Rule::enum(EnglishLevel::class), Rule::notIn([$user->english_level?->value])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'level.not_in' => __('Choose a level different from your current one.'),
        ]);

        if ($lock->pendingRequest($user) !== null) {
            return back()->withErrors(['level' => __('You already have a level change request waiting for an answer.')]);
        }

        $level = EnglishLevel::from((string) $data['level']);
        $levelRequest = LevelChangeRequest::query()->create([
            'user_id' => $user->id,
            'hotel_id' => $user->hotel_id,
            'from_level' => $user->english_level,
            'to_level' => $level,
            'reason' => (string) $data['reason'],
            'status' => 'pending',
        ]);

        AuditLog::record($levelRequest, 'learner.level.change_requested', ['from' => $user->english_level?->value, 'to' => $level->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your request was sent. You will get an answer in your notifications.')]);

        return back();
    }

    private function learner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user('web');

        return $user;
    }
}
