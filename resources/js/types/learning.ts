import type { LearnerJourney } from './auth';
import type { ActivityKind } from './assessment';
import type { Accent } from './pronunciation';
import type { ScenarioCard } from './roleplay';

/*
 * Employee learning content as the learner pages receive it (spec 0003 B.10,
 * Part E; shapes from BlockPresenter, LessonNavigator, JourneyService).
 */

/** A media asset resolved by PayloadResolver: null when the row is gone. */
export type MediaRef = {
    id: number;
    url: string;
    alt: string | null;
};

/** Both speeds of one stored sentence (CTRL-05, TTS-01); null = no clip yet. */
export type AudioPair = {
    normal: string | null;
    slow: string | null;
};

/** The shared `journey` prop (JOURNEY-01..05). */
export type JourneyState = LearnerJourney;

export type CourseTone =
    | 'brand'
    | 'aqua'
    | 'success'
    | 'warning'
    | 'gold'
    | 'danger'
    | 'ai'
    | 'azure'
    | 'sunset'
    | 'blossom';

export type BlockType =
    | 'situation'
    | 'vocabulary'
    | 'expressions'
    | 'listen_repeat'
    | 'dialogue'
    | 'video'
    | 'practice'
    | 'quiz'
    | 'ai_roleplay'
    | 'complete'
    | 'text'
    | 'note'
    | 'image'
    | 'audio'
    | 'email_activity'
    | 'phone_activity';

/** One entry of the step tracker (LESSON-03, LESSON-04). */
export type LessonStepNav = {
    number: number;
    label: string;
    type: BlockType;
    url: string;
    done: boolean;
    current: boolean;
};

/** The lesson header every step shares. */
export type LessonSummary = {
    id: number;
    title: string;
    introduction: string | null;
    objectives: string[];
    cover: MediaRef | null;
    estimatedMinutes: number | null;
    positionInCourse: number;
    courseLessonCount: number;
    course: { id: number; title: string; tone: CourseTone };
    department: { name: string };
};

// ---------------------------------------------------------------- settings

export type ObjectiveIcon = 'chat' | 'people' | 'check';

export type SituationSettings = {
    quote: string | null;
    objectives: { icon: ObjectiveIcon; text: string }[];
};

export type LexiconSettings = {
    featured_lexicon_item_id?: number | null;
    tip?: string | null;
    side_title?: string | null;
    subtitle?: string | null;
};

/** A sentence with its clips paired by the resolver (`<key>_audio`). */
export type PlayableText = {
    text: string;
    text_audio?: AudioPair;
    arabic?: string | null;
};

export type ListenRepeatSettings = {
    subtitle?: string | null;
    tip?: string | null;
    items: (PlayableText & { image?: MediaRef | null })[];
};

export type DialogueLine = PlayableText & { speaker: 'staff' | 'guest' };

export type DialogueSettings = {
    subtitle?: string | null;
    image?: MediaRef | null;
    situation_caption?: string | null;
    tip?: string | null;
    lines: DialogueLine[];
};

export type VideoSettings = {
    subtitle?: string | null;
    video?: MediaRef | null;
    poster?: MediaRef | null;
    controls_note?: string | null;
    example?: (PlayableText & { note?: string | null }) | null;
    tip?: string | null;
};

export type PracticeSettings = {
    subtitle?: string | null;
    motto?: string | null;
};

export type RoleplaySettings = {
    subtitle?: string | null;
    scenario_ids?: number[];
    tip?: string | null;
};

export type CompleteSettings = {
    subtitle?: string | null;
    image?: MediaRef | null;
    quote?: string | null;
    closing_quote?: string | null;
    encouragement?: string | null;
};

/** text / note / image / audio and the two activity wrappers. */
export type GenericSettings = {
    body?: string | null;
    arabic?: string | null;
    image?: MediaRef | null;
    audio_text?: string | null;
    audio_text_audio?: AudioPair;
};

// ------------------------------------------------------------------ blocks

/** A vocabulary / expressions item with Show Meaning content (CTRL-01..03). */
export type LexiconEntry = {
    id: number;
    kind: 'word' | 'expression';
    text: string;
    ipa: string | null;
    partOfSpeech: string | null;
    image: MediaRef | null;
    audio: AudioPair;
    example: string | null;
    exampleAudio: AudioPair | null;
    showMeaning: boolean;
    meaning: {
        arabic: string | null;
        explanation: string | null;
        exampleArabic: string | null;
    } | null;
    saved: boolean;
    featured: boolean;
};

