<?php

namespace Tests\Feature;

use App\Support\QueueFallback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The queue keeps moving without a worker (client report 2026-11-08:
 * "the AI just keeps spinning"): a request drains the database queue after
 * its response when no worker heartbeat is fresh, one drain at a time.
 */
class QueueFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue.default', 'database');
        config()->set('queue.fallback', true);
        config()->set('cache.default', 'array');
        Cache::forget(QueueFallback::HEARTBEAT);
    }

    private function queueJob(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() - 5,
            'created_at' => time() - 5,
        ]);
    }

    public function test_a_waiting_job_with_no_worker_schedules_one_drain()
    {
        $this->queueJob();
        $fallback = app(QueueFallback::class);

        $this->assertTrue($fallback->kick());
        // The lock holds a second request back while the first drains.
        $this->assertFalse($fallback->kick());
    }

    public function test_nothing_happens_while_a_real_worker_is_alive_or_nothing_waits()
    {
        $fallback = app(QueueFallback::class);

        $this->assertFalse($fallback->kick());

        $this->queueJob();
        QueueFallback::beat();

        $this->assertTrue(QueueFallback::workerAlive());
        $this->assertFalse($fallback->kick());
    }

    public function test_the_sync_queue_never_drains()
    {
        config()->set('queue.default', 'sync');
        $this->queueJob();

        $this->assertFalse(app(QueueFallback::class)->kick());
    }
}
