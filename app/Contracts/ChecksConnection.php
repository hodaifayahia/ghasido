<?php

namespace App\Contracts;

/**
 * A text provider that can answer one tiny request, for the Super Admin's
 * "Test" button on Settings → AI models (API-04, PERF-04). Kept apart from
 * AiProvider so a test double of that interface need not implement it.
 */
interface ChecksConnection
{
    /**
     * Ask for a one-line JSON reply. `$fast` uses the fast model, when one
     * is configured.
     */
    public function ping(bool $fast = false): AiReply;
}
