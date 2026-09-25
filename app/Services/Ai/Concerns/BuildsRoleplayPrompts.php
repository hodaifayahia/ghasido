<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiEvaluation;
use App\Contracts\AiUsageInfo;

/**
 * The role-play request shapes every chat provider shares (RP-03, RP-04,
 * AIE-04; spec 0005 §2.3).
 *
 * Both the Chat Completions and the Messages API map the transcript the same
 * way (guest = assistant, employee = user, a user turn first) and parse the
 * same evaluation JSON, so one copy lives here instead of one per provider.
 * The prompts themselves come from App\Services\Ai\RoleplayPrompt.
 *
 * Needs ParsesJsonReplies on the using class.
 */
trait BuildsRoleplayPrompts
{
    /**
     * Map the stored transcript onto chat turns: the guest is the assistant,
     * the employee is the user. Both APIs want a user turn first and last, so
     * an empty or guest-first transcript gets a neutral opener and a
     * guest-last one a neutral nudge.
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

    /**
     * @param  list<array{role: string, text: string, at?: string}>  $transcript
     * @return list<array{role: string, content: string}>
     */
    private function evaluationMessages(array $transcript): array
    {
        return [[
            'role' => 'user',
            'content' => "Transcript:\n".$this->transcriptText($transcript)."\n\nEvaluate the employee now.",
        ]];
    }

    /**
     * The evaluation JSON as a structured AiEvaluation: every scenario
     * criterion present with a 0-100 score and a comment, the overall score
     * falling back to the criteria mean (AIE-04).
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $criteria
     */
    private function roleplayEvaluationFrom(array $data, array $criteria, AiUsageInfo $usage): AiEvaluation
    {
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
            usage: $usage,
            footnote: $this->string($data, 'footnote', ''),
        );
    }
}
