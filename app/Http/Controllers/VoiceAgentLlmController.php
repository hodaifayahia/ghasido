<?php

namespace App\Http\Controllers;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\RoleplayPrompt;
use App\Services\Ai\UsageMeter;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\ApiKeyring;
use App\Services\VoiceAgent\VoiceAgentProxyToken;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
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
            return $this->reply('I am sorry, we need to finish our call here. Thank you for your help!', $model, $stream);
        }

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => RoleplayPrompt::forVoice($scenario, $values['prompt'], $attempt->user?->english_level)],
                ...$this->conversation($request->input('messages')),
            ],
            'temperature' => $values['temperature'],
            'max_tokens' => 300,
            'stream' => $stream,
        ];

        if ($stream) {
            // The usage arrives in a last chunk, so the call is still metered.
            $body['stream_options'] = ['include_usage' => true];
        }

        if (str_contains(is_string($baseUrl) ? $baseUrl : '', 'aliyuncs.com') || config('services.ai.provider') === 'qwen') {
            $body['enable_thinking'] = false;
        }

        try {
            $response = Http::baseUrl(rtrim(is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1', '/'))
                ->withToken($key)
                ->accept($stream ? 'text/event-stream' : 'application/json')
                ->asJson()
                ->connectTimeout(10)
                ->timeout(30)
                // Streamed through as it arrives: Deepgram starts speaking
                // on the first words instead of the whole reply (spec 0009).
                ->withOptions(['stream' => $stream])
                ->post('/chat/completions', $body);
        } catch (ConnectionException) {
            return $this->error('The language model could not be reached.', 502);
        }

        if ($response->status() === 429 && str_contains($response->body(), 'quota')) {
            // Qwen's quota is spent: the guest closes the call politely and
            // the next calls run on Deepgram's own model (spec 0009).
            VoiceAgentSettings::markQwenQuotaExhausted();

            return $this->reply('I am sorry, we need to finish our call here. Thank you for your help!', $model, $stream);
        }

        if (! $response->successful()) {
            return $this->error(sprintf('The language model refused the request (HTTP %d).', $response->status()), 502);
        }

        if ($stream) {
            return $this->relay($response, $model, $user, $meter);
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
     * Relay Qwen's token stream to Deepgram as it arrives, re-shaped into
     * OpenAI chunks, so the guest starts speaking on the first words rather
     * than after the whole reply (spec 0009). A host that ignored `stream`
     * and answered with one JSON body is relayed as a single chunk.
     */
    private function relay(ClientResponse $response, string $model, ?User $user, UsageMeter $meter): StreamedResponse
    {
        $id = 'chatcmpl-'.Str::random(24);
        $created = time();
        $body = $response->toPsrResponse()->getBody();

        return new StreamedResponse(function () use ($body, $id, $created, $model, $user, $meter): void {
            $started = false;
            $emit = static function (array $delta, ?string $finish) use ($id, $created, $model): void {
                echo 'data: '.json_encode([
                    'id' => $id,
                    'object' => 'chat.completion.chunk',
                    'created' => $created,
                    'model' => $model,
                    'choices' => [['index' => 0, 'delta' => (object) $delta, 'finish_reason' => $finish]],
                ])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };
            $say = static function (string $piece) use (&$started, $emit): void {
                $emit($started ? ['content' => $piece] : ['role' => 'assistant', 'content' => $piece], null);
                $started = true;
            };

            $raw = '';
            $buffer = '';
            $usage = null;

            while (! $body->eof()) {
                $chunk = $body->read(1024);
                $raw .= $chunk;
                $buffer .= $chunk;

                while (($end = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $end));
                    $buffer = substr($buffer, $end + 1);

                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $event = json_decode(trim(substr($line, 5)), true);

                    if (! is_array($event)) {
                        continue;
                    }

                    if (is_array($event['usage'] ?? null)) {
                        $usage = $event['usage'];
                    }

                    $piece = $event['choices'][0]['delta']['content'] ?? null;

                    if (is_string($piece) && $piece !== '') {
                        $say($piece);
                    }
                }
            }

            if (! $started) {
                $whole = json_decode($raw, true);
                $text = is_array($whole) ? trim((string) data_get($whole, 'choices.0.message.content', '')) : '';
                $usage = is_array($whole) && is_array($whole['usage'] ?? null) ? $whole['usage'] : $usage;
                // Never leave Deepgram with nothing to say.
                $say($text !== '' ? $text : 'Sorry, could you say that again, please?');
            }

            $emit([], 'stop');
            echo "data: [DONE]\n\n";
            flush();

            $meter->record($user, AiFeature::RoleplayTurn, new AiUsageInfo(
                (int) ($usage['prompt_tokens'] ?? 0),
                (int) ($usage['completion_tokens'] ?? 0),
                $model,
                'qwen_voice_proxy',
            ), chargePoints: false);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
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
