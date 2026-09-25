<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Contracts\AiUsageInfo;
use App\Contracts\ChecksConnection;
use App\Contracts\ImageProvider;
use App\Contracts\SpeechToTextProvider;
use App\Contracts\TtsProvider;
use App\Enums\AiFeature;
use App\Enums\AudioSpeed;
use App\Models\User;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\UsageMeter;
use App\Services\Tts\TtsSettings;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The Settings → AI models "Test" button (API-04, PERF-04, AIL-04): one tiny
 * real call to a capability with the models that are live right now, its
 * latency, and the provider's own error text when it fails. Queued, never
 * in the request (AGENTS §6); the page polls the stored state.
 *
 * ai/fast = a one-line JSON reply; image = one square picture at the
 * configured square size (discarded); tts = one sentence; stt = the TTS
 * clip of a known sentence, transcribed. Every real call is metered.
 */
class RunProviderCheck implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string SENTENCE = 'Good morning, welcome to our hotel.';

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $capability,
        public readonly ?int $userId = null,
    ) {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return $this->capability;
    }

    public function handle(AiModelSettings $settings, UsageMeter $meter): void
    {
        $effective = $settings->effective()[$this->capability] ?? null;

        if ($effective === null) {
            return;
        }

        $settings->recordCheck($this->capability, [
            'status' => 'running',
            'provider' => $effective['provider'],
            'model' => $effective['model'],
        ]);

        $user = $this->userId === null ? null : User::query()->find($this->userId);
        $started = hrtime(true);

        try {
            [$detail, $model] = $this->run($meter, $user, $effective['provider']);
        } catch (Throwable $e) {
            $settings->recordCheck($this->capability, [
                'status' => 'failed',
                'latency_ms' => self::elapsedMs($started),
                'provider' => $effective['provider'],
                'model' => $effective['model'],
                'error' => self::message($e),
            ]);

            return;
        }

        $settings->recordCheck($this->capability, [
            'status' => 'ok',
            'latency_ms' => self::elapsedMs($started),
            'provider' => $effective['provider'],
            'model' => $model !== '' ? $model : $effective['model'],
            'detail' => $detail,
        ]);
    }

    /**
     * A crash outside handle()'s own catch (a timeout) still ends the
     * spinner with a terminal state.
     */
    public function failed(?Throwable $e): void
    {
        app(AiModelSettings::class)->recordCheck($this->capability, [
            'status' => 'failed',
            'error' => $e === null ? __('The check did not finish.') : self::message($e),
        ]);
    }

    /**
     * @return array{0: string, 1: string} detail, model
     */
    private function run(UsageMeter $meter, ?User $user, string $provider): array
    {
        return match ($this->capability) {
            'ai', 'fast' => $this->checkText($meter, $user),
            'image' => $this->checkImage($meter, $user),
            'tts' => $this->checkTts($meter, $user, $provider),
            'stt' => $this->checkStt($meter, $user, $provider),
            default => throw new RuntimeException('Unknown capability.'),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function checkText(UsageMeter $meter, ?User $user): array
    {
        /** @var AiProvider $ai */
        $ai = app(AiProvider::class);

        if (! $ai instanceof ChecksConnection) {
            throw new RuntimeException(__('This AI provider has no connection check.'));
        }

        $reply = $ai->ping($this->capability === 'fast');
        $meter->record($user, AiFeature::ProviderCheck, $reply->usage);

        return [__('Replied: :text', ['text' => Str::limit($reply->text, 120)]), $reply->usage->model];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function checkImage(UsageMeter $meter, ?User $user): array
    {
        $image = app(ImageProvider::class)->generate(
            'A small silver hotel reception bell on a wooden desk, soft light, simple photo.',
            ImageProvider::SIZE_SQUARE,
        );
        $meter->record($user, AiFeature::ProviderCheck, $image->usage);

        $size = $image->width !== null && $image->height !== null ? $image->width.'×'.$image->height.', ' : '';

        return [__('Image received: :size:kb KB :mime', [
            'size' => $size,
            'kb' => (string) max(1, (int) round(strlen($image->binary) / 1024)),
            'mime' => $image->mime,
        ]), $image->usage->model];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function checkTts(UsageMeter $meter, ?User $user, string $provider): array
    {
        $voice = app(TtsSettings::class)->voice();
        $audio = app(TtsProvider::class)->synthesise(self::SENTENCE, $voice, AudioSpeed::Normal);
        $meter->record($user, AiFeature::ProviderCheck, new AiUsageInfo(0, mb_strlen(self::SENTENCE), $voice, $provider));

        return [__('Audio received: :kb KB :mime', [
            'kb' => (string) max(1, (int) round(strlen($audio->binary) / 1024)),
            'mime' => $audio->mime,
        ]), $voice];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function checkStt(UsageMeter $meter, ?User $user, string $provider): array
    {
        $settings = app(AiModelSettings::class);
        $voice = app(TtsSettings::class)->voice();
        $audio = app(TtsProvider::class)->synthesise(self::SENTENCE, $voice, AudioSpeed::Normal);
        $ttsProvider = $settings->effective()['tts']['provider'];

        if ($ttsProvider !== 'fake') {
            $meter->record($user, AiFeature::ProviderCheck, new AiUsageInfo(0, mb_strlen(self::SENTENCE), $voice, $ttsProvider));
        }

        $path = tempnam(sys_get_temp_dir(), 'stt-check-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file.');
        }

        try {
            file_put_contents($path, $audio->binary);
            $transcript = app(SpeechToTextProvider::class)->transcribe($path, $audio->mime);
        } finally {
            @unlink($path);
        }

        $model = $settings->sttModel($provider);
        $meter->record($user, AiFeature::ProviderCheck, new AiUsageInfo(0, 0, $model !== '' ? $model : $provider, $provider));

        return [__('Heard: “:text”', ['text' => Str::limit(trim($transcript), 120)]), $model];
    }

    private static function elapsedMs(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    /**
     * The provider's own reason, trimmed; never a key (the providers never
     * put one in a message).
     */
    private static function message(Throwable $e): string
    {
        $message = trim($e->getMessage());

        return Str::limit($message !== '' ? $message : class_basename($e), 600);
    }
}
