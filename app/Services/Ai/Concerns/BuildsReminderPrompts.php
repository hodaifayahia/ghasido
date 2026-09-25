<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiUsageInfo;
use App\Contracts\ReminderDraft;

/**
 * The reminder-drafting prompt every real provider shares (spec 0005 §4.2).
 *
 * The admin describes the purpose; the model writes a short subject and body
 * in simple, warm English using only the platform's placeholders. Any other
 * `{{…}}` the model invents is removed, so a draft can never carry a
 * placeholder TemplateRenderer would print literally. Needs
 * ParsesJsonReplies on the using class.
 */
trait BuildsReminderPrompts
{
    /**
     * @param  list<string>  $variables
     */
    protected function reminderSystemPrompt(array $variables): string
    {
        $placeholders = implode(', ', array_map(static fn (string $name): string => '{{'.$name.'}}', $variables));

        return implode(' ', [
            'You write short reminder messages for hotel staff who are learning English at a low level.',
            'Use simple, warm, professional English: short sentences, everyday words, no idioms, never childish, never guilt-tripping.',
            'The body is at most 80 words and ends with one clear action.',
            'You may use these placeholders exactly as written, and no others: '.$placeholders.'.',
            'Respond with ONLY a JSON object of this exact shape: {"subject": "<max 60 characters>", "body": "<the message>"}',
        ]);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    protected function reminderMessages(string $purpose, string $tone): array
    {
        return [[
            'role' => 'user',
            'content' => sprintf("Purpose of the reminder: %s\nTone: %s\n\nWrite it now.", trim($purpose), $tone),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $variables
     */
    protected function parseReminderDraft(array $data, array $variables, AiUsageInfo $usage): ReminderDraft
    {
        $clean = static fn (string $text): string => trim((string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            static fn (array $match): string => in_array(strtolower($match[1]), $variables, true) ? '{{'.strtolower($match[1]).'}}' : '',
            $text,
        ));

        return new ReminderDraft(
            subject: mb_substr($clean($this->string($data, 'subject', '')), 0, 150),
            body: $clean($this->string($data, 'body', '')),
            usage: $usage,
        );
    }
}
