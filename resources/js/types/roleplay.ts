import type { MediaRef } from './learning';

/*
 * AI role-play as the learner pages receive it (spec 0003 G.5, G.6, Part E;
 * shapes from BlockPresenter::scenarios and the roleplay lane).
 */

export type ScenarioDifficulty = 'easy' | 'medium' | 'hard';

/** One scenario card on the AI Role-play step (RP-01, RP-05). */
export type ScenarioCard = {
    id: number;
    title: string;
    description: string | null;
    difficulty: ScenarioDifficulty;
    icon: string;
    thumbnail: MediaRef | null;
    url: string;
    attemptsAllowed: number;
    attemptsUsed: number;
    attemptsLeft: number;
};

/** The Get Ready screen (photo_16). */
export type RoleplayReadyView = ScenarioCard & {
    situation: string;
    yourRole: string;
    guestRole: string;
    goals: string[];
    tip: string | null;
    quote: string | null;
    usefulPhrases: string[];
    startUrl: string;
};

export type RoleplayTurnRole = 'guest' | 'employee';

export type RoleplayTurn = {
    id: number;
    role: RoleplayTurnRole;
    text: string;
    audio: { normal: string | null; slow: string | null };
    at: string;
};

export type RoleplayAttemptStatus =
    | 'in_progress'
    | 'pending_reply'
    | 'evaluating'
    | 'completed'
    | 'failed';

export type RoleplayAttemptView = {
    id: number;
    scenario: ScenarioCard;
    attemptNo: number;
    status: RoleplayAttemptStatus;
    transcript: RoleplayTurn[];
    messageUrl: string;
    endUrl: string;
    feedbackUrl: string;
    pollUrl: string;
};

/** `roleplay_attempts.feedback` (spec 0003 G.6). */
export type RoleplayFeedback = {
    summary_label: string;
    summary_text: string;
    did_well: string[];
    improve: { title: string; text: string }[];
    better_expression: { yours: string; better: string } | null;
    key_phrase: string | null;
    footnote: string | null;
};

export type RoleplayCriteria = Record<
    'pronunciation' | 'grammar' | 'vocabulary' | 'fluency' | 'politeness',
    number
>;

export type RoleplayFeedbackView = {
    attemptId: number;
    status: RoleplayAttemptStatus;
    feedback: RoleplayFeedback | null;
    criteriaScores: RoleplayCriteria | null;
    overallScore: number | null;
    attemptsLeft: number;
    retryUrl: string | null;
    backUrl: string;
};
