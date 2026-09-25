<?php

namespace App\Services\Images;

use App\Contracts\AiUsageInfo;
use App\Contracts\GeneratedImage;
use App\Contracts\ImageProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * Qwen / Wan images through Alibaba DashScope (GEN-01, API-02, API-04,
 * SEC-03; spec 0004 NOTES "DashScope image task").
 *
 * Two request shapes, chosen by the response:
 *
 * 1. The async task API — POST {base}/services/aigc/text2image/image-synthesis
 *    with `X-DashScope-Async: enable` returns `output.task_id`; GET
 *    {base}/tasks/{id} is polled with a growing pause until
 *    `output.task_status` is SUCCEEDED (`output.results[0].url`) or FAILED.
 * 2. When the configured model rejects that endpoint (a 4xx other than
 *    auth or rate limit), or the key may not make async calls (a 403 that
 *    says so — the token-plan key does), the sync multimodal endpoint
 *    POST {base}/services/aigc/multimodal-generation/generation, which
 *    answers `output.choices[0].message.content[].image` directly.
 *
 * Result URLs expire after 24 hours, so the bytes are downloaded here at
 * once; nothing downstream ever stores a provider URL. Retries cover only
 * what a retry can fix: an unreachable host, a 429 or a 5xx.
 */
final class DashScopeImageProvider implements ImageProvider
{
    public const string PROVIDER = 'qwen';

    public const string DEFAULT_BASE_URL = 'https://dashscope-intl.aliyuncs.com/api/v1';

    public const string DEFAULT_MODEL = 'qwen-image-3.0-pro';

    public const string DEFAULT_LANDSCAPE_SIZE = '1664*928';

    public const string DEFAULT_SQUARE_SIZE = '1328*1328';

    private const string ASYNC_PATH = '/services/aigc/text2image/image-synthesis';

    private const string SYNC_PATH = '/services/aigc/multimodal-generation/generation';

    private const int CONNECT_TIMEOUT_SECONDS = 15;

    /**
     * The sync shape answers only when the picture is drawn: ~2 minutes for
     * qwen-image-3.0-pro measured live (2026-09-22). Generous, so a slow
     * draw is not cut off and retried (which would bill it twice).
     */
    private const int REQUEST_TIMEOUT_SECONDS = 300;

    private const int RETRY_TIMES = 3;

    private const int RETRY_SLEEP_MS = 2000;

    /** How long a task may run before the job gives up (NOTES: ~4 minutes). */
    public const int POLL_DEADLINE_SECONDS = 240;

