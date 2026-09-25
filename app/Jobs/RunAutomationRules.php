<?php

namespace App\Jobs;

use App\Services\Reminders\AutomationRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily automation pass (REM-03), scheduled in routes/console.php.
 *
 * Unique so two overlapping schedule ticks cannot run the rules twice at
 * once; the runner's repeat window is the second line of defence.
 */
class RunAutomationRules implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function handle(AutomationRunner $runner): void
    {
        $summary = $runner->run();

        Log::info('Reminder automation rules ran.', ['rules' => $summary]);
    }

    /**
     * No owning row to mark: the failure is logged for the operator.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Reminder automation rules failed.', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
