<?php

namespace App\Services\Ai;

use App\Contracts\AiEvaluation;
use App\Contracts\AiProvider;
use App\Contracts\AiReply;
use App\Contracts\AiUsageInfo;
use App\Contracts\ChecksConnection;
use App\Contracts\CourseOutline;
use App\Contracts\LessonDraft;
use App\Contracts\LexiconDraft;
use App\Contracts\ScenarioDraft;
use App\Contracts\SpeakingEvaluation;
use App\Contracts\TestQuestionsDraft;
use App\Contracts\WritingEvaluation;
use App\Enums\LexiconKind;
use App\Enums\ScenarioDifficulty;
use App\Models\AiScenario;
use App\Services\Ai\Concerns\BuildsLessonPrompts;
use App\Services\Ai\Concerns\ParsesJsonReplies;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Any chat endpoint that speaks the OpenAI Chat Completions shape (API-02,
 * API-04, SEC-03; spec 0003 Part C) — used here for Alibaba's Qwen through
 * its compatible-mode endpoint, but equally OpenAI, DeepSeek or a local model.
 *
 * Model, endpoint and key all come from config/services.php: nothing here
 * names a model, so the client swaps generations with an .env change. Every
 * request asks for a single JSON object (response_format json_object) and
 * parses it defensively, because a model reply is data from outside the trust
 * boundary.
 *
 * Request shape: POST {base_url}/chat/completions with a bearer key, body
 * {model, messages:[{role, content}], max_tokens, temperature, response_format}.
 * The response carries choices[0].message.content and a usage object
 * (prompt_tokens / completion_tokens).
 */
final class OpenAiCompatibleAiProvider implements AiProvider, ChecksConnection
{
    use BuildsLessonPrompts;
    use Concerns\BuildsAssessmentPrompts;
    use ParsesJsonReplies;

    public const PROVIDER = 'openai';

    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Generous on purpose: a whole lesson is a large object. A capped reply
     * would be truncated JSON and a failed job, not a saved cost.
     */
    private const MAX_TOKENS = 12000;

    private const TEMPERATURE = 0.6;

    private const TIMEOUT_SECONDS = 180;

    /**
     * Connecting is separate from answering: an unreachable host fails in
     * seconds and is retried, instead of eating the whole reply budget.
     */
    private const CONNECT_TIMEOUT_SECONDS = 15;

    private const RETRY_TIMES = 3;

    private const RETRY_SLEEP_MS = 2000;