/** A practice-hub card (PRAC-01..03, PRAC-07). */
export type PracticeCard = {
    id: number;
    activityId: number;
    type: ActivityKind;
    label: string;
    description: string;
    tone: CourseTone;
    icon: string;
    preview: { images: string[]; sentence: string | null };
    itemCount: number;
    url: string;
    attemptsAllowed: number;
    attemptsUsed: number;
    attemptsLeft: number | null;
    done: boolean;
    bestScore: { score: number; maxScore: number | null } | null;
};

/** One ticked row of the Lesson Summary (photo_19). */
export type CompleteRow = {
    type: BlockType;
    title: string;
    subtitle: string;
    done: boolean;
};

/** What the Lesson Completed screen reports. */
export type CompleteSummary = {
    lessonsCompleted: number;
    lessonsTotal: number;
    nextLesson: { id: number; title: string; url: string } | null;
    rows: CompleteRow[];
    homeUrl: string;
    lessonsUrl: string;
};

type StepBlockBase<TType extends BlockType, TSettings> = {
    id: number;
    type: TType;
    title: string | null;
    heading: string;
    stepLabel: string;
    layout: string | null;
    /** The accent the learner's speech is judged in (spec 0006 §3). */
    accent?: Accent | null;
    settings: TSettings;
    lexicon: LexiconEntry[];
    activities: PracticeCard[];
    scenarios: ScenarioCard[];
    summary: CompleteSummary | null;
};

/** One block as the step page receives it, discriminated on `type`. */
export type StepBlock =
    | StepBlockBase<'situation', SituationSettings>
    | StepBlockBase<'vocabulary' | 'expressions', LexiconSettings>
    | StepBlockBase<'listen_repeat', ListenRepeatSettings>
    | StepBlockBase<'dialogue', DialogueSettings>
    | StepBlockBase<'video', VideoSettings>
    | StepBlockBase<'practice' | 'quiz', PracticeSettings>
    | StepBlockBase<'ai_roleplay', RoleplaySettings>
    | StepBlockBase<'complete', CompleteSettings>
    | StepBlockBase<
          | 'text'
          | 'note'
          | 'image'
          | 'audio'
          | 'email_activity'
          | 'phone_activity',
          GenericSettings
      >;

export type StepBlockOf<T extends BlockType> = Extract<StepBlock, { type: T }>;

/** The block summary an activity page carries. */
export type ActivityBlockRef = {
    id: number;
    type: BlockType;
    heading: string;
    url: string;
    activityNumber: number;
};

// -------------------------------------------------------------------- home

export type PreTestFactIcon = 'clock' | 'list' | 'target' | 'lock' | 'chart';

/** The stored `tests.intro` json (spec 0003 G.4, photo_20). */
export type PreTestIntro = {
    eyebrow?: string;
    heading?: string;
    paragraphs?: string[];
    facts?: { icon: PreTestFactIcon; label: string; text: string }[];
    good_to_know_title?: string;
    good_to_know?: string[];
    remember?: { title: string; text: string };
    primary?: string;
    secondary?: string;
    script?: string;
};

export type HomeTest = {
    id: number;
    type: 'pre' | 'post';
    title: string;
    intro: PreTestIntro;
    timeLimitSeconds: number | null;
    questionCount: number;
    startUrl: string;
};

export type AttemptInProgress = {
    id: number;
    url: string;
    remainingSeconds: number | null;
};

export type ContinueLesson = {
    id: number;
    title: string;
    stepLabel: string;
    url: string;
};

// ------------------------------------------------------------- my lessons

export type LessonListItem = {
    id: number;
    title: string;
    position: number;
    estimatedMinutes: number | null;
    stepCount: number;
    completed: boolean;
    locked: boolean;
    url: string;
};

export type UnitOutline = {
    id: number;
    title: string;
    lessons: LessonListItem[];
};

export type CourseOutline = {
    id: number;
    title: string;
    description: string | null;
    tone: CourseTone;
    units: UnitOutline[];
};

