<?php

namespace App\Contracts;

/**
 * AI-drafted Pre-test / Post-test questions (GEN-01, GEN-03, TEST-05).
 *
 * Every question is one normalised array, validated by the provider's
 * parser before it gets here:
 *
 * - `skill`: multiple_choice | listening | speaking | writing | ordering
 * - `question`: the prompt the learner reads
 * - `situation`: optional context line
 * - `options`: list<{id, text}> and `correct` option id (multiple_choice, listening)
 * - `audio_script`: what the learner hears (listening)
 * - `sentences`: the lines in the CORRECT order (ordering)
 * - `model_answer`: an example good answer (speaking, writing)
 *
 * A draft, never auto-published (GEN-03): the job stores these as draft
 * activities the admin reviews.
 */
final readonly class TestQuestionsDraft
{
    /**
     * @param  list<array{skill: string, question: string, situation: string, options: list<array{id: string, text: string}>, correct: string|null, audio_script: string, sentences: list<string>, model_answer: string}>  $questions
     */
    public function __construct(
        public array $questions,
        public AiUsageInfo $usage,
    ) {}
}