    /**
     * The one instruction every role-play prompt carries, so the guest never
     * drifts into a general assistant (RP-04).
     */
    private const ROLEPLAY_GUARD = RoleplayPrompt::GUARD;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $baseUrl = null,
        private readonly string $providerLabel = self::PROVIDER,
        private readonly ?string $fastModel = null,
    ) {}

    /**
     * Qwen 3.x models are reasoning models: left on, "thinking" spends the
     * token budget and minutes before the JSON answer, and the reply can come
     * back empty. DashScope's compatible mode accepts `enable_thinking`;
     * OpenAI proper rejects unknown parameters, so it is sent to Qwen only.
     * Every model id on the DashScope host gets it (deepseek, kimi, …); one
     * that refuses it (glm-5.3) is resent without it — see complete().
     */
    private function disablesThinking(): bool
    {
        return $this->providerLabel === 'qwen'
            || str_contains((string) $this->baseUrl, 'aliyuncs.com');
    }

    /**
     * One tiny request for the Settings → AI models "Test" button: proves
     * the key, the endpoint and the model id together (API-04).
     */
    public function ping(bool $fast = false): AiReply
    {
        $result = $this->complete(
            'You are a connection check. Respond with ONLY the JSON object {"ok": true}.',
            [['role' => 'user', 'content' => 'Connection check.']],
            $fast ? $this->fastModel : null,
        );

        return new AiReply(trim($result['text']), $result['usage']);
    }

    public function roleplayReply(AiScenario $scenario, array $transcript): AiReply
    {
        $system = implode("\n\n", [
            self::ROLEPLAY_GUARD,
            $this->configuredInstruction($scenario),
            $this->scenarioBrief($scenario),
            'Respond with ONLY a JSON object of the shape {"reply": "<the guest\'s next line>"}. No markdown, no commentary.',
        ]);

        // A learner is waiting on this line, so it uses the fast model when
        // one is configured (PERF-04).
        $result = $this->complete($system, $this->conversationMessages($transcript), $this->fastModel);

        try {
            $reply = $this->string($this->decodeJson($result['text']), 'reply', '');
        } catch (RuntimeException) {
            $reply = '';
        }

        if ($reply === '') {
            // Not JSON after all: treat the whole text as the line rather
            // than fail a learner's turn over formatting.
            $reply = trim($result['text']);
        }

        if ($reply === '') {
            throw new RuntimeException('The AI provider returned an empty reply.');
        }

        return new AiReply($reply, $result['usage']);
    }

    public function evaluateRoleplay(AiScenario $scenario, array $transcript): AiEvaluation
    {
        $criteria = $scenario->criteriaKeys();

        $system = implode("\n\n", [
            'You are an encouraging English coach for hotel staff. Evaluate the EMPLOYEE\'s side of the role-play below. Reward communicative success over grammatical perfection; the tone is adult, warm and professional, never childish (RP-08, RP-09).',
            $this->configuredInstruction($scenario),
            $this->scenarioBrief($scenario),
            'Score each criterion 0-100 with a one-sentence comment. Respond with ONLY a JSON object of this exact shape: '
            .'{"criteria": {'.implode(', ', array_map(static fn (string $key): string => sprintf('"%s": {"score": <int>, "comment": "<text>"}', $key), $criteria)).'}, '
            .'"overall": <int 0-100>, "did_well": ["<text>", ...], "improve": [{"title": "<short title>", "text": "<advice>"}], '
            .'"better_expression": {"yours": "<one sentence the employee said>", "better": "<a more natural version>"}, '
            .'"key_phrase": "<one short phrase to remember, in quotation marks>", "summary_label": "<2-3 words>", "summary_text": "<one sentence>", "footnote": "<one sentence of general advice>"}',
        ]);

        $messages = [[
            'role' => 'user',
            'content' => "Transcript:\n".$this->transcriptText($transcript)."\n\nEvaluate the employee now.",
        ]];

        $result = $this->complete($system, $messages);
        $data = $this->decodeJson($result['text']);

        $rawCriteria = $data['criteria'] ?? [];
        $parsedCriteria = [];
        foreach ($criteria as $key) {
            $entry = is_array($rawCriteria) && isset($rawCriteria[$key]) && is_array($rawCriteria[$key]) ? $rawCriteria[$key] : [];
            $parsedCriteria[$key] = [
                'score' => $this->score($entry, 'score'),
                'comment' => $this->string($entry, 'comment', ''),
            ];
        }

        $overall = isset($data['overall'])
            ? $this->score($data, 'overall')
            : (int) round(array_sum(array_column($parsedCriteria, 'score')) / max(1, count($parsedCriteria)));

        $improve = [];
        foreach ($this->list($data, 'improve') as $entry) {
            if (is_array($entry)) {
                $improve[] = [
                    'title' => $this->string($entry, 'title', ''),
                    'text' => $this->string($entry, 'text', ''),
                ];
            }
        }

        $betterExpression = is_array($data['better_expression'] ?? null) ? $data['better_expression'] : [];

        return new AiEvaluation(
            criteria: $parsedCriteria,
            overall: $overall,
            didWell: $this->stringList($data, 'did_well'),
            improve: $improve,
            betterExpression: [
                'yours' => $this->string($betterExpression, 'yours', ''),
                'better' => $this->string($betterExpression, 'better', ''),
            ],
            keyPhrase: $this->string($data, 'key_phrase', ''),
            summaryLabel: $this->string($data, 'summary_label', 'Good try!'),
            summaryText: $this->string($data, 'summary_text', 'Keep practicing. You can try again.'),
            usage: $result['usage'],
            footnote: $this->string($data, 'footnote', ''),
        );
    }

    public function generateLexicon(string $english, LexiconKind $kind, string $context): LexiconDraft
    {
        $system = 'You write vocabulary entries for an English course for hotel staff in Algeria whose English level is low and whose first language is Arabic. Keep every explanation to one short, simple sentence. Examples must be realistic hotel situations. Respond with ONLY a JSON object of this exact shape: '
            .'{"arabic_meaning": "<Modern Standard Arabic>", "simple_explanation": "<one simple English sentence>", "hotel_example": "<one English sentence a guest or staff member would say>", "hotel_example_arabic": "<the example in Arabic>", "ipa": "<IPA transcription between slashes or null>", "part_of_speech": "<n|v|adj|adv|phrase or null>"}';

        $messages = [[
            'role' => 'user',
            'content' => sprintf(
                "Entry kind: %s\nEnglish: %s\nContext: %s",
                $kind->value,
                trim($english),
                $context !== '' ? $context : 'general hotel work',
            ),
        ]];

        $result = $this->complete($system, $messages);
        $data = $this->decodeJson($result['text']);

        return new LexiconDraft(
            arabicMeaning: $this->string($data, 'arabic_meaning', ''),
            simpleExplanation: $this->string($data, 'simple_explanation', ''),
            hotelExample: $this->string($data, 'hotel_example', ''),
            hotelExampleArabic: $this->string($data, 'hotel_example_arabic', ''),
            ipa: $this->nullableString($data, 'ipa'),
            partOfSpeech: $this->nullableString($data, 'part_of_speech'),
            usage: $result['usage'],
        );
    }

    public function generateScenario(string $title, string $department, ScenarioDifficulty $difficulty, string $notes = ''): ScenarioDraft
    {
        $system = 'You design AI role-play scenarios for an English course for hotel staff in Algeria whose first language is Arabic and whose English level is low. Given a title, a hotel department and a difficulty, write ONE complete, realistic scenario in which the guest speaks simple, natural English. The employee is practising, so keep the language professional and achievable. Respond with ONLY a JSON object of this exact shape: '
            .'{"description": "<one or two sentences summarising the scenario>", '
            .'"situation": "<2-3 sentences describing the setting the employee faces>", '
            .'"ai_role": "<the guest character and mood, written as \'You are ...\'>", '
            .'"employee_role": "<the employee role, written as \'You are ...\'>", '
            .'"objective": "<one sentence: what the employee must achieve>", '
            .'"goals": ["<short goal>", "<short goal>", "<short goal>", "<short goal>"], '
            .'"useful_phrases": ["<a phrase the employee can use>", "<phrase>", "<phrase>", "<phrase>", "<phrase>"], '
            .'"quote": "<a short opening line the guest might say, in quotation marks>", '
            .'"tip": "<one short coaching tip for the employee>"}';

        $messages = [[
            'role' => 'user',
            'content' => sprintf(
                "Title: %s\nDepartment: %s\nDifficulty: %s\nNotes: %s",
                trim($title),
                $department !== '' ? $department : 'hotel front office',
                $difficulty->value,
                $notes !== '' ? trim($notes) : 'none',
            ),
        ]];

        $result = $this->complete($system, $messages);
        $data = $this->decodeJson($result['text']);

        return new ScenarioDraft(
            description: $this->string($data, 'description', ''),
            situation: $this->string($data, 'situation', ''),
            aiRole: $this->string($data, 'ai_role', ''),
            employeeRole: $this->string($data, 'employee_role', ''),
            objective: $this->string($data, 'objective', ''),
            goals: $this->stringList($data, 'goals'),
            usefulPhrases: $this->stringList($data, 'useful_phrases'),
            quote: $this->nullableString($data, 'quote'),
            tip: $this->nullableString($data, 'tip'),
            usage: $result['usage'],
        );
    }

    public function generateLesson(string $topic, string $department, string $level, string $notes = ''): LessonDraft
    {
        $result = $this->complete($this->lessonSystemPrompt(), $this->lessonMessages($topic, $department, $level, $notes));

        return $this->lessonDraftFrom($this->decodeJson($result['text']), $topic, $result['usage']);
    }

    public function generateCourseOutline(string $brief, string $department, string $level, int $lessonCount): CourseOutline
    {
        $lessonCount = max(1, $lessonCount);
        $result = $this->complete($this->outlineSystemPrompt($lessonCount), $this->outlineMessages($brief, $department, $level));

        return $this->outlineFrom($this->decodeJson($result['text']), $brief, $lessonCount, $result['usage']);
    }

    public function evaluateWriting(array $item, string $answer): WritingEvaluation
    {
        $system = 'You are an encouraging English coach for hotel staff. Evaluate the written reply below against the task. Reward getting the message across over perfect grammar; the tone is adult, warm and professional. Respond with ONLY a JSON object of this exact shape: '
            .'{"criteria": {"task_completion": {"score": <int 0-100>, "comment": "<text>"}, "accuracy": {"score": <int>, "comment": "<text>"}, "politeness": {"score": <int>, "comment": "<text>"}, "clarity": {"score": <int>, "comment": "<text>"}}, '
            .'"better_answer": "<a model reply of similar length>", "summary": "<one encouraging sentence>"}';

        $task = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $messages = [[
            'role' => 'user',
            'content' => sprintf(
                "Task (JSON):\n%s\n\nEmployee's answer (verbatim):\n%s",
                $task === false ? '{}' : $task,
                $answer,
            ),
        ]];

        $result = $this->complete($system, $messages);
        $data = $this->decodeJson($result['text']);

        $rawCriteria = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        $criteria = [];
        foreach (['task_completion', 'accuracy', 'politeness', 'clarity'] as $key) {
            $entry = is_array($rawCriteria[$key] ?? null) ? $rawCriteria[$key] : [];
            $criteria[$key] = [
                'score' => $this->score($entry, 'score'),
                'comment' => $this->string($entry, 'comment', ''),
            ];
        }

        return new WritingEvaluation(
            criteria: $criteria,
            betterAnswer: $this->string($data, 'better_answer', ''),
            summary: $this->string($data, 'summary', ''),
            usage: $result['usage'],
        );
    }

    public function evaluateSpeaking(array $item, string $transcript): SpeakingEvaluation
    {
        $result = $this->complete($this->speakingSystemPrompt(), $this->speakingMessages($item, $transcript));

        return $this->parseSpeakingEvaluation($this->decodeJson($result['text']), $result['usage']);
    }

    public function generateTestQuestions(string $department, string $level, array $skills, string $notes = '', array $avoid = []): TestQuestionsDraft
    {
        $result = $this->complete(
            $this->testQuestionsSystemPrompt(),
            $this->testQuestionsMessages($department, $level, $skills, $notes, $avoid),
        );

        return $this->parseTestQuestions($this->decodeJson($result['text']), $skills, $result['usage']);
    }

    // ------------------------------------------------------------ transport

    /**
     * One Chat Completions call. Returns the reply text and the usage the API
     * reported.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, usage: AiUsageInfo}
     */
    private function complete(string $system, array $messages, ?string $model = null): array
    {
        $model = $model !== null && $model !== '' ? $model : $this->model;

        if ($model === '') {
            throw new RuntimeException('AI_MODEL is not configured (config services.ai.model).');
        }

        if ($this->apiKey === '') {
            throw new RuntimeException('AI_KEY is not configured (config services.ai.key).');
        }

        $body = [
            'model' => $model,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'response_format' => ['type' => 'json_object'],
        ];

        if ($this->disablesThinking()) {
            $body['enable_thinking'] = false;
        }

        try {
            $response = $this->client()->post('/chat/completions', $body)->throw();
        } catch (RequestException $e) {
            // Some non-Qwen models on DashScope refuse the switch outright
            // (measured 2026-09-23: glm-5.3 answers 400 "The value of the
            // enable_thinking parameter is restricted to True"). Resend once
            // without it rather than fail the job.
            if (! isset($body['enable_thinking']) || $e->response->status() !== 400 || ! str_contains($e->response->body(), 'enable_thinking')) {
                throw $e;
            }

            unset($body['enable_thinking']);
            $response = $this->client()->post('/chat/completions', $body)->throw();
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('The AI provider returned a non-JSON response.');
        }

        $choices = $this->list($data, 'choices');
        $first = $choices[0] ?? null;
        $message = is_array($first) && is_array($first['message'] ?? null) ? $first['message'] : [];
        $text = $this->string($message, 'content', '');

        if ($text === '') {
            throw new RuntimeException('The AI provider returned an empty reply.');
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $model = $this->string($data, 'model', $model);

        return [
            'text' => $text,
            'usage' => new AiUsageInfo(
                promptTokens: $this->int($usage, 'prompt_tokens'),
                completionTokens: $this->int($usage, 'completion_tokens'),
                model: $model,
                provider: $this->providerLabel,
            ),
        ];
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');

        return Http::baseUrl($baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(
                self::RETRY_TIMES,
                self::RETRY_SLEEP_MS,
                // Retry what a retry can fix (unreachable host, rate limit,
                // provider outage); a 400/401 fails at once with its message.
                fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
            );
    }

    // -------------------------------------------------------------- prompts

    /**
     * The scenario brief, assembled from the ai_scenarios row and nothing the
     * client sent (RP-04).
     */
    private function scenarioBrief(AiScenario $scenario): string
    {
        return RoleplayPrompt::brief($scenario);
    }

    private function configuredInstruction(AiScenario $scenario): string
    {
        return RoleplayPrompt::configuredInstruction($scenario);
    }

    /**
     * Map the stored transcript onto chat turns: the guest is the assistant,
     * the employee is the user. An empty or guest-first transcript gets a
     * neutral opener so the model always has a user turn to answer.
     *
     * @param  list<array{role: string, text: string, at?: string}>  $transcript
     * @return list<array{role: string, content: string}>
     */
    private function conversationMessages(array $transcript): array
    {
        $messages = [];

        foreach ($transcript as $turn) {
            $text = trim($turn['text']);
            if ($text === '') {
                continue;
            }

            $messages[] = [
                'role' => $turn['role'] === 'guest' ? 'assistant' : 'user',
                'content' => $text,
            ];
        }

        if ($messages === [] || $messages[0]['role'] !== 'user') {
            array_unshift($messages, [
                'role' => 'user',
                'content' => '(The employee is ready. Begin the conversation as the guest.)',
            ]);
        }

        $last = $messages[count($messages) - 1];
        if ($last['role'] === 'assistant') {
            $messages[] = [
                'role' => 'user',
                'content' => '(Continue as the guest.)',
            ];
        }

        return $messages;
    }

    /**
     * @param  list<array{role: string, text: string, at?: string}>  $transcript
     */
    private function transcriptText(array $transcript): string
    {
        return implode("\n", array_map(
            static fn (array $turn): string => sprintf('%s: %s', $turn['role'] === 'guest' ? 'Guest' : 'Employee', trim($turn['text'])),
            $transcript,
        ));
    }
}
