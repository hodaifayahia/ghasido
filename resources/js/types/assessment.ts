import type { AudioPair, MediaRef } from './learning';
import type { PronunciationResult } from './pronunciation';

/*
 * Activities, attempts and tests as the learner pages receive them
 * (spec 0003 B.9, Part E; shapes from ActivityPresenter and AttemptRecorder).
 *
 * In `test` mode the server strips `correct`, `order`, `pairs` and every
 * Arabic field before the payload leaves (CTRL-04, TEST-03), which is why
 * those keys are optional here.
 */

export type ActivityKind =
    | 'listen_choose'
    | 'look_listen'
    | 'best_response'
    | 'listen_match'
    | 'watch_respond'
    | 'words_sentences'
    | 'dialogue_order'
    | 'picture_order'
    | 'multiple_choice'
    | 'speaking'
    | 'writing'
    | 'short_answer'
    | 'fill_blank'
    | 'matching'
    | 'ordering'
    | 'audio_question'
    | 'image_question'
    | 'video_question';

export type ActivityMode = 'practice' | 'test';

export type ImageOption = {
    id: string;
    label: string;
    image: MediaRef | null;
};

export type AudioOption = {
    id: string;
    audio_text: string;
    audio_text_audio?: AudioPair;
};

export type TextOption = {
    id: string;
    text: string;
    image?: MediaRef | null;
};

export type ListenChooseItem = {
    id: string;
    audio_text: string;
    audio_text_audio?: AudioPair;
    options: ImageOption[];
    correct?: string;
};

export type LookListenItem = {
    id: string;
    image: MediaRef | null;
    options: AudioOption[];
    correct?: string;
};

export type BestResponseItem = {
    id: string;
    situation: string;
    guest_audio_text: string;
    guest_audio_text_audio?: AudioPair;
    options: TextOption[];
    correct?: string;
};

export type ListenMatchItem = {
    id: string;
    prompts: AudioOption[];
    targets: ImageOption[];
    pairs?: Record<string, string>;
};

export type WatchRespondItem = {
    id: string;
    video: MediaRef | null;
    poster: MediaRef | null;
    subtitle: string | null;
    question: string;
    hint: string | null;
    options: TextOption[];
    correct?: string;
};

export type WordsSentencesItem = {
    id: string;
    sentence: string;
    audio_text: string;
    audio_text_audio?: AudioPair;
    image: MediaRef | null;
    options: ImageOption[];
    correct?: string;
};

export type DialogueOrderItem = {
    id: string;
    audio_text: string;
    audio_text_audio?: AudioPair;
    sentences: { id: string; text: string; text_audio?: AudioPair }[];
    order?: string[];
};

export type PictureOrderItem = {
    id: string;
    context: string | null;
    cards: { id: string; image: MediaRef | null; caption: string }[];
    order?: string[];
};

export type Passage =
    | {
          kind: 'email';
          from: string;
          to: string;
          subject: string;
          body: string;
      }
    | { kind: 'notice'; image: MediaRef | null; question_below: string };

export type MultipleChoiceItem = {
    id: string;
    question: string;
    subtitle: string | null;
    image: MediaRef | null;
    passage: Passage | null;
    layout: 'side' | 'grid';
    options: TextOption[];
    correct?: string;
};

export type SpeakingItem = {
    id: string;
    question: string;
    situation: string | null;
    instruction: string | null;
    image: MediaRef | null;
    max_seconds: number;
};

export type WritingItem = {
    id: string;
    scenario: string;
    request_text: string;
    request_text_audio?: AudioPair;
    information: string[];
    min_words: number;
};

/*
 * The client's ten types (client report 2026-09-29) share one flexible
 * prompt: a question, a picture, an uploaded clip (`audio`), a sentence
 * played from stored audio (`audio_text_audio`) and, for a video question,
 * a video. Answers are text or pictures, each with an optional
 * pronunciation.
 */
export type FlexiblePrompt = {
    id: string;
    question?: string | null;
    image?: MediaRef | null;
    audio?: MediaRef | null;
    audio_text?: string | null;
    audio_text_audio?: AudioPair;
};

export type FlexibleOption = {
    id: string;
    text: string;
    image?: MediaRef | null;
    audio_text?: string;
    audio_text_audio?: AudioPair;
};

export type FlexibleChoiceItem = FlexiblePrompt & {
    video?: MediaRef | null;
    poster?: MediaRef | null;
    option_style?: 'text' | 'image';
    options: FlexibleOption[];
    correct?: string;
};

export type ShortAnswerItem = FlexiblePrompt & { accepted?: string[] };

export type FillBlankItem = FlexiblePrompt & {
    /** `[[b1]]` marks each blank. */
    sentence: string;
    blanks: { id: string; accepted?: string[] }[];
};

