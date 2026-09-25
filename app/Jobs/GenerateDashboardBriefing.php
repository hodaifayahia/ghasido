<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\Hotel;
use App\Services\Ai\UsageMeter;
use App\Services\Dashboard\DashboardBriefing;
use App\Services\Dashboard\DashboardStats;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Write the Dashboard briefing for one hotel, or for the whole portfolio
 * when `$hotelId` is null (spec 0005 §4.1).
 *
 * Rebuilds the same figures the dashboard shows at run time, so a burst of
 * page views is summed up once, and stores the model's words on the
 * `ai_insights` row the page polls. Metered as `dashboard_briefing`
 * (API-03); nobody's AI points are spent.
 */
class GenerateDashboardBriefing implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly ?int $hotelId)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return $this->hotelId === null ? 'portfolio' : (string) $this->hotelId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $hotel = $this->hotel();

        if ($this->hotelId !== null && $hotel === null) {
            return;
        }

        $insight = DashboardBriefing::insightFor($hotel);

        if ($insight === null) {
            return;
        }

        $insight->forceFill(['status' => GenerationStatus::Running])->save();

        // A fresh instance: DashboardStats keeps per-build state.
        $built = app(DashboardStats::class)->build($hotel);
        $context = DashboardBriefing::context($hotel, $built);
        $briefing = $ai->briefDashboard($context);

        $meter->record(null, AiFeature::DashboardBriefing, $briefing->usage, chargePoints: false, hotelId: $hotel?->id);

        $insight->forceFill([
            'status' => GenerationStatus::Done,
            'payload' => $briefing->toArray(),
            'fingerprint' => hash('sha256', (string) json_encode($context)),
            'failed_reason' => null,
            'generated_at' => Date::now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        DashboardBriefing::insightFor($this->hotel())?->forceFill([
            'status' => GenerationStatus::Failed,
            'failed_reason' => mb_substr((string) $exception?->getMessage(), 0, 500),
        ])->save();
    }

    private function hotel(): ?Hotel
    {
        return $this->hotelId === null ? null : Hotel::withoutGlobalScopes()->find($this->hotelId);
    }
}
