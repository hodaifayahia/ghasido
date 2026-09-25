<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Accent;
use App\Http\Controllers\Controller;
use App\Models\AiScenario;
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
        // Speech settings shape every scenario's and lesson's audio, so they
        // are a content-authoring capability, not a self-edit (spec 0005 §1.8).
        Gate::authorize('create', AiScenario::class);

        $validated = $request->validate([
            'voice' => ['required', 'string', Rule::in(DeepgramVoiceCatalog::models())],
            'expressivity' => ['required', 'integer', Rule::in([-2, -1, 0, 1, 2])],
            // One lesson voice per accent (spec 0006 §3); each must be a
            // voice of that accent.
            'british_voice' => ['sometimes', 'nullable', 'string', Rule::in(DeepgramVoiceCatalog::modelsWithAccent(Accent::British->catalogAccent()))],
            'american_voice' => ['sometimes', 'nullable', 'string', Rule::in(DeepgramVoiceCatalog::modelsWithAccent(Accent::American->catalogAccent()))],
        ]);

        /** @var User $user */
        $user = $request->user();
        $values = [
            'voice' => (string) $validated['voice'],
            'expressivity' => (int) $validated['expressivity'],
        ];

        foreach (['british_voice', 'american_voice'] as $key) {
            if (array_key_exists($key, $validated)) {
                $values[$key] = is_string($validated[$key]) ? $validated[$key] : null;
            }
        }

        $settings->update($values, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Speech settings updated. Regenerate lesson clips to use the new voice.'),
        ]);

        return back();
    }
}
