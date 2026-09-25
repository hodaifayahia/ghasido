<?php

namespace App\Http\Controllers;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Models\RoleplayAttempt;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\RoleplayPrompt;
use App\Services\Ai\UsageMeter;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\ApiKeyring;
use App\Services\VoiceAgent\VoiceAgentProxyToken;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An OpenAI-compatible chat-completions endpoint that lets the Deepgram Voice
 * Agent use Qwen as the guest's brain without the Qwen key ever leaving the
 * server (API-02, SEC-03, RP-04; spec 0004 NOTES).
 *
 * Deepgram calls POST /voice-agent/llm/{token}/chat/completions from its own
 * servers. The token is short-lived and bound to one in-progress voice call;
 * anything else is a 403. The system prompt is always rebuilt here from the
 * scenario row — whatever system message arrives is dropped (RP-04).
 */
class VoiceAgentLlmController extends Controller
{
    private const MAX_HISTORY = 40;

    public function __invoke(
        Request $request,
        string $token,
        VoiceAgentProxyToken $tokens,
        VoiceAgentSettings $settings,
        UsageMeter $meter,
    ): Response {
        $attemptId = $tokens->verify($token);
        $attempt = $attemptId !== null ? RoleplayAttempt::query()->find($attemptId) : null;

        if ($attempt === null
            || $attempt->channel !== RoleplayAttempt::CHANNEL_VOICE_CALL
            || ! $attempt->status->acceptsTurns()) {
            return $this->error('This voice call is not active.', 403);
        }

        $scenario = $attempt->scenario;

        if ($scenario === null) {
            return $this->error('This voice call is not active.', 403);
        }

        $values = $settings->forScenario($scenario);
        $model = app(AiModelSettings::class)->fastChatModel();
        $key = app(ApiKeyring::class)->key('services.ai.key');
        $baseUrl = config('services.ai.base_url');

        if ($model === '' || $key === '') {
            return $this->error('The language model is not configured.', 503);
        }

        $user = $attempt->user;
        $stream = $request->boolean('stream');

        // The owner's Qwen credit ends the call politely too, preview or not
        // (spec 0007, D7).
        if (! app(ApiCredit::class)->isAvailable(ApiAccount::Qwen)
            || (! $attempt->is_preview && $user !== null && ! $meter->isWithinLimits($user, AiFeature::RoleplayTurn))) {
            return $this->reply(__('I am sorry, we need to finish our call here. Thank you for your help!'), $model, $stream);
        }

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => RoleplayPrompt::forVoice($scenario, $values['prompt'], $attempt->user?->english_level)],
                ...$this->conversation($request->input('messages')),
            ],
            'temperature' => $values['temperature'],
            'max_tokens' => 300,
            'stream' => false,
        ];

        if (str_contains(is_string($baseUrl) ? $baseUrl : '', 'aliyuncs.com') || config('services.ai.provider') === 'qwen') {
            $body['enable_thinking'] = false;
        }

        try {
            $response = Http::baseUrl(rtrim(is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1', '/'))
                ->withToken($key)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post('/chat/completions', $body);
        } catch (ConnectionException) {
            return $this->error('The language model could not be reached.', 502);
        }

        if (! $response->successful()) {
            return $this->error(sprintf('The language model refused the request (HTTP %d).', $response->status()), 502);
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));

        if ($text === '') {
            return $this->error('The language model returned an empty reply.', 502);
        }

        $meter->record($user, AiFeature::RoleplayTurn, new AiUsageInfo(
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
            (string) $response->json('model', $model),
            'qwen_voice_proxy',
        ), chargePoints: false);

        return $this->reply($text, $model, $stream);
    }

    /**
     * The user/assistant turns Deepgram sent, newest last; system and tool
     * messages are dropped (RP-04).
     *
     * @return list<array{role: string, content: string}>
     */
    private function conversation(mixed $messages): array
    {
        $turns = [];

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (! is_array($message) || ! in_array($message['role'] ?? null, ['user', 'assistant'], true)) {
                continue;
            }

            $content = $message['content'] ?? '';

            if (is_array($content)) {
                $content = implode(' ', array_map(
                    static fn (mixed $part): string => is_array($part) && is_string($part['text'] ?? null) ? $part['text'] : '',
                    $content,
                ));
            }

            $content = trim(is_string($content) ? $content : '');

            if ($content !== '') {
                $turns[] = ['role' => (string) $message['role'], 'content' => mb_substr($content, 0, 4000)];
            }
        }

        $turns = array_slice($turns, -self::MAX_HISTORY);

        if ($turns === [] || $turns[0]['role'] !== 'user') {
            array_unshift($turns, ['role' => 'user', 'content' => '(The employee has answered the call.)']);
        }

        return $turns;
    }

    private function reply(string $text, string $model, bool $stream): Response
    {
        $id = 'chatcmpl-'.Str::random(24);
        $created = time();

        if (! $stream) {
            return response()->json([
                'id' => $id,
                'object' => 'chat.completion',
                'created' => $created,
                'model' => $model,
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $text],
                    'finish_reason' => 'stop',
                ]],
            ]);
        }

        $chunk = static fn (array $delta, ?string $finish): string => 'data: '.json_encode([
            'id' => $id,
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $model,
            'choices' => [['index' => 0, 'delta' => (object) $delta, 'finish_reason' => $finish]],
        ])."\n\n";

        return new StreamedResponse(function () use ($chunk, $text): void {
            echo $chunk(['role' => 'assistant', 'content' => $text], null);
            echo $chunk([], 'stop');
            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['message' => $message, 'type' => 'invalid_request_error']], $status);
    }
}
