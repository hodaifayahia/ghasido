<?php

namespace App\Services\Ai;

use App\Contracts\AiEvaluation;
use App\Contracts\AiProvider;
use App\Contracts\AiReply;
use App\Contracts\AiUsageInfo;
use App\Contracts\ChecksConnection;
use App\Contracts\CoachingSummary;
use App\Contracts\CourseOutline;
use App\Contracts\DashboardBriefingDraft;
use App\Contracts\LessonDraft;
use App\Contracts\LexiconDraft;
use App\Contracts\PronunciationCoaching;
use App\Contracts\PronunciationGuideDraft;
use App\Contracts\ReminderDraft;
use App\Contracts\ScenarioDraft;
use App\Contracts\SpeakingEvaluation;
use App\Contracts\TestQuestionsDraft;
use App\Contracts\TextTranslationDraft;
use App\Contracts\WritingEvaluation;
use App\Enums\Accent;
use App\Enums\EnglishLevel;
use App\Enums\LexiconKind;
use App\Enums\ScenarioDifficulty;
use App\Models\AiScenario;
use App\Services\Ai\Concerns\BuildsBriefingPrompts;
use App\Services\Ai\Concerns\BuildsCoachingPrompts;
use App\Services\Ai\Concerns\BuildsLessonPrompts;
use App\Services\Ai\Concerns\BuildsPronunciationPrompts;
use App\Services\Ai\Concerns\BuildsReminderPrompts;
use App\Services\Ai\Concerns\BuildsRoleplayPrompts;
use App\Services\Ai\Concerns\BuildsTranslationPrompts;
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
    use BuildsBriefingPrompts;
    use BuildsCoachingPrompts;
    use BuildsLessonPrompts;
    use BuildsPronunciationPrompts;
    use BuildsReminderPrompts;
    use BuildsRoleplayPrompts;
    use BuildsTranslationPrompts;
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

    /**
     * Judging runs cool, so the same answer earns the same score from one
     * run to the next (AIE-04; spec 0005 §2.4). Conversation stays warmer.
     */
    private const EVALUATION_TEMPERATURE = 0.1;

    private const TIMEOUT_SECONDS = 180;

    /**
     * Connecting is separate from answering: an unreachable host fails in
     * seconds and is retried, instead of eating the whole reply budget.
     */
    private const CONNECT_TIMEOUT_SECONDS = 15;

    private const RETRY_TIMES = 3;

    private const RETRY_SLEEP_MS = 2000;

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

    public function roleplayReply(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiReply
    {
        $system = RoleplayPrompt::replySystem($scenario, $transcript, $level);

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

    public function evaluateRoleplay(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiEvaluation
    {
        $criteria = $scenario->criteriaKeys();

        $result = $this->complete(
            RoleplayPrompt::evaluationSystem($scenario, $criteria, $level),
            $this->evaluationMessages($transcript),
            temperature: self::EVALUATION_TEMPERATURE,
        );

        return $this->roleplayEvaluationFrom($this->decodeJson($result['text']), $criteria, $result['usage']);
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

    public function translateText(string $english): TextTranslationDraft
    {
        $result = $this->complete($this->translationSystemPrompt(), $this->translationMessages($english));
        $data = $this->decodeJson($result['text']);

        return new TextTranslationDraft(
            arabic: $this->string($data, 'arabic', ''),
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

    public function evaluateWriting(array $item, string $answer, ?EnglishLevel $level = null): WritingEvaluation
    {
        $result = $this->complete(
            $this->writingSystemPrompt($level),
            $this->writingMessages($item, $answer),
            temperature: self::EVALUATION_TEMPERATURE,
        );

        return $this->parseWritingEvaluation($this->decodeJson($result['text']), $result['usage']);
    }

    public function evaluateSpeaking(array $item, string $transcript, ?EnglishLevel $level = null): SpeakingEvaluation
    {
        $result = $this->complete(
            $this->speakingSystemPrompt($level),
            $this->speakingMessages($item, $transcript),
            temperature: self::EVALUATION_TEMPERATURE,
        );

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

    public function coachLearner(array $context, ?EnglishLevel $level = null): CoachingSummary
    {
        $result = $this->complete(
            $this->coachingSystemPrompt($level),
            $this->coachingMessages($context),
            temperature: self::EVALUATION_TEMPERATURE,
        );

        return $this->parseCoachingSummary($this->decodeJson($result['text']), $result['usage']);
    }

    public function briefDashboard(array $context): DashboardBriefingDraft
    {
        $result = $this->complete(
            $this->briefingSystemPrompt(),
            $this->briefingMessages($context),
            temperature: self::EVALUATION_TEMPERATURE,
        );

        return $this->parseBriefing($this->decodeJson($result['text']), $result['usage']);
    }

    public function draftReminder(string $purpose, string $tone, array $variables): ReminderDraft
    {
        $result = $this->complete($this->reminderSystemPrompt($variables), $this->reminderMessages($purpose, $tone));

        return $this->parseReminderDraft($this->decodeJson($result['text']), $variables, $result['usage']);
    }

    public function pronunciationGuide(string $text, Accent $accent): PronunciationGuideDraft
    {
        // IPA and trap words are knowledge work: the main model, run cool.
        $result = $this->complete(
            $this->pronunciationGuideSystemPrompt($accent),
            $this->pronunciationGuideMessages($text, $accent),
            temperature: self::EVALUATION_TEMPERATURE,
        );

        return $this->parsePronunciationGuide($this->decodeJson($result['text']), $result['usage']);
    }

    public function coachPronunciation(array $result, array $words, Accent $accent, ?EnglishLevel $level = null): PronunciationCoaching
    {
        // A learner is waiting on this tip: the fast model when configured.
        $reply = $this->complete(
            $this->pronunciationCoachSystemPrompt($accent, $level),
            $this->pronunciationCoachMessages($result),
            $this->fastModel,
            self::EVALUATION_TEMPERATURE,
        );

        return $this->parsePronunciationCoaching($this->decodeJson($reply['text']), $words, $reply['usage']);
    }

    // ------------------------------------------------------------ transport

    /**
     * One Chat Completions call. Returns the reply text and the usage the API
     * reported.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, usage: AiUsageInfo}
     */
    private function complete(string $system, array $messages, ?string $model = null, float $temperature = self::TEMPERATURE): array
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
            'temperature' => $temperature,
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
}