export type MatchingItem = FlexiblePrompt & {
    prompts: {
        id: string;
        text: string;
        image?: MediaRef | null;
        audio?: MediaRef | null;
        text_audio?: AudioPair;
    }[];
    targets: { id: string; text: string; image?: MediaRef | null }[];
    pairs?: Record<string, string>;
};

export type OrderingItem = FlexiblePrompt & {
    sentences: {
        id: string;
        text: string;
        image?: MediaRef | null;
        text_audio?: AudioPair;
    }[];
    order?: string[];
};

type ItemMap = {
    listen_choose: ListenChooseItem;
    look_listen: LookListenItem;
    best_response: BestResponseItem;
    listen_match: ListenMatchItem;
    watch_respond: WatchRespondItem;
    words_sentences: WordsSentencesItem;
    dialogue_order: DialogueOrderItem;
    picture_order: PictureOrderItem;
    multiple_choice: MultipleChoiceItem;
    speaking: SpeakingItem;
    writing: WritingItem;
    short_answer: ShortAnswerItem;
    fill_blank: FillBlankItem;
    matching: MatchingItem;
    ordering: OrderingItem;
    audio_question: FlexibleChoiceItem;
    image_question: FlexibleChoiceItem;
    video_question: FlexibleChoiceItem;
};

export type ActivityItemOf<T extends ActivityKind> = ItemMap[T];

export type ActivityItem = ItemMap[ActivityKind];

type ActivityViewBase<T extends ActivityKind> = {
    /** The placement id: what the answer route is keyed on. */
    id: number;
    activityId: number;
    versionId: number;
    version: number;
    type: T;
    label: string;
    title: string | null;
    skillLabel: string | null;
    prompt: string | null;
    /** Null in test mode (CTRL-04). */
    promptArabic: string | null;
    description: string;
    tone: string;
    icon: string;
    sideImage: MediaRef | null;
    mode: ActivityMode;
    items: ItemMap[T][];
    itemCount: number;
    isAutoScored: boolean;
    showMeaningEnabled: boolean;
    timeLimitSeconds: number | null;
    attemptsAllowed: number;
    attemptsUsed: number;
    /** Null when attempts are unlimited (PRAC-07). */
    attemptsLeft: number | null;
};

/** One placed activity, discriminated on `type`. */
export type ActivityView = {
    [K in ActivityKind]: ActivityViewBase<K>;
}[ActivityKind];

export type ActivityViewOf<T extends ActivityKind> = Extract<
    ActivityView,
    { type: T }
>;

/** The raw answer for one item (`attempts.raw_answer`, keyed by item id). */
export type RecordingAnswer = {
    recording_media_id: number | null;
    duration_ms: number;
    /** Typed instead, when the microphone could not be used (RESP-05). */
    text?: string;
};

export type RawAnswer =
    | string
    | string[]
    | Record<string, string>
    | RecordingAnswer
    | { text: string };

export type AnswerMap = Record<string, RawAnswer>;

/** What comes back after a practice answer (never on a test page). */
export type ActivityResult = {
    placementId: number;
    attemptId: number;
    attemptNo: number;
    score: number | null;
    maxScore: number | null;
    isCorrect: boolean | null;
    perItem: Record<string, boolean | null>;
    correct: Record<string, unknown>;
    timeTakenMs: number;
    aiStatus: string | null;
    /** The judged spoken or written answer, once evaluated. */
    feedback?: ActivityFeedback | null;
};

/** A pronunciation check, or the AI verdict on a spoken / written answer. */
export type ActivityFeedback =
    | { kind: 'pronunciation'; check: PronunciationResult }
    | {
          kind: 'writing' | 'speaking';
          score: number | null;
          maxScore: number | null;
          summary: string;
          criteria: {
              key: string;
              label: string;
              score: number | null;
              comment: string;
          }[];
          corrections: { original: string; corrected: string; note: string }[];
          betterAnswer: string;
          transcript: string | null;
      };

// ------------------------------------------------------------------ tests

export type TestType = 'pre' | 'post';

export type ResultsVisibility = 'hidden' | 'score' | 'breakdown';

/** One question of a running test (spec 0003 Part E, `employee/test/Question`). */
export type TestQuestionView = {
    number: number;
    total: number;
    activity: ActivityView;
    answer: RawAnswer | null;
    prevUrl: string | null;
    nextUrl: string | null;
    answerUrl: string;
    finishUrl: string;
};

export type TestAttemptView = {
    id: number;
    testId: number;
    title: string;
    type: TestType;
    startedAt: string;
    /** Server deadline (TIME-05); null when the test has no timer. */
    deadlineAt: string | null;
    remainingSeconds: number | null;
    answeredNumbers: number[];
};

export type TestResultView = {
    attemptId: number;
    title: string;
    type: TestType;
    visibility: ResultsVisibility;
    submittedAt: string;
    percent: number | null;
    score: number | null;
    maxScore: number | null;
    passed: boolean | null;
    timeTakenMs: number;
    breakdown: { skillLabel: string; score: number; maxScore: number }[] | null;
};
