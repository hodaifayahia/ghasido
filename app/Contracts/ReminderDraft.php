<?php

namespace App\Contracts;

/**
 * An AI-drafted reminder template (spec 0005 §4.2): a subject and a body
 * using only the platform's placeholders. A draft the admin edits and saves,
 * never sent or published by itself (GEN-03).
 */
final readonly class ReminderDraft
{
    public function __construct(
        public string $subject,
        public string $body,
        public AiUsageInfo $usage,
    ) {}

    /**
     * @return array{subject: string, body: string}
     */
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'body' => $this->body];
    }
}
