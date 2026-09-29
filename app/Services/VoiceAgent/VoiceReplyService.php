<?php

namespace App\Services\VoiceAgent;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Models\AiScenario;
use App\Models\AudioClip;
use App\Models\RoleplayAttempt;
use App\Models\VoiceLine;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\RoleplayPrompt;
use App\Services\Ai\UsageMeter;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\ApiKeyring;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * One turn of the fast voice engine (RP-03, RP-04, RP-11, AIL-03, AIL-04;
 * spec 0009): the employee's finished sentence in, the guest's next line
 * and its recording out.
 *
 * The AI is shown the scenario's recorded lines and decides itself whether
 * one of them is the natural answer (reused: instant, no speech bill) or a
 * new line is needed (voiced once, then stored for the next call). The
 * prompt is always rebuilt here from the scenario row (RP-04).
 *
 * This runs inside the request, not a queued job: a live conversation
 * cannot wait for a queue worker. It is the one AI call of the platform that
 * does, like the voice agent's Qwen proxy before it (spec 0004), and it is
 * bounded by short timeouts.
 *
 * Both sides of the exchange are stored with the call's transcript under
 * sequence numbers derived from the turn (employee 1 + 2n, guest 2 + 2n),
 * so a retried or speculative request replaces its own turn and never adds
 * a line twice (PROG-04, DATA-05).
 */
final class VoiceReplyService
{
    public const PROVIDER_LABEL = 'qwen_voice_pipeline';

    public const SOURCE_NEW = 'new';

    public const SOURCE_REUSED = 'reused';

    /** The line the guest ends a call with when the allowance is spent. */
    public const CLOSING_LINE = 'I am sorry, we need to finish our call here. Thank you for your help!';

    private const MAX_HISTORY = 40;

    private const MAX_TOKENS = 160;

    public function __construct(
        private readonly UsageMeter $meter,
        private readonly VoiceAgentSettings $settings,
        private readonly VoiceLineBank $bank,
    ) {}

    public static function employeeSeq(int $turn): int
    {
        return 1 + 2 * $turn;
    }

    public static function guestSeq(int $turn): int
    {
        return 2 + 2 * $turn;
    }

    /**
     * @return array{turn: int, rev: int, text: string, audioUrl: string|null, source: string, limitReached: bool, stale: bool}
     *
     * @throws RuntimeException when the language model could not answer (the employee's line is still stored)
     */
    public function reply(RoleplayAttempt $attempt, AiScenario $scenario, int $turn, int $rev, string $employeeText): array
    {
        $values = $this->settings->forScenario($scenario);
        // The fast engine always speaks with the Aura-2 voice: it is the one
        // the recordings are made in (spec 0009).
        $voice = $values['speakModel'];
        $user = $attempt->user;
        $employeeText = trim($employeeText);

        if ($this->limitReached($attempt)) {
            $line = $this->bank->lineFor(null, $voice, self::CLOSING_LINE, false, $user);

            return $this->finish($attempt, $turn, $rev, $employeeText, $line, false, true);
        }

        $history = $this->history($attempt, $turn);
        $candidates = $values['reuseStoredLines'] ? $this->bank->candidates($scenario, $voice) : new Collection;

        try {
            $result = $this->complete($scenario, $attempt, $history, $employeeText, $candidates, $values);
        } catch (RuntimeException $e) {
            // The employee's words are data: keep them even when the guest
            // could not answer (DATA-05).
            $this->store($attempt, $turn, $rev, $employeeText, null);

            throw $e;
        }

        if ($result['usage'] !== null) {
            $this->meter->record($user, AiFeature::RoleplayTurn, $result['usage'], chargePoints: false);
        }

        $decision = $this->decide($result['text'], $candidates);

        if ($decision['line'] !== null) {
            return $this->finish($attempt, $turn, $rev, $employeeText, $decision['line'], true, false);
        }

        $line = $this->bank->lineFor($scenario, $voice, $decision['say'], $decision['keep'], $user);

        return $this->finish($attempt, $turn, $rev, $employeeText, $line, false, false);
    }

    /**
     * @return array{turn: int, rev: int, text: string, audioUrl: string|null, source: string, limitReached: bool, stale: bool}
     */
    private function finish(RoleplayAttempt $attempt, int $turn, int $rev, string $employeeText, VoiceLine $line, bool $reused, bool $limitReached): array
    {
        $stored = $this->store($attempt, $turn, $rev, $employeeText, $line);

        if ($stored) {
            $this->bank->markUsed($line, $reused);
        }

        return [
            'turn' => $turn,
            'rev' => $rev,
            'text' => $line->text,
            'audioUrl' => $line->url(),
            'source' => $reused ? self::SOURCE_REUSED : self::SOURCE_NEW,
            'limitReached' => $limitReached,
            'stale' => ! $stored,
        ];
    }

