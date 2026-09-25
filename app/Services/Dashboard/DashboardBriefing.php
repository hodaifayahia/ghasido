<?php

namespace App\Services\Dashboard;

use App\Enums\GenerationStatus;
use App\Jobs\GenerateDashboardBriefing;
use App\Models\AiInsight;
use App\Models\Hotel;

/**
 * The AI briefing on the Dashboard (spec 0005 §4.1).
 *
 * One stored briefing per hotel, plus one for the whole portfolio (the Super
 * Admin's view), in `ai_insights`. The model reads aggregate figures only,
 * taken from the dashboard the viewer already sees (DashboardStats::build),
 * so a briefing never says more than the page does and never names a
 * learner (PRIV-03). Rewritten by a queued job when the figures change,
 * at most every COOLDOWN_HOURS; the page polls while it is written (PERF-04).
 */
final class DashboardBriefing
{
    public const COOLDOWN_HOURS = 12;

    /**
     * What the briefing card shows, queueing a refresh when one is due.
     *
     * @param  array<string, mixed>  $built  DashboardStats::build($hotel)
     * @return array{status: string, headline: string|null, highlights: list<string>, concerns: list<string>, actions: list<string>, generatedAt: string|null}
     */
    public function present(?Hotel $hotel, array $built): array
    {
        $context = self::context($hotel, $built);
        $fingerprint = hash('sha256', (string) json_encode($context));
        $insight = self::insightFor($hotel);

        if ($context['employees'] > 0 && AiInsight::needsRefresh($insight, $fingerprint, self::COOLDOWN_HOURS)) {
            $insight = $this->queue($hotel, $insight, $fingerprint);
        }

        $payload = $insight === null ? [] : ($insight->payload ?? []);

        return [
            'status' => AiInsight::stateOf($insight),
            'headline' => is_string($payload['headline'] ?? null) ? $payload['headline'] : null,
            'highlights' => self::strings($payload['highlights'] ?? []),
            'concerns' => self::strings($payload['concerns'] ?? []),
            'actions' => self::strings($payload['actions'] ?? []),
            'generatedAt' => $insight?->generated_at?->toIso8601String(),
        ];
    }

    /**
     * The figures the model reads: counts and percentages only.
     *
     * @param  array<string, mixed>  $built
     * @return array{scope: string, employees: int, stats: array<string, mixed>, training: mixed, departments: list<array{name: string, percent: int}>, needs_attention: array<string, int>, at_risk: array{total: int, high: int, medium: int, reasons: mixed}}
     */
    public static function context(?Hotel $hotel, array $built): array
    {
        $stats = [];

        foreach (is_array($built['stats'] ?? null) ? $built['stats'] : [] as $stat) {
            if (is_array($stat) && is_string($stat['key'] ?? null)) {
                $stats[$stat['key']] = $stat['value'] ?? null;
            }
        }

        $departments = [];

        foreach (is_array($built['departmentProgress'] ?? null) ? $built['departmentProgress'] : [] as $department) {
            if (is_array($department)) {
                $departments[] = ['name' => (string) ($department['name'] ?? ''), 'percent' => (int) ($department['percent'] ?? 0)];
            }
        }

        $attention = [];

        foreach (is_array($built['needsAttention'] ?? null) ? $built['needsAttention'] : [] as $group) {
            if (is_array($group) && is_string($group['key'] ?? null)) {
                $attention[$group['key']] = (int) ($group['total'] ?? 0);
            }
        }

        $atRisk = is_array($built['atRisk'] ?? null) ? $built['atRisk'] : [];

        return [
            'scope' => $hotel === null ? 'whole portfolio' : 'one hotel',
            'employees' => (int) ($stats['employees'] ?? 0),
            'stats' => $stats,
            'training' => $built['trainingOverview']['all'] ?? null,
            'departments' => $departments,
            'needs_attention' => $attention,
            'at_risk' => [
                'total' => (int) ($atRisk['total'] ?? 0),
                'high' => (int) ($atRisk['high'] ?? 0),
                'medium' => (int) ($atRisk['medium'] ?? 0),
                // How many learners each rule flagged; never who.
                'reasons' => $atRisk['reasons'] ?? [],
            ],
        ];
    }

    public static function insightFor(?Hotel $hotel): ?AiInsight
    {
        return $hotel === null
            ? AiInsight::forSubject(AiInsight::SUBJECT_PORTFOLIO, 0, AiInsight::KIND_HOTEL_BRIEFING)
            : AiInsight::for($hotel, AiInsight::KIND_HOTEL_BRIEFING);
    }

    private function queue(?Hotel $hotel, ?AiInsight $insight, string $fingerprint): AiInsight
    {
        $insight ??= new AiInsight([
            'subject_type' => $hotel === null ? AiInsight::SUBJECT_PORTFOLIO : $hotel->getMorphClass(),
            'subject_id' => $hotel === null ? 0 : $hotel->id,
            'kind' => AiInsight::KIND_HOTEL_BRIEFING,
            'hotel_id' => $hotel?->id,
        ]);

        $insight->forceFill([
            'status' => GenerationStatus::Pending,
            'fingerprint' => $fingerprint,
            'failed_reason' => null,
        ])->save();

        // A queue outage keeps the last briefing (or the pending state) on
        // the card; it never breaks the dashboard.
        rescue(function () use ($hotel): void {
            GenerateDashboardBriefing::dispatch($hotel?->id);
        });

        return $insight->refresh();
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''))
            : [];
    }
}
