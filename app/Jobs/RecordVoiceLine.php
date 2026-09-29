<?php

namespace App\Jobs;

use App\Models\AudioClip;
use App\Models\User;
use App\Services\VoiceAgent\VoiceLineBank;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Record one guest line of the fast voice engine in its Aura-2 voice and
 * keep it (spec 0009). The learner already heard the line streamed from
 * Deepgram; this recording is what the next call plays from storage, and
 * what the AI may reuse instead of a new line (TTS-02, AIL-04).
 *
 * Unique per clip, so a line said in two calls at once is voiced once.
 */
class RecordVoiceLine implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly int $clipId,
        public readonly ?int $userId = null,
    ) {
        $this->onQueue(Queues::MEDIA);
    }

    public function uniqueId(): string
    {
        return (string) $this->clipId;
    }

    public function handle(VoiceLineBank $bank): void
    {
        $clip = AudioClip::query()->find($this->clipId);

        if ($clip === null) {
            return;
        }

        $user = $this->userId !== null ? User::query()->find($this->userId) : null;

        if (! $bank->record($clip, $user)) {
            // Retried with backoff; the clip stays pending meanwhile.
            throw new RuntimeException(sprintf('Voice line [%d] could not be recorded.', $clip->id));
        }
    }
}
