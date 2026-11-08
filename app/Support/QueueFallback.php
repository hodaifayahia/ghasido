<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Keep queued work moving when no queue worker is running (client report
 * 2026-11-08: "the AI just keeps spinning"). Every AI job runs on the
 * database queue (AGENTS.md §6); on shared hosting the workers live only
 * as long as their cron jobs, and when those are missing every lesson
 * generation, image, evaluation and translation waits forever.
 *
 * A real worker (cron or `remote-workers.sh`) stamps a heartbeat on every
 * loop. When the heartbeat is stale and jobs are waiting, a signed-in
 * request runs `queue:work --stop-when-empty` AFTER its response has been
 * sent (PHP's finish-request, so nobody waits for it), one drain at a
 * time behind a lock. The cron workers stay the recommended setup; this
 * only stops the app from stalling without them.
 */
final class QueueFallback
{
    public const string HEARTBEAT = 'queue:worker-heartbeat';

    private const string LOCK = 'queue:fallback-drain';

    /** A worker that looped within this many seconds counts as alive. */
    private const int ALIVE_SECONDS = 90;

    /** One drain's time budget; the next request starts another if needed. */
    public const int DRAIN_SECONDS = 120;

    private static bool $draining = false;

    /**
     * Called by Queue::looping: a real worker is alive. The fallback drain
     * does not count, or it would hide a missing worker from the next kick.
     */
    public static function beat(): void
    {
        if (! self::$draining) {
            Cache::put(self::HEARTBEAT, time(), 600);
        }
    }

    public static function workerAlive(): bool
    {
        return time() - (int) Cache::get(self::HEARTBEAT, 0) < self::ALIVE_SECONDS;
    }

    public function __construct(private readonly Application $app) {}

    /**
     * Start a drain after this response when jobs wait and no worker runs.
     * Returns whether one was scheduled (for the tests and the doctor).
     */
    public function kick(): bool
    {
        if (! config('queue.fallback', true)
            || config('queue.default') !== 'database'
            || self::workerAlive()
            || ! $this->jobsWaiting()) {
            return false;
        }

        $lock = Cache::lock(self::LOCK, self::DRAIN_SECONDS + 60);

        if (! $lock->get()) {
            return false;
        }

        $this->app->terminating(function () use ($lock): void {
            self::$draining = true;

            try {
                @set_time_limit(self::DRAIN_SECONDS + 60);
                ignore_user_abort(true);

                Artisan::call('queue:work', [
                    'connection' => 'database',
                    '--queue' => implode(',', [Queues::INTERACTIVE, Queues::DEFAULT, Queues::MEDIA]),
                    '--stop-when-empty' => true,
                    '--max-time' => self::DRAIN_SECONDS,
                    '--tries' => 3,
                    '--sleep' => 1,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            } finally {
                self::$draining = false;
                $lock->release();
            }
        });

        return true;
    }

    private function jobsWaiting(): bool
    {
        try {
            return DB::table((string) config('queue.connections.database.table', 'jobs'))
                ->whereNull('reserved_at')
                ->where('available_at', '<=', time())
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
