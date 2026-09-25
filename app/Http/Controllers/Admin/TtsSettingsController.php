<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tts\DeepgramVoiceCatalog;
use App\Services\Tts\TtsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Saves the safe global speech controls (TTS-04, API-02, API-04). API keys
 * stay in environment configuration and are never accepted from the client.
 */
final class TtsSettingsController extends Controller
{
    public function update(Request $request, TtsSettings $settings): RedirectResponse
    {
        Gate::authorize('update', $request->user());

        $validated = $request->validate([
            'voice' => ['required', 'string', Rule::in(DeepgramVoiceCatalog::models())],
            'expressivity' => ['required', 'integer', Rule::in([-2, -1, 0, 1, 2])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $settings->update([
            'voice' => (string) $validated['voice'],
            'expressivity' => (int) $validated['expressivity'],
        ], $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Speech settings updated. Regenerate lesson clips to use the new voice.'),
        ]);

        return back();
    }
}