    /** @var list<int> seconds between polls; the last value repeats */
    private const array POLL_PAUSES = [2, 3, 5, 8, 10];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly ?string $baseUrl = null,
        private readonly ?string $landscapeSize = null,
        private readonly ?string $squareSize = null,
    ) {}

    public function generate(string $prompt, string $size = self::SIZE_LANDSCAPE): GeneratedImage
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('AI_IMAGE_KEY is not configured (config services.ai.image_key).');
        }

        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new RuntimeException('An image needs a prompt.');
        }

        $url = $this->imageUrl($prompt, $this->dimensions($size));

        return $this->download($url);
    }

    /**
     * The result URL, through the async task API or the sync fallback.
     */
    private function imageUrl(string $prompt, string $size): string
    {
        try {
            $response = $this->client()
                ->withHeaders(['X-DashScope-Async' => 'enable'])
                ->post(self::ASYNC_PATH, [
                    'model' => $this->model,
                    'input' => ['prompt' => $prompt],
                    'parameters' => [
                        'size' => $size,
                        'n' => 1,
                        'watermark' => false,
                        'prompt_extend' => true,
                    ],
                ]);
        } catch (RequestException $e) {
            if (! $this->modelRejectedEndpoint($e)) {
                throw $this->failure($e);
            }

            return $this->syncImageUrl($prompt, $size);
        }

        return $this->urlFrom($this->json($response->json()));
    }

    /**
     * The sync multimodal shape, for models the text2image task endpoint
     * does not serve.
     */
    private function syncImageUrl(string $prompt, string $size): string
    {
        try {
            $response = $this->client()->post(self::SYNC_PATH, [
                'model' => $this->model,
                'input' => [
                    'messages' => [[
                        'role' => 'user',
                        'content' => [['text' => $prompt]],
                    ]],
                ],
                'parameters' => [
                    'size' => $size,
                    'n' => 1,
                    'watermark' => false,
                    'prompt_extend' => true,
                ],
            ]);
        } catch (RequestException $e) {
            throw $this->failure($e);
        }

        return $this->urlFrom($this->json($response->json()));
    }

    /**
     * A response either carries the image already or a task to wait for.
     *
     * @param  array<string, mixed>  $data
     */
    private function urlFrom(array $data): string
    {
        $url = $this->findUrl($data);

        if ($url !== null) {
            return $url;
        }

        $output = $this->map($data['output'] ?? null);
        $taskId = $output['task_id'] ?? null;

        if (! is_string($taskId) || $taskId === '') {
            throw new RuntimeException($this->message($data, 'The image provider returned neither an image nor a task.'));
        }

        return $this->poll($taskId);
    }

    /**
     * Wait for a task with a growing pause, up to POLL_DEADLINE_SECONDS.
     */
    private function poll(string $taskId): string
    {
        $deadline = Carbon::now()->addSeconds(self::POLL_DEADLINE_SECONDS);
        $attempt = 0;

        while (true) {
            $pause = self::POLL_PAUSES[min($attempt, count(self::POLL_PAUSES) - 1)];
            Sleep::for($pause)->seconds();
            $attempt++;

            try {
                $data = $this->json($this->client()->get('/tasks/'.rawurlencode($taskId))->json());
            } catch (RequestException $e) {
                throw $this->failure($e);
            }

            $output = $this->map($data['output'] ?? null);
            $status = strtoupper((string) ($output['task_status'] ?? ''));

            if ($status === 'SUCCEEDED') {
                $url = $this->findUrl($data);

                if ($url === null) {
                    throw new RuntimeException('The image task succeeded but returned no image URL.');
                }

                return $url;
            }

            if (in_array($status, ['FAILED', 'CANCELED', 'UNKNOWN'], true)) {
                $reason = $output['message'] ?? $output['code'] ?? null;

                throw new RuntimeException(sprintf(
                    'The image task %s%s',
                    strtolower($status),
                    is_string($reason) && $reason !== '' ? ': '.$reason : '.',
                ));
            }

            if (Carbon::now()->greaterThanOrEqualTo($deadline)) {
                throw new RuntimeException(sprintf('The image task did not finish within %d seconds.', self::POLL_DEADLINE_SECONDS));
            }
        }
    }

    private function download(string $url): GeneratedImage
    {
        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS, $this->retryable(...))
                ->get($url);
        } catch (RequestException $e) {
            throw $this->failure($e);
        }

        $binary = $response->body();

        if ($binary === '') {
            throw new RuntimeException('The generated image could not be downloaded.');
        }

        $info = @getimagesizefromstring($binary);
        $mime = is_array($info)
            ? $info['mime']
            : strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! str_starts_with($mime, 'image/')) {
            throw new RuntimeException('The image provider returned a file that is not an image.');
        }

        return new GeneratedImage(
            binary: $binary,
            mime: $mime,
            extension: match ($mime) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            },
            width: is_array($info) ? (int) $info[0] : null,
            height: is_array($info) ? (int) $info[1] : null,
            // One image = one input unit, priced per image (spec 0007, D9).
            usage: new AiUsageInfo(1, 0, $this->model, self::PROVIDER),
        );
    }

    // ------------------------------------------------------------ helpers

    private function client(): PendingRequest
    {
        $base = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');

        return Http::baseUrl($base)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS, $this->retryable(...));
    }

    private function retryable(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError()));
    }

    /**
     * A 4xx that is not auth or rate limiting: the model does not serve this
     * endpoint (or this request shape), so the other shape is worth a try.
     */
    private function modelRejectedEndpoint(RequestException $e): bool
    {
        $status = $e->response->status();

        // Some keys (the token-plan key in spec 0004) may not call async
        // tasks at all: a 403 "does not support asynchronous calls" means the
        // sync shape is the one to use, not that the key is wrong.
        if ($status === 403) {
            return str_contains(strtolower($e->response->body()), 'asynchronous');
        }

        return $status >= 400 && $status < 500 && ! in_array($status, [401, 429], true);
    }

    private function failure(RequestException $e): RuntimeException
    {
        $data = $e->response->json();

        return new RuntimeException(sprintf(
            'The image provider answered HTTP %d: %s',
            $e->response->status(),
            $this->message(is_array($data) ? $this->map($data) : [], 'request failed'),
        ), 0, $e);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findUrl(array $data): ?string
    {
        $output = $this->map($data['output'] ?? null);

        foreach ($this->lists($output['results'] ?? null) as $result) {
            $url = $this->map($result)['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        foreach ($this->lists($output['choices'] ?? null) as $choice) {
            $message = $this->map($this->map($choice)['message'] ?? null);

            foreach ($this->lists($message['content'] ?? null) as $part) {
                $image = $this->map($part)['image'] ?? null;
                if (is_string($image) && $image !== '') {
                    return $image;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function message(array $data, string $default): string
    {
        $output = $this->map($data['output'] ?? null);

        foreach ([$data['message'] ?? null, $output['message'] ?? null, $data['code'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('The image provider returned a non-JSON response.');
        }

        return $this->map($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    private function lists(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function dimensions(string $size): string
    {
        // Settings → AI models may override either shape (API-04).
        if ($size === self::SIZE_SQUARE) {
            return $this->squareSize !== null && $this->squareSize !== '' ? $this->squareSize : self::DEFAULT_SQUARE_SIZE;
        }

        return $this->landscapeSize !== null && $this->landscapeSize !== '' ? $this->landscapeSize : self::DEFAULT_LANDSCAPE_SIZE;
    }
}
