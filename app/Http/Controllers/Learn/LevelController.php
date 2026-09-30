<?php

namespace App\Http\Controllers\Learn;

use App\Enums\EnglishLevel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The learner's level (client decision 2026-09-30): Beginner, Intermediate
 * or Advanced, chosen by the learner and changeable later. It decides which
 * lessons and tests they see (courses.level, tests.level).
 *
 * After a strong Pre-test the next level is suggested; the learner moves up
 * or stays at their level to review. Nothing here changes the level unasked.
 */
class LevelController extends Controller
{
    /** Choose or change the level. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'string', Rule::enum(EnglishLevel::class)],
        ]);

        $user = $this->learner($request);
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

    private function learner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user('web');

        return $user;
    }
}
