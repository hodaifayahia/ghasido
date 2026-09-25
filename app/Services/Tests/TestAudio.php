<?php

namespace App\Services\Tests;

use App\Enums\GenerationStatus;
use App\Models\Activity;
use App\Models\AudioClip;
use App\Models\Test;
use App\Services\Audio\AudioLibrary;

/**
 * Stored TTS audio for listening questions (TTS-01, TTS-02, CTRL-05,
 * PERF-04; spec 0004).
 *
 * A listening script lives in the item as `audio_text` or
 * `guest_audio_text`; this service queues both speeds through the shared
 * AudioLibrary (one file per sentence, hash-keyed, generated once) and
 * reports where generation stands so the admin page can show it. It never
 * synthesises inside a request: the queued GenerateAudioClip job does.
 */
class TestAudio
{
    /** @var list<string> */
    public const SCRIPT_KEYS = ['audio_text', 'guest_audio_text'];

    public function __construct(private readonly AudioLibrary $library) {}

    /**
     * Every listening script in one question's payload.
     *
     * @return list<string>
     */
    public function scriptsOf(Activity $activity): array
    {
        $texts = [];
        $this->collect($activity->payload, $texts);

        return array_values($texts);
    }

    /**
     * Queue normal + slow audio for one question. Returns the number of
     * scripts queued (already-generated clips are left alone).
     */
    public function generate(Activity $activity): int
    {
        $scripts = $this->scriptsOf($activity);

        foreach ($scripts as $script) {
            $this->library->ensureBoth($script);
        }

        return count($scripts);
    }

    /**
     * Queue audio for every question of a test.
     */
    public function generateForTest(Test $test): int
    {
        $count = 0;

        foreach ($test->questions()->with('activity')->get() as $placement) {
            if ($placement->activity !== null) {
                $count += $this->generate($placement->activity);
            }
        }

        return $count;
    }

    /**
     * Where this question's audio stands: `none` (no script), `missing`
     * (never generated), `pending`, `running`, `failed` or `done` (both
     * speeds of every script stored).
     *
     * @return array{status: string, scripts: list<string>, failedReason: string|null}
     */
    public function status(Activity $activity): array
    {
        $scripts = $this->scriptsOf($activity);

        if ($scripts === []) {
            return ['status' => 'none', 'scripts' => [], 'failedReason' => null];
        }

        $hashes = array_map(static fn (string $text): string => AudioClip::hashFor(AudioClip::normalise($text)), $scripts);

        $clips = AudioClip::query()
            ->forVoice($this->library->voice())
            ->whereIn('text_hash', array_unique($hashes))
            ->get(['id', 'text_hash', 'speed', 'status', 'failed_reason', 'media_asset_id']);

        $expected = count(array_unique($hashes)) * 2;

        if ($clips->isEmpty()) {
            return ['status' => 'missing', 'scripts' => $scripts, 'failedReason' => null];
        }

        $failed = $clips->first(fn (AudioClip $clip): bool => $clip->status === GenerationStatus::Failed);

        if ($failed !== null) {
            return ['status' => 'failed', 'scripts' => $scripts, 'failedReason' => $failed->failed_reason];
        }

        if ($clips->contains(fn (AudioClip $clip): bool => $clip->status === GenerationStatus::Running)) {
            return ['status' => 'running', 'scripts' => $scripts, 'failedReason' => null];
        }

        $done = $clips->filter(fn (AudioClip $clip): bool => $clip->isDone())->count();

        if ($done >= $expected) {
            return ['status' => 'done', 'scripts' => $scripts, 'failedReason' => null];
        }

        return ['status' => $clips->count() < $expected && $done === $clips->count() ? 'missing' : 'pending', 'scripts' => $scripts, 'failedReason' => null];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, string>  $texts
     */
    private function collect(array $data, array &$texts): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->collect($value, $texts);

                continue;
            }

            if (is_string($key) && in_array($key, self::SCRIPT_KEYS, true) && is_string($value) && trim($value) !== '') {
                $texts[AudioClip::normalise($value)] = $value;
            }
        }
    }
}
