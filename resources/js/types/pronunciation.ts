/*
 * The pronunciation check (spec 0006) as the step page polls it
 * (App\Services\Pronunciation\PronunciationPresenter).
 */

/** British or American English: the lesson's voice and judging accent. */
export type Accent = 'en-GB' | 'en-US';

export type PronunciationWordStatus =
    | 'correct'
    | 'unclear'
    | 'almost'
    | 'mispronounced'
    | 'different'
    | 'missed'
    | 'skipped';

export type PronunciationLevel =
    | 'excellent'
    | 'good'
    | 'fair'
    | 'try_again'
    | 'not_heard';

/** One word of the sentence with its verdict (ACC-02: icon + text). */
export type PronunciationWord = {
    index: number;
    text: string;
    status: PronunciationWordStatus;
    /** What was heard instead, for a mispronounced or different word. */
    heard: string | null;
    /** The swapped sound, e.g. "v → f". */
    sound: string | null;
    tip: string | null;
    /** This word alone at both speeds, once generated (the drill). */
    audio: { normal: string | null; slow: string | null } | null;
};

export type PronunciationFeedback = {
    headline: string;
    tips: { word: string; tip: string }[];
    next: string;
    /** Shown only behind Show Meaning (CTRL-01, CTRL-02). */
    arabic: string | null;
};

export type PronunciationResult = {
    id: number;
    status: 'pending' | 'running' | 'done' | 'failed';
    /** Nothing more will change: stop polling. */
    settled: boolean;
    error: string | null;
    text: string;
    sentence: string | null;
    isDrill: boolean;
    wordIndex: number | null;
    accent: Accent;
    attemptNo: number;
    score: number | null;
    level: PronunciationLevel | null;
    levelLabel: string | null;
    scores: {
        words: number | null;
        clarity: number | null;
        flow: number | null;
    };
    words: PronunciationWord[];
    extras: string[];
    fillers: number;
    longPauses: number;
    feedbackStatus: 'pending' | 'done' | 'failed' | 'skipped' | null;
    feedback: PronunciationFeedback | null;
    pollUrl: string;
};

/**
 * A saved sentence check of a Listen & Repeat step, restored from the server
 * after a logout (client report 2026-11-05): the full result, the sentence
 * index and the learner's own recording (DATA-02).
 */
export type PronunciationHistoryEntry = PronunciationResult & {
    item: number;
    createdAt: string | null;
    recordingUrl: string | null;
};
