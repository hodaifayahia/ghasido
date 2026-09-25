<?php

namespace App\Enums;

/**
 * Where a queued AI or TTS job stands (PERF-04, GEN-03, TTS-03, spec 0003
 * B.4, B.5).
 *
 * Persisted on the owning row so the page can poll it. Every job reaches
 * `done` or `failed`; a spinner with no terminal state is a defect
 * (AGENTS.md §6, Queues).
 */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