    private function limitReached(RoleplayAttempt $attempt): bool
    {
        $user = $attempt->user;
        $credit = app(ApiCredit::class);

        if (! $credit->isAvailable(ApiAccount::Qwen) || ! $credit->isAvailable(ApiAccount::Deepgram)) {
            return true;
        }

        return ! $attempt->is_preview
            && $user !== null
            && (! $this->meter->isWithinLimits($user, AiFeature::RoleplayTurn) || ! $this->meter->canContinueVoice($attempt));
    }

    /**
     * Write this turn's two lines, replacing an earlier request for the
     * same turn. A request older than the one already stored for the turn
     * (a speculative reply overtaken by the confirmed one) changes nothing.
     */
    private function store(RoleplayAttempt $attempt, int $turn, int $rev, string $employeeText, ?VoiceLine $line): bool
    {
        $revKey = sprintf('voice-reply:%d:%d', $attempt->id, $turn);

        return DB::transaction(function () use ($attempt, $turn, $rev, $employeeText, $line, $revKey): bool {
            /** @var RoleplayAttempt $locked */
            $locked = RoleplayAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->acceptsTurns()) {
                return false;
            }

            $latest = Cache::get($revKey);

            if (is_int($latest) && $latest > $rev) {
                return false;
            }

            Cache::put($revKey, $rev, now()->addHours(2));

            $employeeSeq = self::employeeSeq($turn);
            $guestSeq = self::guestSeq($turn);
            $at = Date::now()->toIso8601String();

            $turns = array_values(array_filter(
                $locked->transcript,
                static fn (array $entry): bool => ! in_array($entry['seq'] ?? null, [$employeeSeq, $guestSeq], true),
            ));

            if ($employeeText !== '') {
                $turns[] = ['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => $employeeText, 'seq' => $employeeSeq, 'at' => $at];
            }

            if ($line !== null) {
                $guest = ['role' => RoleplayAttempt::ROLE_GUEST, 'text' => $line->text, 'seq' => $guestSeq, 'at' => $at];
                $mediaId = $line->audioClip?->media_asset_id;

                if ($mediaId !== null) {
                    // The admin review plays exactly what the learner heard.
                    $guest['audio_media_id'] = $mediaId;
                }

                $turns[] = $guest;
            }

            usort($turns, static fn (array $a, array $b): int => ($a['seq'] ?? PHP_INT_MAX) <=> ($b['seq'] ?? PHP_INT_MAX));

            $locked->transcript = $turns;
            $locked->save();
            $attempt->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * The conversation before this turn, oldest first.
     *
     * @return list<array{role: string, text: string, at?: string}>
     */
    private function history(RoleplayAttempt $attempt, int $turn): array
    {
        $before = self::employeeSeq($turn);
        $turns = array_values(array_filter(
            $attempt->transcript,
            static fn (array $entry): bool => ($entry['seq'] ?? PHP_INT_MAX) < $before,
        ));

        usort($turns, static fn (array $a, array $b): int => ($a['seq'] ?? 0) <=> ($b['seq'] ?? 0));

        return array_slice(array_map(
            static fn (array $entry): array => ['role' => $entry['role'], 'text' => $entry['text']],
            $turns,
        ), -self::MAX_HISTORY);
    }

    /**
     * @param  list<array{role: string, text: string, at?: string}>  $history
     * @param  Collection<int, VoiceLine>  $candidates
     * @param  array<string, mixed>  $values
     * @return array{text: string, usage: AiUsageInfo|null}
     */
    private function complete(AiScenario $scenario, RoleplayAttempt $attempt, array $history, string $employeeText, Collection $candidates, array $values): array
    {
        $env = config('services.ai.provider');
        $provider = app(AiModelSettings::class)->provider('ai', is_string($env) && trim($env) !== '' ? trim($env) : 'fake');

        if ($provider === 'fake') {
            // No network in tests or with the fake switch on (API-04).
            return ['text' => json_encode(['say' => 'Thank you. Could you help me with my booking, please?', 'keep' => true]) ?: '', 'usage' => null];
        }

        $model = app(AiModelSettings::class)->fastChatModel();
        $key = app(ApiKeyring::class)->key('services.ai.key');
        $baseUrl = config('services.ai.base_url');
        $baseUrl = is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1';

        if ($model === '' || trim($key) === '') {
            throw new RuntimeException(__('The language model is not configured.'));
        }

        $pacing = [...$history, ['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => $employeeText]];

        $messages = [['role' => 'system', 'content' => implode("\n\n", array_filter([
            RoleplayPrompt::forVoice($scenario, (string) $values['prompt'], $attempt->user?->english_level),
            RoleplayPrompt::pacingLine($scenario, $pacing),
            $this->bankInstruction($candidates),
        ]))]];

        foreach ($pacing as $entry) {
            $messages[] = [
                'role' => $entry['role'] === RoleplayAttempt::ROLE_EMPLOYEE ? 'user' : 'assistant',
                'content' => mb_substr($entry['text'], 0, 2000),
            ];
        }

        if ($messages[1]['role'] !== 'user') {
            // Some OpenAI-compatible hosts want the first turn to be the user's.
            array_splice($messages, 1, 0, [['role' => 'user', 'content' => '(The employee has answered the call.)']]);
        }

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) $values['temperature'],
            'max_tokens' => self::MAX_TOKENS,
            'stream' => false,
        ];

        if (str_contains($baseUrl, 'aliyuncs.com') || $provider === 'qwen') {
            // Qwen 3.x reasons first unless told not to: seconds of silence.
            $body['enable_thinking'] = false;
        }

        $response = $this->post($baseUrl, $key, $body);

        if ($response->status() === 400 && isset($body['enable_thinking']) && str_contains($response->body(), 'enable_thinking')) {
            // Some models on the same host refuse the switch (spec 0004).
            unset($body['enable_thinking']);
            $response = $this->post($baseUrl, $key, $body);
        }

        if ($response->status() === 429 && str_contains($response->body(), 'quota')) {
            // Seen live 2026-09-26: "Your token-plan quota has been
            // exhausted." Saying it again cannot help; say who can, and
            // send the next calls to the voice agent meanwhile.
            VoiceAgentSettings::markQwenQuotaExhausted();

            throw new RuntimeException(__('The AI text service has run out of quota. Please tell your administrator.'));
        }

        if (! $response->successful()) {
            throw new RuntimeException(__('The guest could not answer (HTTP :status). Please say it again.', ['status' => $response->status()]));
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));

        if ($text === '') {
            throw new RuntimeException(__('The guest could not answer. Please say it again.'));
        }

        return ['text' => $text, 'usage' => new AiUsageInfo(
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
            (string) $response->json('model', $model),
            self::PROVIDER_LABEL,
        )];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function post(string $baseUrl, string $key, array $body): Response
    {
        try {
            return $this->client($baseUrl, $key)->post('/chat/completions', $body);
        } catch (ConnectionException) {
            throw new RuntimeException(__('The guest could not be reached. Please say it again.'));
        }
    }

    private function client(string $baseUrl, string $key): PendingRequest
    {
        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withToken(trim($key))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 150, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);
    }

    /**
     * The reply contract, with the recorded lines the AI may reuse.
     *
     * @param  Collection<int, VoiceLine>  $candidates
     */
    private function bankInstruction(Collection $candidates): string
    {
        $keep = 'Set "keep" to true when the new line is general enough to say again in another call of this scenario, and to false when it contains a name, a number or any detail the employee gave.';

        if ($candidates->isEmpty()) {
            return 'Respond with ONLY a JSON object of the shape {"say": "<the guest\'s next line, read aloud>", "keep": true|false}. No markdown, no commentary. '.$keep;
        }

        $list = $candidates->values()->map(
            static fn (VoiceLine $line, int $index): string => sprintf('%d. %s', $index + 1, json_encode($line->text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        )->implode("\n");

        return implode("\n", [
            'Recorded lines: these guest lines already have a recorded voice. Reusing one plays instantly and costs nothing.',
            $list,
            'If one recorded line is exactly what the guest would naturally say next (it answers what the employee just said and fits the conversation and the learner\'s level), reuse it. Otherwise write a new line. Never reuse a line you already said in this call unless the employee asked you to repeat.',
            'Respond with ONLY a JSON object, either {"reuse": <number of the recorded line>} or {"say": "<the guest\'s next line, read aloud>", "keep": true|false}. No markdown, no commentary. '.$keep,
        ]);
    }

    /**
     * Read the AI's choice: a recorded line, or a new line to voice.
     *
     * @param  Collection<int, VoiceLine>  $candidates
     * @return array{line: VoiceLine|null, say: string, keep: bool}
     */
    private function decide(string $raw, Collection $candidates): array
    {
        $data = self::decodeJson($raw);

        if ($data !== null) {
            $reuse = $data['reuse'] ?? null;

            if (is_numeric($reuse)) {
                $line = $candidates->values()->get((int) $reuse - 1);

                if ($line instanceof VoiceLine) {
                    return ['line' => $line, 'say' => $line->text, 'keep' => true];
                }
            }

            $say = $data['say'] ?? $data['reply'] ?? null;

            if (is_string($say) && trim($say) !== '') {
                return ['line' => null, 'say' => self::clean($say), 'keep' => ($data['keep'] ?? false) === true];
            }
        }

        // Not the JSON asked for: say the text itself rather than fail the
        // learner's turn, and keep it out of the bank.
        $say = self::clean($raw);

        if ($say === '') {
            throw new RuntimeException(__('The guest could not answer. Please say it again.'));
        }

        return ['line' => null, 'say' => $say, 'keep' => false];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Plain spoken text: no markdown, quotes or stage directions.
     */
    private static function clean(string $text): string
    {
        $text = preg_replace(['/\*+|_{2,}|`+|#+\s*/u', '/\([^)]*\)|\[[^\]]*\]/u'], '', $text) ?? $text;

        return mb_substr(trim(AudioClip::normalise($text), " \t\n\r\0\x0B\"'"), 0, 500);
    }
}
