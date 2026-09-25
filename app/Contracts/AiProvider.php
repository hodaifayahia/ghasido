<?php

namespace App\Contracts;

use App\Enums\LexiconKind;
use App\Enums\ScenarioDifficulty;
use App\Models\AiScenario;

/**
 * Every LLM feature the platform uses, behind one interface (API-04).
 *
 * Application code depends on this, never on a concrete SDK or endpoint, so
 * the provider swaps through configuration alone (spec 0003 Part C). Every
 * method returns a DTO that carries its own AiUsageInfo for metering.
 *
 * Transcripts are the stored `roleplay_attempts.transcript` shape: a list of
 * `{role: guest|employee, text, at}` entries in conversation order.
 */
interface AiProvider
{
    /**
     * The guest's next line in a role-play (RP-03, RP-04).
     *
     * @param  list<array{role: string, text: string, at?: string}>  $transcript
     */
    public function roleplayReply(AiScenario $scenario, array $transcript): AiReply;

    /**
     * Judge one complete conversation against the scenario's criteria
     * (RP-08, AIE-01).
     *
     * @param  list<array{role: string, text: string, at?: string}>  $transcript
     */
    public function evaluateRoleplay(AiScenario $scenario, array $transcript): AiEvaluation;

    /**
     * Draft the Arabic meaning, explanation and hotel example for a word or
     * expression (GEN-01). A draft, never auto-published (GEN-03).
     */
    public function generateLexicon(string $english, LexiconKind $kind, string $context): LexiconDraft;

    /**
     * Draft a whole role-play scenario from the admin's title, department and
     * difficulty (GEN-01, RP-01, RP-04). A draft, never auto-published
     * (GEN-03). `$notes` is any optional steer the admin typed.
     */
    public function generateScenario(string $title, string $department, ScenarioDifficulty $difficulty, string $notes = ''): ScenarioDraft;

    /**
     * Draft a whole lesson — situation, vocabulary, expressions, dialogue and
     * practice — from a topic, department and level (GEN-01, LESSON-01,
     * LESSON-02). A draft, never auto-published (GEN-03).
     */
    public function generateLesson(string $topic, string $department, string $level, string $notes = ''): LessonDraft;

    /**
     * Plan a short course from one admin prompt: a title, a description and
     * exactly `$lessonCount` lesson topics in teaching order (GEN-01; spec
     * 0004). A draft, never auto-published (GEN-03).
     */
    public function generateCourseOutline(string $brief, string $department, string $level, int $lessonCount): CourseOutline;

    /**
     * Judge one written answer against its writing item (WRITE-03).
     *
     * @param  array<string, mixed>  $item  one `writing` payload item (spec 0003 B.9)
     */
    public function evaluateWriting(array $item, string $answer): WritingEvaluation;

    /**
     * Judge one spoken answer from its transcript against its speaking item
     * (TEST-07, AIE-01, AIE-04). Structured criterion → {score, comment}.
     *
     * @param  array<string, mixed>  $item  one `speaking` payload item (spec 0003 B.9)
     */
    public function evaluateSpeaking(array $item, string $transcript): SpeakingEvaluation;

    /**
     * Draft Pre/Post-test questions, one per entry of `$skills` in order
     * (GEN-01, TEST-05). `$avoid` lists question texts a paired test already
     * uses, so a Post-test tests the same skills with different items
     * (TEST-02, TSTM-03). A draft, never auto-published (GEN-03).
     *
     * @param  list<string>  $skills  TestQuestionSkill values
     * @param  list<string>  $avoid
     */
    public function generateTestQuestions(string $department, string $level, array $skills, string $notes = '', array $avoid = []): TestQuestionsDraft;
}
