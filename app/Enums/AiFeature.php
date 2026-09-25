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

    /**
     * Does this call spend an employee's role-play allowance?
     */
    public function countsTowardsTurnLimit(): bool
    {
        return $this === self::RoleplayTurn;
    }
}
