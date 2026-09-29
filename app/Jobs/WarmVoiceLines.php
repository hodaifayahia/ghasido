<?php

namespace App\Jobs;

use App\Services\VoiceAgent\VoiceLineBank;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Record the fast engine's generic guest lines ("Sorry, could you say that
 * again?") in one voice, so the AI can answer with them from the first call
 * (spec 0009). Unique per voice: two calls starting together queue one job,
 * and a line already recorded is never voiced again (TTS-02).
 */
class WarmVoiceLines implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30];

    public function __construct(public readonly string $voice)
    {
        $this->onQueue(Queues::MEDIA);
    }

    public function uniqueId(): string
    {
        return $this->voice;
    }

    public function handle(VoiceLineBank $bank): void
    {
        $bank->warm($this->voice);
    }
}
