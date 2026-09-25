<?php

namespace App\Services\Dashboard;

use Carbon\CarbonInterface;
use Closure;

/**
 * One candidate row of the dashboard's Recent Activity feed.
 *
 * The detail line may be deferred: a lesson's number in its course and a
 * learner's first lesson each cost a query, so they are resolved only for the
 * five rows that survive the merge (DashboardStats::recentActivity()).
 */
final class ActivityEvent
{
    /**
     * @param  string|Closure(): string  $details
     */
    public function __construct(
        public readonly CarbonInterface $at,
        public readonly string $employee,
        public readonly string $type,
        public readonly string $activity,
        private readonly string|Closure $details,
    ) {}

    public function details(): string
    {
        return $this->details instanceof Closure ? ($this->details)() : $this->details;
    }
}
