<?php

namespace App\Contracts;

use App\Enums\Accent;
use App\Enums\EnglishLevel;
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
    public function roleplayReply(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiReply;

    /**
     * Judge one complete conversation against the scenario's criteria
     * (RP-08, AIE-01).
     *
     * @param  list<array{role: string, text: string, at?: string}>  $transcript
     */
    public function evaluateRoleplay(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiEvaluation;

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
    public function evaluateWriting(array $item, string $answer, ?EnglishLevel $level = null): WritingEvaluation;

    /**
     * Judge one spoken answer from its transcript against its speaking item
     * (TEST-07, AIE-01, AIE-04). Structured criterion → {score, comment}.
     *
     * @param  array<string, mixed>  $item  one `speaking` payload item (spec 0003 B.9)
     */
    public function evaluateSpeaking(array $item, string $transcript, ?EnglishLevel $level = null): SpeakingEvaluation;

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

    /**
     * A short personal coaching summary from one learner's own figures
     * (spec 0005 §3.5). Words only; the server picks the next step.
     *
     * @param  array<string, mixed>  $context  LearnerCoach::context()
     */
    public function coachLearner(array $context, ?EnglishLevel $level = null): CoachingSummary;

    /**
     * A short briefing for managers from aggregate training figures only
     * (spec 0005 §4.1): no learner is ever named in the data.
     *
     * @param  array<string, mixed>  $context  DashboardBriefing::context()
     */
    public function briefDashboard(array $context): DashboardBriefingDraft;

    /**
     * Draft a reminder template's subject and body for a purpose the admin
     * typed, using only the given placeholders (spec 0005 §4.2). A draft
     * the admin edits and saves (GEN-03).
     *
     * @param  list<string>  $variables  ReminderTemplate::VARIABLES
     */
    public function draftReminder(string $purpose, string $tone, array $variables): ReminderDraft;

    /**
     * How a text sounds in one accent, word by word, with the trap words an
     * Arabic speaker's typical errors produce (spec 0006 §4). An internal
     * scoring aid; the admin may review it (GEN-03).
     */
    public function pronunciationGuide(string $text, Accent $accent): PronunciationGuideDraft;

    /**
     * Coach a learner from the structured result of one pronunciation check
     * (spec 0006 §5). Words only: the scores are the server's.
     *
     * @param  array<string, mixed>  $result  CoachPronunciation::context()
     * @param  list<string>  $words  the words of the sentence practised
     */
    public function coachPronunciation(array $result, array $words, Accent $accent, ?EnglishLevel $level = null): PronunciationCoaching;

    /**
     * The meaning of one English text a learner is reading, in one helper
     * language: a course title, an objective, a question (CTRL-01..03;
     * client decisions 2026-09-26 and 2026-09-30). Plain translation, never
     * a hint at the right answer. `$language` is the language's English
     * name ("Arabic", "French"…), from HelperLanguages.
     */
    public function translateText(string $english, string $language = 'Arabic'): TextTranslationDraft;
}
