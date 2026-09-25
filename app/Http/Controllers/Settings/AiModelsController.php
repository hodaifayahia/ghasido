<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RunProviderCheck;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ai\AiModelSettings;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → AI models (API-04, API-02, SEC-03, SEC-06, PERF-04): the
 * Super Admin switches the text, fast, image and speech models, turns a
 * paid capability off (fake) while testing, and tests each one with a
 * queued real call. Keys and endpoints are never accepted from the client.
 * Guarded by the `manage-ai-models` gate on the route and again here.
 */
final class AiModelsController extends Controller
{
    public function edit(AiModelSettings $settings, VoiceAgentSettings $voiceAgent): Response
    {
        Gate::authorize('manage-ai-models');

        return Inertia::render('settings/AiModels', [
            'settings' => $settings->payload(),
            'checks' => $settings->checks(),
            'voiceAgent' => ['settings' => $voiceAgent->payload()],
        ]);
    }

    public function update(Request $request, AiModelSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        $validated = $request->validate($settings->rules());

        /** @var User $user */
        $user = $request->user();
        $row = $settings->update($validated, $user);
        AuditLog::record($row, 'ai-models.settings.updated', ['values' => $row->values]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI model settings saved. New jobs use them at once.')]);

        return back();
    }

    public function check(Request $request, string $capability, AiModelSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        abort_unless(in_array($capability, AiModelSettings::CHECKS, true), 404);

        $settings->recordCheck($capability, ['status' => 'pending']);
        RunProviderCheck::dispatch($capability, $request->user()?->id);

        return back();
    }
}