// ------------------------------------------------------------- phrasebook

export type PhrasebookEntry = {
    id: number;
    source: 'lexicon' | 'custom';
    lexiconItemId: number | null;
    kind: 'word' | 'expression' | null;
    text: string;
    ipa: string | null;
    image: MediaRef | null;
    audio: AudioPair;
    example: string | null;
    exampleAudio: AudioPair | null;
    showMeaning: boolean;
    meaning: LexiconEntry['meaning'];
    lesson: { id: number; title: string } | null;
    savedAt: string;
    removeUrl: string;
    /** Spaced review (spec 0005 §3.4): Leitner box 0..masteryMax. */
    mastery: number;
    masteryMax: number;
    needsPractice: boolean;
    /** Sent back with an answer so a retried request saves it once. */
    reviewCount: number;
    reviewUrl: string;
};

/** The AI coach card (spec 0005 §3.5); the next step is chosen by the server. */
export type CoachSummary = {
    status: 'empty' | 'pending' | 'refreshing' | 'ready' | 'failed';
    headline: string | null;
    strengths: string[];
    focus: string[];
    tip: string | null;
    generatedAt: string | null;
    nextStep: { label: string; description: string; url: string };
};

/** Days in a row with some learning (spec 0005 §3.2). */
export type StreakSummary = {
    current: number;
    best: number;
    activeToday: boolean;
    week: { date: string; label: string; active: boolean; today: boolean }[];
};

// --------------------------------------------------------------- progress

export type ProgressCourse = {
    id: number;
    title: string;
    tone: CourseTone;
    description: string | null;
    lessonsTotal: number;
    lessonsCompleted: number;
    percent: number;
};

export type ProgressStats = {
    lessonsCompleted: number;
    lessonsTotal: number;
    percent: number;
    trainingStartedAt: string | null;
    trainingCompletedAt: string | null;
    lastActivityAt: string | null;
};

/** A test result shaped by its `results_visibility` (TEST-04). */
export type TestResultSummary = {
    submittedAt: string | null;
    percent: number | null;
    score: number | null;
    maxScore: number | null;
    passed: boolean | null;
    visibility: string;
};

// --------------------------------------------------------------- messages

export type LearnerMessage = {
    id: number;
    channel: 'email' | 'in_app';
    subject: string;
    body: string;
    sentAt: string | null;
    expiresAt: string | null;
    readAt: string | null;
    read: boolean;
    readUrl: string;
};

// ------------------------------------------------------------ certificate

export type CertificateView = {
    id: number;
    type: 'participation' | 'completion';
    typeLabel: string;
    verificationId: string;
    issuedAt: string;
    course: { id: number; title: string };
    employee: { name: string };
    hotel: string | null;
    department: string | null;
};

export type CertificateEligibility = {
    available: boolean;
    preTestSubmitted: boolean;
    lessonsCompleted: number;
    lessonsTotal: number;
    postTestUnlocked: boolean;
    postTestSubmitted: boolean;
};

// ------------------------------------------------------------ first login

export type FirstLoginUser = {
    name: string;
    email: string | null;
    reminderConsent: boolean;
    completed: boolean;
};

// ---------------------------------------------------------------- levels

/** Beginner, Intermediate or Advanced (client decision 2026-09-30). */
export type EnglishLevelValue = 'beginner' | 'intermediate' | 'advanced';

export type LevelOption = { value: string; label: string };

/** The learner's level and a pending move-up suggestion (shared prop). */
export type LearnerLevel = {
    current: EnglishLevelValue | null;
    currentLabel: string | null;
    suggestion: EnglishLevelValue | null;
    suggestionLabel: string | null;
    options: LevelOption[];
    updateUrl: string;
    answerUrl: string;
};

/**
 * An admin-written Arabic meaning for one piece of lesson text
 * (lessons.meanings, user request 2026-09-25).
 */
export type LessonMeaning = {
    arabic: string;
    explanation: string | null;
};

/** One question of a finished test's answer review (`show_answers`). */
export type TestReviewStatus =
    | 'correct'
    | 'incorrect'
    | 'unanswered'
    | 'evaluated';

export type TestReviewRow = {
    number: number;
    question: string;
    yourAnswer: string | null;
    correctAnswer: string | null;
    status: TestReviewStatus;
};
