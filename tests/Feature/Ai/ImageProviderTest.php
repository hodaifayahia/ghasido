<?php

namespace Tests\Feature\Ai;

use App\Contracts\ImageProvider;
use App\Services\Images\DashScopeImageProvider;
use App\Services\Images\FakeImageProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * The image providers (GEN-01, API-04; spec 0004 NOTES "DashScope image
 * task"): the async task API with polling, a FAILED task, the sync fallback
 * for models that reject the task endpoint, and the no-network fake.
 */
class ImageProviderTest extends TestCase
{
    use RefreshDatabase;

    private const string BASE = 'https://dashscope.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function png(): string
    {
        return (new FakeImageProvider)->generate('a test picture')->binary;
    }

    private function provider(): DashScopeImageProvider
    {
        return new DashScopeImageProvider('test-key', 'qwen-image-3.0-pro', self::BASE);
    }

    public function test_the_async_task_is_created_polled_and_its_image_downloaded()
    {
        Http::fake([
            'dashscope.test/api/v1/services/aigc/text2image/image-synthesis' => Http::response([
                'output' => ['task_id' => 'task-1', 'task_status' => 'PENDING'],
            ]),
            'dashscope.test/api/v1/tasks/task-1' => Http::sequence()
                ->push(['output' => ['task_id' => 'task-1', 'task_status' => 'RUNNING']])
                ->push(['output' => ['task_id' => 'task-1', 'task_status' => 'SUCCEEDED', 'results' => [['url' => 'https://oss.test/result.png']]]]),
            'oss.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $image = $this->provider()->generate('A receptionist greets a guest', ImageProvider::SIZE_LANDSCAPE);

        $this->assertSame('image/png', $image->mime);
        $this->assertSame('png', $image->extension);
        $this->assertGreaterThan(0, strlen($image->binary));
        $this->assertSame('qwen-image-3.0-pro', $image->usage->model);

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/services/aigc/text2image/image-synthesis')
                && $request->hasHeader('X-DashScope-Async', 'enable')
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'qwen-image-3.0-pro'
                && $request['input']['prompt'] === 'A receptionist greets a guest'
                && $request['parameters']['size'] === '1664*928'
                && $request['parameters']['watermark'] === false;
        });
        Http::assertSentCount(4);
        Sleep::assertSleptTimes(2);
    }

    public function test_a_failed_task_surfaces_its_message()
    {
        Http::fake([
            'dashscope.test/api/v1/services/aigc/text2image/image-synthesis' => Http::response([
                'output' => ['task_id' => 'task-2', 'task_status' => 'PENDING'],
            ]),
            'dashscope.test/api/v1/tasks/task-2' => Http::response([
                'output' => ['task_id' => 'task-2', 'task_status' => 'FAILED', 'code' => 'DataInspectionFailed', 'message' => 'Input data may contain inappropriate content.'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Input data may contain inappropriate content.');

        $this->provider()->generate('Something', ImageProvider::SIZE_SQUARE);
    }

    public function test_a_model_that_rejects_the_task_endpoint_falls_back_to_the_sync_shape()
    {
        Http::fake([
            'dashscope.test/api/v1/services/aigc/text2image/image-synthesis' => Http::response([
                'code' => 'InvalidParameter',
                'message' => 'Model not supported by this API.',
            ], 400),
            'dashscope.test/api/v1/services/aigc/multimodal-generation/generation' => Http::response([
                'output' => ['choices' => [['message' => ['role' => 'assistant', 'content' => [['image' => 'https://oss.test/sync.png']]]]]],
            ]),
            'oss.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $image = $this->provider()->generate('A hotel towel', ImageProvider::SIZE_SQUARE);

        $this->assertSame('image/png', $image->mime);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/multimodal-generation/generation')
            && $request['input']['messages'][0]['content'][0]['text'] === 'A hotel towel'
            && $request['parameters']['size'] === '1328*1328');
        Sleep::assertNeverSlept();
    }

    public function test_a_key_that_may_not_call_async_tasks_uses_the_sync_shape()
    {
        Http::fake([
            'dashscope.test/api/v1/services/aigc/text2image/image-synthesis' => Http::response([
                'code' => 'AccessDenied',
                'message' => 'current user api does not support asynchronous calls',
            ], 403),
            'dashscope.test/api/v1/services/aigc/multimodal-generation/generation' => Http::response([
                'output' => ['choices' => [['message' => ['content' => [['image' => 'https://oss.test/sync.png']]]]]],
            ]),
            'oss.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertSame('image/png', $this->provider()->generate('A lobby')->mime);
        Http::assertSentCount(3);
    }

    public function test_an_auth_error_fails_at_once_without_the_fallback()
    {
        Http::fake([
            'dashscope.test/*' => Http::response(['code' => 'InvalidApiKey', 'message' => 'Invalid API-key provided.'], 401),
        ]);

        try {
            $this->provider()->generate('Anything');
            $this->fail('An auth error must surface.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringContainsString('Invalid API-key provided.', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_a_task_that_never_finishes_times_out()
    {
        Http::fake([
            'dashscope.test/api/v1/services/aigc/text2image/image-synthesis' => Http::response(['output' => ['task_id' => 'slow', 'task_status' => 'PENDING']]),
            'dashscope.test/api/v1/tasks/slow' => Http::response(['output' => ['task_id' => 'slow', 'task_status' => 'RUNNING']]),
        ]);

        // Every fake pause moves the clock, so the deadline is reached.
        Sleep::whenFakingSleep(fn ($duration) => $this->travel((int) $duration->totalSeconds)->seconds());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not finish within');

        $this->provider()->generate('A lobby');
    }

    public function test_the_fake_provider_draws_a_valid_png_and_is_the_default_binding()
    {
        config()->set('services.ai.image_provider', 'fake');

        $provider = app(ImageProvider::class);
        $this->assertInstanceOf(FakeImageProvider::class, $provider);

        $image = $provider->generate('A key card', ImageProvider::SIZE_SQUARE);
        $info = getimagesizefromstring($image->binary);

        $this->assertIsArray($info);
        $this->assertSame('image/png', $info['mime']);
    }

    public function test_qwen_resolves_the_dashscope_provider()
    {
        config()->set('services.ai.image_provider', 'qwen');
        config()->set('services.ai.image_key', 'k');

        $this->assertInstanceOf(DashScopeImageProvider::class, app(ImageProvider::class));
    }
}
