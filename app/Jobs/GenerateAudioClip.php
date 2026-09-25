<?php

namespace App\Jobs;

use App\Contracts\TtsProvider;
use App\Enums\GenerationStatus;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\AudioClip;
use App\Models\MediaAsset;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Synthesise one audio clip and store it (TTS-01, TTS-02, TTS-03, spec 0003
 * B.4, Part C).
 *
 * Unique per clip id: a retry, or two content saves racing to ensure the same
 * sentence, can never produce two billed calls or two files for one clip. The
 * job also checks the row before calling out, so a clip that is already done
 * is left alone.
 *
 * Every path ends in `done` or `failed` on the row, which is what the page
 * polls (PERF-04).
 */
class GenerateAudioClip implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $clipId)
    {
        $this->onQueue(Queues::MEDIA);
    }

    public function uniqueId(): string
    {
        return (string) $this->clipId;
    }

    public function handle(TtsProvider $tts): void
    {
        $clip = AudioClip::query()->find($this->clipId);

        if ($clip === null || $clip->isDone()) {
            return;
        }

        $clip->markRunning();

        $audio = $tts->synthesise($clip->text, $clip->voice, $clip->speed);

        $now = now();
        $path = sprintf(
            'content/audio/%s/%s/%s.%s',
            $now->format('Y'),
            $now->format('m'),
            (string) Str::uuid(),
            ltrim($audio->extension, '.'),
        );

        Storage::disk(MediaAsset::DISK_PUBLIC)->put($path, $audio->binary);

        $asset = MediaAsset::query()->create([
            'disk' => MediaAsset::DISK_PUBLIC,
            'path' => $path,
            'original_name' => null,
            'mime' => $audio->mime,
            'kind' => MediaKind::Audio,
            'alt_text' => null,
            'duration_ms' => $audio->durationMs,
            'size_bytes' => strlen($audio->binary),
            'uploaded_by' => null,
            'hotel_id' => null,
            'library' => MediaLibrary::Generated,
            'category' => 'tts',
            'label' => Str::limit($clip->text, 80),
        ]);

        $provider = config('services.tts.provider', 'fake');

        $clip->markDone($asset, is_string($provider) ? $provider : 'unknown');
    }

    public function failed(?Throwable $exception): void
    {
        $clip = AudioClip::query()->find($this->clipId);

        if ($clip === null || $clip->status === GenerationStatus::Done) {
            return;
        }

        $clip->markFailed($exception?->getMessage() ?? 'Audio generation failed.');
    }
}
