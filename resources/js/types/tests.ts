/*
 * The Super Admin Pre-test & Post-test screen as the page receives it
 * (TEST-01, TEST-04, TEST-06, TSTM-02, TSTM-03, ADM-02; desginphotos/
 * photo_2026-09-15_18-11-15.jpg).
 *
 * The admin page is backed by the same test and activity rows used by the
 * learner runner (TEST-01..10, TSTM-01..05).
 */

import type {
    TestAiPanel,
    TestQuestionAudio,
    TestQuestionDetail,
} from './assessment-ai';
import type {
    LessonActivityRow,
    LessonAudioPair,
    LessonMediaRef,
} from './lessons';

// Pre-test vs Post-test. Named TestVariant (not TestType) so it never clashes
// with the learner-facing TestType union in assessment.ts.
export type TestVariant = 'pre' | 'post';

export type TestsSelectOption = {
    value: string;
    label: string;
};

/** A rectangle of desginphotos/…18-11-15 (1280×853), for LessonsMockupCrop. */
export type TestsMockupCrop = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type TestStatus = 'active' | 'draft';

export type TestListItem = {
    id: string;
    title: string;
    department: string;
    hotel: string;
    meta: string;
    questionCount: number;
    /** Sittings of any status; a test with any cannot be deleted (DATA-10). */
    attemptCount: number;
    timeLimit: number | null;
    type: TestVariant;
    status: TestStatus;
    crop: TestsMockupCrop;
};

export type TestDirectoryMetricKey =
    | 'totalTests'
    | 'activeTests'
    | 'draftTests'
    | 'questions';

export type TestDirectoryMetric = {
    key: TestDirectoryMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type CreateTestPayload = {
    title: string;
    type: TestVariant;
    department: string;
    timeLimit: string;
};

export type TestsList = {
    search: string;
    hotel: string;
    department: string;
    type: string;
    hotels: TestsSelectOption[];
    departments: TestsSelectOption[];
    types: TestsSelectOption[];
    items: TestListItem[];
};

/**
 * The client's ten question types (client report 2026-09-29), the same
 * ten the lesson activity editor offers.
 */
export type TestQuestionKind =
    | 'multiple_choice'
    | 'ordering'
    | 'matching'
    | 'short_answer'
    | 'audio_question'
    | 'image_question'
    | 'video_question'
    | 'speaking'
    | 'fill_blank'
    | 'writing';

export type TestQuestionKindOption = {
    value: TestQuestionKind;
    label: string;
};

export type TestQuestionOption = {
    id: string;
    text: string;
    correct: boolean;
};

export type TestEditorQuestion = {
    id: number;
    index: number;
    kind: TestQuestionKind;
    text: string;
    updateUrl: string;
    deleteUrl: string;
    mediaUrl: string;
    imageCrop?: TestsMockupCrop;
    media: TestQuestionMedia;
    options: TestQuestionOption[];
    typeLabel?: string;
    /** An AI draft learners cannot see until it is approved (GEN-03). */
    aiDraft?: boolean;
    releaseUrl?: string;
    details?: TestQuestionDetail[];
    audio?: TestQuestionAudio | null;
    /** What the shared activity editor opens with. */
    activity: LessonActivityRow | null;
    mediaMap: Record<string, LessonMediaRef>;
    audioMap: Record<string, LessonAudioPair>;
};

export type TestQuestionMediaRef = {
    id: string;
    label: string;
    url: string;
    thumbUrl: string;
    alt: string;
};

export type TestQuestionMedia = {
    image: TestQuestionMediaRef | null;
    audio: TestQuestionMediaRef | null;
    video: TestQuestionMediaRef | null;
};

/** The short form (kind, text, options) the CSV import still reads. */
export type TestQuestionPayload = {
    kind: TestQuestionKind;
    text: string;
    options: TestQuestionOption[];
};

export type TestEditorSavePayload = {
    title: string;
    type: TestVariant;
    department_id: number;
    hotel_id: number | null;
    description: string;
    time_limit_minutes: number | null;
    question_count: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    single_attempt: boolean;
    results_visibility: 'hidden' | 'score' | 'score_breakdown';
    show_answers: boolean;
    motivational_message: boolean;
    show_meaning: boolean;
    pass_mark: number | null;
};

export type TestEditor = {
    id: number | null;
    updateUrl: string | null;
    publishUrl: string | null;
    questionStoreUrl: string | null;
    title: string;
    type: string;
    typeOptions: TestsSelectOption[];
    department: string;
    departments: TestsSelectOption[];
    hotel?: string;
    timeLimit: string;
    questionCount: string;
    attemptCount: number;
    description: string;
    descriptionCount: string;
    kinds: TestQuestionKindOption[];
    questionKinds?: TestQuestionKindOption[];
    activeKind: TestQuestionKind;
    ai?: TestAiPanel | null;
    questions: TestEditorQuestion[];
    settings: TestEditorSettings;
    urls?: {
        update?: string;
        publish?: string;
        storeQuestion?: string;
    };
};

export type TestEditorSettings = {
    shuffle_questions: boolean;
    shuffle_options: boolean;
    single_attempt: boolean;
    results_visibility: 'hidden' | 'score' | 'score_breakdown';
    show_answers: boolean;
    motivational_message: boolean;
    show_meaning: boolean;
    passMark: string;
};

export type TestPreviewOption = {
    id: string;
    text: string;
};

export type TestPreview = {
    position: string;
    imageCrop: TestsMockupCrop;
    question: string;
    options: TestPreviewOption[];
    media: TestQuestionMedia;
    questions: TestEditorQuestion[];
};

export type TestMediaTabKey = 'image' | 'audio' | 'video';

export type TestMediaTab = {
    key: TestMediaTabKey;
    label: string;
};

export type TestSuggestedImage = {
    id: string;
    label: string;
    url: string;
    thumbUrl: string;
    alt: string;
};

export type TestMedia = {
    tabs: TestMediaTab[];
    activeTab: TestMediaTabKey;
    suggested: TestSuggestedImage[];
};

export type TestResultTone = 'brand' | 'danger' | 'success' | 'excel';

export type TestResultStat = {
    key: string;
    value: number;
    unit?: string;
    label: string;
    detail: string;
    tone: TestResultTone;
};

export type TestResults = {
    stats: TestResultStat[];
};
