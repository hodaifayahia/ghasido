<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiUsageInfo;
use App\Contracts\SpeakingEvaluation;
use App\Contracts\TestQuestionsDraft;
use App\Enums\TestQuestionSkill;

/**
 * The prompts and the defensive parsers for the assessment features every
 * real AiProvider shares: judging a spoken answer from its transcript
 * (AIE-01, AIE-04) and drafting Pre/Post-test questions (GEN-01, TEST-02,
 * TSTM-03). One copy, so the OpenAI-compatible and Anthropic providers
 * cannot drift apart. The host class also uses ParsesJsonReplies.
 */
trait BuildsAssessmentPrompts
{
    protected function speakingSystemPrompt(): string
    {
        return 'You are an encouraging English coach for hotel staff whose first language is Arabic. You receive a speaking task and the automatic transcript of what the employee said (the transcript may contain recognition errors; ignore pronunciation). Reward communicative success over grammatical perfection; the tone is adult, warm and professional. If the transcript is empty or unrelated, give low task_completion and say so kindly. Respond with ONLY a JSON object of this exact shape: '
            .'{"criteria": {"task_completion": {"score": <int 0-100>, "comment": "<one sentence>"}, "accuracy": {"score": <int>, "comment": "<text>"}, "vocabulary": {"score": <int>, "comment": "<text>"}, "politeness": {"score": <int>, "comment": "<text>"}}, '
            .'"better_answer": "<a short model answer the employee could say>", "summary": "<one encouraging sentence>"}';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{role: string, content: string}>
     */
    protected function speakingMessages(array $item, string $transcript): array
    {
        $task = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [[
            'role' => 'user',
            'content' => sprintf(
                "Speaking task (JSON):\n%s\n\nTranscript of the employee's recorded answer:\n%s",
                $task === false ? '{}' : $task,
                trim($transcript) === '' ? '(nothing was recognised)' : $transcript,
            ),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function parseSpeakingEvaluation(array $data, AiUsageInfo $usage): SpeakingEvaluation
    {
        $raw = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        $criteria = [];

        foreach (SpeakingEvaluation::CRITERIA as $key) {
            $entry = is_array($raw[$key] ?? null) ? $raw[$key] : [];
            $criteria[$key] = [
                'score' => $this->score($entry, 'score'),
                'comment' => $this->string($entry, 'comment', ''),
            ];
        }

        return new SpeakingEvaluation(
            criteria: $criteria,
            betterAnswer: $this->string($data, 'better_answer', ''),
            summary: $this->string($data, 'summary', ''),
            usage: $usage,
        );
    }

    protected function testQuestionsSystemPrompt(): string
    {
        return 'You write English placement-test questions for hotel staff in Algeria whose first language is Arabic and whose English level is low. Every question is about real hotel work in the given department. Use short, simple, natural English. Never write Arabic. Never reveal the answer inside the question. You receive the list of question skills, in order; write exactly one question per skill, in the same order. Respond with ONLY a JSON object of this exact shape: '
            .'{"questions": [{"skill": "<the requested skill>", "question": "<what the learner reads>", "situation": "<one short context sentence or empty>", '
            .'"options": ["<option>", "<option>", "<option>"], "answer_index": <0-based index of the correct option>, '
            .'"audio_script": "<listening only: one or two sentences a guest says, which the learner hears but never sees>", '
            .'"sentences": ["<ordering only: 4 to 5 lines of a short hotel dialogue in the CORRECT order>"], '
            .'"model_answer": "<speaking and writing only: a short good answer>"}]}'
            ."\n\nRules per skill: multiple_choice = question + 3 or 4 options, one correct. "
            .'listening = audio_script + question asking what the learner should answer or understood + 3 options about what was heard. '
            .'speaking = question asking the learner to say something in a situation (situation required), no options. '
            .'writing = question asking for a short written reply (2-3 sentences) to a guest message given in situation, no options. '
            .'ordering = question "Put the conversation in the correct order." + sentences in the correct order.';
    }

    /**
     * @param  list<string>  $skills
     * @param  list<string>  $avoid  question texts that must not be repeated (a paired test, TSTM-03)
     * @return list<array{role: string, content: string}>
     */
    protected function testQuestionsMessages(string $department, string $level, array $skills, string $notes, array $avoid): array
    {
        $content = sprintf(
            "Department: %s\nLevel: %s\nTopic / notes: %s\nSkills, in order (%d questions): %s",
            $department !== '' ? $department : 'Reception',
            $level !== '' ? $level : 'A2',
            trim($notes) !== '' ? trim($notes) : 'everyday hotel situations',
            count($skills),
            implode(', ', $skills),
        );

        if ($avoid !== []) {
            $content .= "\n\nThis is the paired Post-test: test the same skills at the same difficulty, but every question must be DIFFERENT from these Pre-test questions:\n- "
                .implode("\n- ", array_slice($avoid, 0, 40));
        }

        return [['role' => 'user', 'content' => $content]];
    }

    /**
     * Normalise the model's questions against the requested skills. A
     * question that cannot be used (no text, too few options, too few
     * sentences) is dropped rather than stored half-formed.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $skills
     */
    protected function parseTestQuestions(array $data, array $skills, AiUsageInfo $usage): TestQuestionsDraft
    {
        $rows = $this->list($data, 'questions');
        $questions = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            /** @var array<string, mixed> $row */
            $skill = TestQuestionSkill::tryFrom($this->string($row, 'skill', ''))
                ?? TestQuestionSkill::tryFrom($skills[$index] ?? '')
                ?? TestQuestionSkill::MultipleChoice;

            $question = $this->string($row, 'question', '');
            if ($question === '') {
                continue;
            }

            $options = [];
            foreach ($this->stringList($row, 'options') as $i => $text) {
                $options[] = ['id' => chr(65 + $i), 'text' => $text];
            }

            $answerIndex = $this->int($row, 'answer_index');
            $correct = $options[$answerIndex]['id'] ?? ($options[0]['id'] ?? null);
            $sentences = $this->stringList($row, 'sentences');
            $script = $this->string($row, 'audio_script', '');

            $usable = match ($skill) {
                TestQuestionSkill::MultipleChoice => count($options) >= 2,
                TestQuestionSkill::Listening => count($options) >= 2 && $script !== '',
                TestQuestionSkill::Ordering => count($sentences) >= 3,
                TestQuestionSkill::Speaking, TestQuestionSkill::Writing => true,
            };

            if (! $usable) {
                continue;
            }

            $choice = in_array($skill, [TestQuestionSkill::MultipleChoice, TestQuestionSkill::Listening], true);

            $questions[] = [
                'skill' => $skill->value,
                'question' => $question,
                'situation' => $this->string($row, 'situation', ''),
                'options' => $choice ? array_slice($options, 0, 4) : [],
                'correct' => $choice ? $correct : null,
                'audio_script' => $skill === TestQuestionSkill::Listening ? $script : '',
                'sentences' => $skill === TestQuestionSkill::Ordering ? array_slice($sentences, 0, 6) : [],
                'model_answer' => $this->string($row, 'model_answer', ''),
            ];
        }

        return new TestQuestionsDraft(array_slice($questions, 0, max(count($skills), 1)), $usage);
    }
}
