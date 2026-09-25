<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\Ai\AiUsageReport;
use App\Services\Owner\CreditSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → AI usage (API-03, AIL-04; spec 0005 §4.3): Super Admin only,
 * like Settings → AI models, and the gate is repeated here. Read-only: the
 * price table behind the costs belongs to the platform owner, who edits it
 * on the owner console, since it also sets the dollar credit the app may
 * spend (spec 0007, D8).
 */
class AiUsageController extends Controller
{
    public function __construct(private readonly AiUsageReport $report) {}

    public function index(Request $request, CreditSummary $credit): Response
    {
        Gate::authorize('manage-ai-models');

        $validated = $request->validate([
            'period' => ['nullable', 'integer', Rule::in(AiUsageReport::PERIODS)],
            'hotel' => ['nullable', 'integer', 'exists:hotels,id'],
        ]);

        $period = (int) ($validated['period'] ?? 30);
        $hotel = isset($validated['hotel']) ? (int) $validated['hotel'] : null;

        return Inertia::render('settings/AiUsage', [
            'report' => $this->report->build($period, $hotel),
            // Her AI credit, as on the dashboard (spec 0007, D11).
            'aiCredit' => $credit->forSuperAdmin(),
            'periods' => AiUsageReport::PERIODS,
            'hotels' => Hotel::withoutGlobalScopes()->notArchived()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Hotel $hotel): array => ['id' => $hotel->id, 'name' => $hotel->name])
                ->values()
                ->all(),
        ]);
    }
}
