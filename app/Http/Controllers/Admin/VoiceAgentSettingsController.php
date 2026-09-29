<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Saves the live voice-call controls (RP-03, RP-04, API-04, ADM-02,
 * SEC-06; spec 0004): the global Deepgram Voice Agent settings, and one
 * scenario's voice and greeting overrides. Keys are never accepted from the
 * client (API-02, SEC-03).
 */
final class VoiceAgentSettingsController extends Controller
{
    public function update(Request $request, VoiceAgentSettings $settings): RedirectResponse
    {
        Gate::authorize('create', AiScenario::class);

        $validated = $request->validate($settings->rules());

        // Configuration only: a Qwen quota outage moves calls to the voice
        // agent for a while, but never blocks saving the admin's choice.
        if ($validated['thinkMode'] === 'qwen_proxy' && ! $settings->qwenProxyAvailable(false)) {
            throw ValidationException::withMessages([
                'thinkMode' => (string) $settings->qwenProxyReason(false),
            ]);
        }

        if ($validated['engine'] === 'pipeline' && ! $settings->pipelineAvailable(false)) {
            throw ValidationException::withMessages([
                'engine' => (string) $settings->pipelineReason(false),
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $row = $settings->update($validated, $user);
        AuditLog::record($row, 'voice-agent.settings.updated', ['values' => $row->values]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voice call settings saved.')]);

        return back();
    }

    public function updateScenario(Request $request, AiScenario $scenario, VoiceAgentSettings $settings): RedirectResponse
    {
        Gate::authorize('update', $scenario);

        $validated = $request->validate([
            'speakModel' => ['nullable', 'string', Rule::in(VoiceAgentSettings::speakModels())],
            'elevenVoiceId' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'greeting' => ['nullable', 'string', 'max:300'],
        ]);

        $settings->updateScenario($scenario, [
            'speakModel' => isset($validated['speakModel']) ? (string) $validated['speakModel'] : null,
            'elevenVoiceId' => isset($validated['elevenVoiceId']) ? (string) $validated['elevenVoiceId'] : null,
            'greeting' => isset($validated['greeting']) ? (string) $validated['greeting'] : null,
        ]);
        AuditLog::record($scenario, 'ai-scenario.voice-agent.updated', ['voice_agent' => $settings->scenarioOverrides($scenario)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scenario voice saved.')]);

        return back();
    }
}
