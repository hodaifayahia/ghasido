import type { Accent } from './pronunciation';

export type LessonFilterOption = {
    value: string;
    label: string;
};

export type LessonsFilters = {
    hotel: string;
    department: string;
    course: string;
    unit: string;
    lesson: string;
    hotels: LessonFilterOption[];
    departments: LessonFilterOption[];
    courses: LessonFilterOption[];
    units: LessonFilterOption[];
    lessons: LessonFilterOption[];
    /** Tree nodes the user expanded by hand: `c<id>` / `u<id>`. */
    open?: string[];
};

export type LessonDirectoryRow = {
    id: number;
    title: string;
    course: string | null;
    unit: string | null;
    department: string;
    hotel: string;
    hotelId: number | null;
    departmentId: number | null;
    courseId: number;
    unitId: number;
    status: LessonContentStatus;
    /** Number of visible employee steps in this lesson. */
    steps: number;
    url: string;
};

export type LessonDirectoryMetricKey =
    | 'totalLessons'
    | 'publishedLessons'
    | 'draftLessons'
    | 'learningSteps';

export type LessonDirectoryMetric = {
    key: LessonDirectoryMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type LessonDirectoryFilters = {
    search: string;
    hotel: string;
    department: string;
    course: string;
    status: string;
    hotels: LessonFilterOption[];
    departments: LessonFilterOption[];
    courses: LessonFilterOption[];
    statuses: LessonFilterOption[];
    pageSizes: number[];
};

export type LessonDirectoryPagination = {
    from: number;
    to: number;
    total: number;
    currentPage: number;
    lastPage: number;
    perPage: number;
    pages: Array<number | 'ellipsis'>;
};

export type LessonCreateUnit = {
    id: number;
    title: string;
};

export type LessonCreateCourse = {
    id: number;
    title: string;
    units: LessonCreateUnit[];
};

export type LessonsTabKey =
    | 'content'
    | 'preview'
    | 'settings'
    | 'materials'
    | 'roleplay'
    | 'quiz';

export type LessonsTab = {
    key: LessonsTabKey;
    label: string;
};

export type LessonsTreeTone =
    | 'brand'
    | 'aqua'
    | 'success'
    | 'warning'
    | 'gold'
    | 'danger';

export type LessonContentStatus = 'draft' | 'published';

export type LessonsTreeLesson = {
    id: number;
    title: string;
    active?: boolean;
    status?: LessonContentStatus;
};

export type LessonsTreeUnit = {
    id: number;
    title: string;
    expanded?: boolean;
    lessons?: LessonsTreeLesson[];
    addLessonLabel?: string;
    status?: LessonContentStatus;
};

export type LessonsTreeCourse = {
    id: number;
    title: string;
    expanded?: boolean;
    tone?: LessonsTreeTone;
    units?: LessonsTreeUnit[];
    status?: LessonContentStatus;
};

export type LessonMockupCrop = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type LessonCompletionRule =
    | 'all_steps'
    | 'last_step'
    | 'practice_passed';

export type LessonCompletionCondition = {
    rule?: LessonCompletionRule;
    min_score?: number | null;
};

export type LessonEditor = {
    id: number | null;
    title: string;
    titleCount: string;
    coverCrop?: LessonMockupCrop;
    coverUrl: string | null;
    coverAlt: string;
    coverMediaId: number | null;
    introduction: string;
    introductionCount: string;
    objectives: string[];
    status: LessonContentStatus;
    publishedAt: string | null;
    estimatedMinutes: number | null;
    completionCondition: LessonCompletionCondition | null;
    /** Stored accent; null = the platform default (spec 0006 §3). */
    accent: Accent | null;
    /** The accent in effect: the stored one or the platform default. */
    effectiveAccent: Accent;
    unitId: number | null;
    hotelLabel: string | null;
    departmentLabel: string | null;
    previewUrl: string | null;
    updatedAt: string | null;
};

export type LessonBlockIcon =
    | 'situation'
    | 'vocabulary'
    | 'expressions'
    | 'dialogue'
    | 'audio'
    | 'image'
    | 'video'
    | 'practice'
    | 'roleplay'
    | 'quiz'
    | 'download'
    | 'note';

export type LessonBlockTone =
    | 'brand'
    | 'azure'
    | 'success'
    | 'warning'
    | 'danger'
    | 'ai'
    | 'aqua'
    | 'gold';

export type BlockTypeKey =
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

export type LessonBuilderBlock = {
    id: string;
    label: string;
    icon: LessonBlockIcon;
    tone: LessonBlockTone;
    /** The block type the tile adds; null when none exists yet. */
    type: BlockTypeKey | null;
};

export type LessonBlockTypeOption = {
    value: BlockTypeKey;
    label: string;
    stepLabel: string;
    description: string;
    tone: string;
    icon: string;
};

export type LessonLibraryTab = {
    key: string;
    label: string;
};

export type LessonLibraryImage = {
    id: string;
    label: string;
    crop?: LessonMockupCrop;
    url?: string;
    thumbUrl?: string;
    alt?: string;
    width?: number | null;
    height?: number | null;
    category?: string | null;
};

export type LessonsImageLibrary = {
    activeTab: string;
    tabs: LessonLibraryTab[];
    search: string;
    category: string;
    categories: LessonFilterOption[];
    images: LessonLibraryImage[];
    total?: number;
};

export type LessonMediaKind = 'image' | 'audio' | 'video' | 'document';

export type LessonMediaRef = {
    id: number;
    url: string;
    thumbUrl: string;
    alt: string;
    label: string;
    kind: LessonMediaKind;
};

export type AudioClipStatus =
    | 'missing'
    | 'pending'
    | 'running'
    | 'done'
    | 'failed';

export type AudioClipState = {
    status: AudioClipStatus;
    url: string | null;
};

export type LessonAudioPair = {
    normal: AudioClipState;
    slow: AudioClipState;
};

export type LexiconAiDraft = {
    arabic_meaning: string;
    simple_explanation: string;
    hotel_example: string;
    hotel_example_arabic: string;
    ipa: string | null;
    part_of_speech: string | null;
};

export type LexiconItemRow = {
    id: number;
    kind: 'word' | 'expression';
    englishText: string;
    ipa: string | null;
    partOfSpeech: string | null;
    arabicMeaning: string | null;
    simpleExplanation: string | null;
    hotelExample: string | null;
    hotelExampleArabic: string | null;
    imageId: number | null;
    imageUrl: string | null;
    showMeaningEnabled: boolean;
    source: 'manual' | 'ai';
    aiStatus: AudioClipStatus | null;
    aiDraft: LexiconAiDraft | null;
    audio: Record<string, LessonAudioPair>;
    missingAudio: boolean;
};

export type ActivityTypeKey =
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
    | 'writing';

export type LessonActivityItem = Record<string, unknown> & { id: string };

export type ActivityPayload = {
    items: LessonActivityItem[];
};

export type LessonActivityRow = {
    id: number;
    placementId: number | null;
    type: ActivityTypeKey;
    label: string;
    skillLabel: string | null;
    title: string | null;
    prompt: string;
    promptArabic: string | null;
    payload: ActivityPayload;
    attemptsAllowed: number;
    timeLimitSeconds: number | null;
    showMeaningEnabled: boolean;
    status: LessonContentStatus;
    version: number;
    itemCount: number;
};

export type BlockSettings = Record<string, unknown>;

export type LessonBlockRow = {
    id: number;
    type: BlockTypeKey;
    label: string;
    stepLabel: string;
    description: string;
    tone: string;
    icon: string;
    position: number;
    isVisible: boolean;
    title: string | null;
    layout: string;
    settings: BlockSettings;
    media: Record<string, LessonMediaRef>;
    audio: Record<string, LessonAudioPair>;
    lexiconItems: LexiconItemRow[];
    activities: LessonActivityRow[];
    scenarioIds: number[];
};

export type LessonScenarioOption = {
    id: number;
    title: string;
    description: string | null;
    difficulty: string;
    icon: string;
    status: LessonContentStatus;
};
