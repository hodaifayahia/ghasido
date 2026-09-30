/*
 * AI on the Pre-test & Post-test builder (GEN-01, GEN-03, GEN-04, TTS-01,
 * AIE-04, TSTM-03, PERF-04; spec 0004): question drafts, stored listening
 * audio, and the AI-judged speaking/writing answers in the results.
 */

export type TestAiJobStatus = 'pending' | 'running' | 'done' | 'failed';

export type TestQuestionSkillKey =
    | 'multiple_choice'
    | 'listening'
    | 'speaking'
    | 'writing'
    | 'ordering';

export type TestAiOption<T extends string = string> = {
    value: T;
    label: string;
};

export type TestAiPanel = {
    status: TestAiJobStatus | null;
    failedReason: string | null;
    generateUrl: string;
    regenerateUrl: string;
    canRegenerate: boolean;
    lastRequest: {
        count: number;
        level: string;
        prompt: string;
        paired: boolean;
    };
    pairedSource: {
        id: number;
        title: string;
        questionCount: number;
    } | null;
    skills: TestAiOption<TestQuestionSkillKey>[];
    levels: TestAiOption[];
    generateAudioUrl: string;
};

export type TestAiGeneratePayload = {
    prompt: string;
    count: number | null;
    skills: TestQuestionSkillKey[];
    level: string;
    paired: boolean;
};

/** `none` never reaches the page: a question without a script sends null. */
export type TestQuestionAudioStatus =
    | 'missing'
    | 'pending'
    | 'running'
    | 'failed'
    | 'done';

export type TestQuestionAudio = {
    status: TestQuestionAudioStatus;
    failedReason: string | null;
    generateUrl: string;
};

export type TestQuestionDetail = {
    label: string;
    text: string;
};
