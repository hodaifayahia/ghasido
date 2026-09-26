<?php

namespace App\Enums;

/**
 * What a metered provider call was for (AIL-04, API-03, spec 0003 B.6).
 *
 * `ai_usages.feature` is one of these, so limits and cost reports can be
 * broken down by feature. Only the two role-play features count against an
 * employee's daily turn limit (AIL-01, AIL-03); the rest are admin-side or
 * infrastructure (TTS, STT) and are metered for cost only.
 */
enum AiFeature: string
{
    case RoleplayTurn = 'roleplay_turn';
    case RoleplayEval = 'roleplay_eval';
    case LexiconGenerate = 'lexicon_generate';
    case ScenarioGenerate = 'scenario_generate';
    case LessonGenerate = 'lesson_generate';
    case CourseOutline = 'course_outline';
    case ImageGenerate = 'image_generate';
    case WritingEval = 'writing_eval';
    case SpeakingEval = 'speaking_eval';
    case TestQuestionsGenerate = 'test_questions_generate';
    case Tts = 'tts';
    case Stt = 'stt';
    // The Super Admin's "Test" button on Settings → AI models (API-03).
    case ProviderCheck = 'provider_check';
    // A learner's coaching summary (spec 0005 §3.5); system-initiated, so
    // metered for cost but never charged to the learner's points.
    case LearnerCoach = 'learner_coach';
    // A manager's dashboard briefing (spec 0005 §4.1).
    case DashboardBriefing = 'dashboard_briefing';
    // An AI-drafted reminder template (spec 0005 §4.2).
    case ReminderDraft = 'reminder_draft';
    // Pronunciation check (spec 0006): the word-level listens of a
    // learner's recording (and of our reference audio, to calibrate), the
    // Qwen guide per text and accent, and the coach's tip. Metered for
    // cost, never charged to the learner's points.
    case PronunciationCheck = 'pronunciation_check';
    case PronunciationGuide = 'pronunciation_guide';
    case PronunciationCoach = 'pronunciation_coach';
    // A live voice call's Deepgram agent time, one row per call at hang-up,
    // in seconds (spec 0007, D9). Not a turn: never counts toward limits.
    case VoiceCall = 'voice_call';
    // Show Meaning on any English text a learner reads (client decision
    // 2026-09-26). Cached per text; metered for cost, never charged to the
    // learner's points.
    case Translation = 'translation';

    /**
     * The unit this feature's usage rows are counted in, so a model with no
     * price can be offered one in the right unit (spec 0007, D9).
     */
    public function unit(): string
    {
        return match ($this) {
            self::Tts => 'characters',
            self::Stt, self::PronunciationCheck, self::VoiceCall => 'seconds',
            self::ImageGenerate => 'images',
            default => 'tokens',
        };
    }

    /**
     * Does this call spend an employee's role-play allowance?
     */
    public function countsTowardsTurnLimit(): bool
    {
        return $this === self::RoleplayTurn;
    }
}
