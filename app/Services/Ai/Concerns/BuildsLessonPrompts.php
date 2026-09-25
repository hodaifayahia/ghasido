<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiUsageInfo;
use App\Contracts\CourseOutline;
use App\Contracts\LessonDraft;

/**
 * The lesson and course-outline prompts and reply parsers, shared by every
 * real provider so Qwen, OpenAI and Anthropic draft the same shape (GEN-01,
 * GEN-03, LESSON-01, LESSON-02; spec 0004).
 *
 * The lesson shape follows the default block order: Situation → Vocabulary
 * → Useful Expressions → Listen & Repeat → Dialogue → Practice → Lesson
 * Complete. Arabic is asked for only where Show Meaning reveals it (CTRL-01).
 *
 * Needs ParsesJsonReplies in the using class.
 */
trait BuildsLessonPrompts
{
    protected function lessonSystemPrompt(): string
    {
        return 'You design English lessons for hotel staff in Algeria whose first language is Arabic and whose English level is low. Given a topic, a hotel department and a level, write ONE complete lesson: a short realistic situation, what the learner will be able to do, key vocabulary, useful expressions, short sentences to listen to and repeat, a short dialogue and a few practice questions. Keep every English sentence short and simple. Adult, professional tone, never childish. Arabic must be Modern Standard Arabic and appears ONLY in the "arabic" fields. Respond with ONLY a JSON object of this exact shape: '
            .'{"title": "<lesson title, max 8 words>", '
            .'"subtitle": "<one sentence describing the lesson objective>", '
            .'"situation_text": "<2-3 short sentences describing the scene the staff member is in>", '
            .'"situation_quote": "<a short line someone in the scene says, in quotation marks>", '
            .'"objectives": ["<short objective>", "<short objective>", "<short objective>"], '
            .'"image_prompt": "<one English sentence describing a realistic photo of the scene: a modern hotel, adult professional staff, no text in the image>", '
            .'"vocabulary": [{"english": "<word or short phrase>", "arabic": "<MSA>", "explanation": "<one simple English sentence>", "example": "<one hotel sentence>"}], '
            .'"expressions": [{"english": "<useful expression>", "arabic": "<MSA>", "explanation": "<one simple English sentence>", "example": "<one hotel sentence>"}], '
            .'"listen_repeat": [{"text": "<short English sentence to repeat>", "arabic": "<MSA translation>"}], '
            .'"dialogue": [{"speaker": "<Guest or Staff>", "text": "<English line>", "arabic": "<MSA translation>"}], '
            .'"practice": [{"prompt": "<question>", "options": ["<option>", "<option>", "<option>", "<option>"], "answer_index": <0-based index of the correct option>, "explanation": "<why it is correct>"}]}'
            ."\n\nProvide 3 objectives, 6 to 8 vocabulary items, 4 to 6 expressions, 3 to 5 listen-and-repeat sentences, 6 to 8 dialogue lines and 3 to 5 practice questions.";
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    protected function lessonMessages(string $topic, string $department, string $level, string $notes): array
    {
        return [[
            'role' => 'user',
            'content' => sprintf(
                "Topic: %s\nDepartment: %s\nLevel: %s\nNotes: %s",
                trim($topic),
                $department !== '' ? $department : 'hotel front office',
                $level !== '' ? $level : 'beginner',
                $notes !== '' ? trim($notes) : 'none',
            ),
        ]];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function lessonDraftFrom(array $data, string $topic, AiUsageInfo $usage): LessonDraft
    {
        return new LessonDraft(
            title: $this->string($data, 'title', trim($topic)),
            subtitle: $this->string($data, 'subtitle', ''),
            situationText: $this->string($data, 'situation_text', ''),
            situationQuote: $this->string($data, 'situation_quote', ''),
            vocabulary: $this->lexiconRows($data, 'vocabulary'),
            expressions: $this->lexiconRows($data, 'expressions'),
            dialogue: $this->dialogueRows($data, 'dialogue'),
            practice: $this->practiceRows($data, 'practice'),
            usage: $usage,
            objectives: array_slice($this->stringList($data, 'objectives'), 0, 5),
            imagePrompt: $this->string($data, 'image_prompt', ''),
            listenRepeat: $this->listenRepeatRows($data, 'listen_repeat'),
        );
    }

    protected function outlineSystemPrompt(int $lessonCount): string
    {
        return sprintf(
            'You plan short English courses for hotel staff in Algeria whose first language is Arabic and whose English level is low. Given the admin\'s request, a hotel department and a level, plan ONE course of exactly %d lessons in teaching order, from the simplest situation to the hardest. Each lesson covers one realistic workplace situation. Respond with ONLY a JSON object of this exact shape: '
            .'{"title": "<course title, max 6 words>", "description": "<one sentence>", "lessons": [{"title": "<lesson title, max 8 words>", "topic": "<one sentence describing the situation and the language to teach>"}]}',
            $lessonCount,
        );
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    protected function outlineMessages(string $brief, string $department, string $level): array
    {
        return [[
            'role' => 'user',
            'content' => sprintf(
                "Request: %s\nDepartment: %s\nLevel: %s",
                trim($brief),
                $department !== '' ? $department : 'hotel front office',
                $level !== '' ? $level : 'beginner',
            ),
        ]];
    }

    /**
     * Exactly `$lessonCount` lessons: extra rows are cut, missing rows are
     * filled from the brief so the course always has the size asked for.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function outlineFrom(array $data, string $brief, int $lessonCount, AiUsageInfo $usage): CourseOutline
    {
        $lessons = [];

        foreach ($this->list($data, 'lessons') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = $this->string($row, 'title', '');
            $topic = $this->string($row, 'topic', $title);

            if ($title === '' && $topic === '') {
                continue;
            }

            $lessons[] = ['title' => $title !== '' ? $title : $topic, 'topic' => $topic !== '' ? $topic : $title];
        }

        $lessons = array_slice($lessons, 0, $lessonCount);

        for ($index = count($lessons); $index < $lessonCount; $index++) {
            $lessons[] = [
                'title' => sprintf('Lesson %d', $index + 1),
                'topic' => sprintf('%s (part %d)', trim($brief), $index + 1),
            ];
        }

        return new CourseOutline(
            title: $this->string($data, 'title', mb_substr(trim($brief), 0, 80)),
            description: $this->string($data, 'description', ''),
            lessons: $lessons,
            usage: $usage,
        );
    }

    /**
     * Sentences to listen to and repeat. Rows without English are dropped.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array{text: string, arabic: string}>
     */
    protected function listenRepeatRows(array $data, string $key): array
    {
        $rows = [];

        foreach ($this->list($data, $key) as $row) {
            if (is_string($row) && trim($row) !== '') {
                $rows[] = ['text' => trim($row), 'arabic' => ''];

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $text = $this->string($row, 'text', '');
            if ($text === '') {
                continue;
            }

            $rows[] = ['text' => $text, 'arabic' => $this->string($row, 'arabic', '')];
        }

        return $rows;
    }
}
