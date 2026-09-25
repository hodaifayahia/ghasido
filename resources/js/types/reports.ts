export type ReportSelectOption = {
    value: string;
    label: string;
};

export type ReportsFilters = {
    range: string;
    /** ISO dates of the resolved range (the custom inputs read them). */
    from: string;
    to: string;
    rangeLabel: string;
    hotel: string;
    department: string;
    employee: string;
    activityType: string;
    completionStatus: string;
    ranges: ReportSelectOption[];
    hotels: ReportSelectOption[];
    departments: ReportSelectOption[];
    employees: ReportSelectOption[];
    activityTypes: ReportSelectOption[];
    completionStatuses: ReportSelectOption[];
};

export type ReportMetricKey =
    | 'totalEmployees'
    | 'activeAccounts'
    | 'completedPretest'
    | 'completedPosttest'
    | 'completedAiScenarios'
    | 'completedLessons';

export type ReportMetric = {
    key: ReportMetricKey;
    value: number;
    label: string;
    detail: string;
};

export type ReportGroupedBarPoint = {
    label: string;
    first: number;
    second: number;
};

export type ReportSingleBarPoint = {
    label: string;
    value: number;
};

export type ReportCompletionBreakdown = {
    overall: number;
    completed: number;
    inProgress: number;
    notStarted: number;
};

export type ReportActivityBreakdown = {
    total: number;
    activeThisWeek: number;
    activeThisMonth: number;
    inactive: number;
};

export type ReportsTabKey =
    | 'employeeResults'
    | 'detailedAnswers'
    | 'roleplayLogs'
    | 'lessonProgress'
    | 'comparison'
    | 'downloadCenter';

export type ReportsTab = {
    key: ReportsTabKey;
    label: string;
};

export type ReportRowStatus =
    | 'active'
    | 'in_progress'
    | 'inactive'
    | 'completed';

export type ReportEmployeeRow = {
    id: number;
    rank: number;
    initials: string;
    name: string;
    department: string;
    /** Null until a sitting of that test has been submitted. */
    preScore: number | null;
    postScore: number | null;
    lessonsCompleted: number;
    lessonsTotal: number;
    scenariosCompleted: number;
    scenariosTotal: number;
    lastActivity: string;
    status: ReportRowStatus;
    statusLabel: string;
};

/** One answer, the research row (TEST-06, DATA-01, DATA-11). */
export type ReportAnswerRow = {
    id: number;
    employee: string;
    department: string;
    /** The test title or the lesson title. */
    context: string;
    skill: string;
    question: string;
    answer: string;
    /** Null for answers awaiting AI or human grading (speaking, writing). */
    isCorrect: boolean | null;
    score: number | null;
    maxScore: number | null;
    overridden: boolean;
    /** The AI's value kept beside an admin override (AIE-05), else null. */
    originalScore: number | null;
    overrideReason: string | null;
    /** Written and spoken answers are graded by the AI and can be re-graded. */
    aiGraded: boolean;
    aiStatus: 'pending' | 'running' | 'done' | 'failed' | null;
    timeTakenMs: number | null;
    version: number;
    submittedAt: string;
    /** The authorized serve route of a spoken answer, or null. */
    audioUrl: string | null;
};

export type ReportRoleplayStatus =
    | 'in_progress'
    | 'evaluating'
    | 'completed'
    | 'abandoned';

export type ReportCriterionScore = {
    key: string;
    label: string;
    score: number | null;
};

export type ReportTranscriptTurn = {
    role: 'guest' | 'employee';
    text: string;
    at: string;
};

export type ReportRoleplayFeedback = {
    summary_label?: string;
    summary_text?: string;
    did_well?: string[];
    improve?: Array<{ title: string; text: string }>;
    better_expression?: { yours: string; better: string };
    key_phrase?: string;
    footnote?: string;
};

/** One role-play attempt; transcript and feedback only for transcripts.view holders (ROLE-04). */
export type ReportRoleplayRow = {
    id: number;
    employee: string;
    department: string;
    scenario: string;
    attemptNo: number;
    status: ReportRoleplayStatus;
    statusLabel: string;
    overallScore: number | null;
    overridden: boolean;
    originalScore: number | null;
    overrideReason: string | null;
    aiStatus: 'pending' | 'running' | 'done' | 'failed' | null;
    criteria: ReportCriterionScore[];
    summary: string | null;
    turns: number;
    durationMs: number | null;
    startedAt: string;
    transcript: ReportTranscriptTurn[] | null;
    feedback: ReportRoleplayFeedback | null;
};

export type ReportLessonRow = {
    id: number;
    employee: string;
    department: string;
    course: string;
    lesson: string;
    completedAt: string;
};

export type ReportComparisonRow = {
    id: string;
    userId: number;
    employee: string;
    department: string;
    skill: string;
    preCorrect: number | null;
    preTotal: number | null;
    prePercent: number | null;
    postCorrect: number | null;
    postTotal: number | null;
    postPercent: number | null;
    delta: number | null;
};

/** The rows of the active tab, discriminated by the tab key. */
export type ReportResults =
    | { tab: 'employeeResults'; rows: ReportEmployeeRow[] }
    | { tab: 'detailedAnswers'; rows: ReportAnswerRow[] }
    | { tab: 'roleplayLogs'; rows: ReportRoleplayRow[] }
    | { tab: 'lessonProgress'; rows: ReportLessonRow[] }
    | { tab: 'comparison'; rows: ReportComparisonRow[] }
    | { tab: 'downloadCenter'; rows: never[] };

export type ReportPagination = {
    from: number;
    to: number;
    total: number;
    currentPage: number;
    lastPage: number;
    pages: Array<number | 'ellipsis'>;
    perPageOptions: number[];
    currentPerPage: number;
};

export type ReportExportActionTone = 'excel' | 'brand' | 'danger';

export type ReportExportFormat = 'csv' | 'xlsx' | 'pdf';

export type ReportExportAction = {
    id: ReportExportFormat;
    label: string;
    tone: ReportExportActionTone;
};

export type ReportDatasetKey =
    | 'employees'
    | 'answers'
    | 'roleplay'
    | 'lessons'
    | 'comparison'
    | 'anonymised';

export type ReportDataset = {
    key: ReportDatasetKey;
    label: string;
    description: string;
    anonymised: boolean;
};

export type ReportDetailLesson = {
    id: number;
    title: string;
    course: string;
    completedAt: string | null;
};

export type ReportDetailTest = {
    id: number;
    type: 'pre' | 'post';
    title: string;
    attemptNo: number;
    percent: number | null;
    submittedAt: string;
};

export type ReportDetailRoleplay = {
    id: number;
    scenario: string;
    attemptNo: number;
    status: ReportRoleplayStatus;
    overallScore: number | null;
    startedAt: string;
};

/** The per-employee drill-down behind View Details (REP-02). */
export type ReportEmployeeDetail = ReportEmployeeRow & {
    hotel: string;
    username: string | null;
    participantCode: string | null;
    trainingStarted: string;
    trainingCompleted: string;
    lessons: ReportDetailLesson[];
    tests: ReportDetailTest[];
    roleplays: ReportDetailRoleplay[];
};

/** Row actions offered from the overflow menu of an Employee Results row. */
export type ReportRowAction =
    | 'details'
    | 'answers'
    | 'roleplay'
    | 'lessons'
    | 'comparison'
    | 'export';

/** The score an admin is adjusting from Reports & Export (AIE-05; spec 0005 §2.5). */
export type ReportScoreTarget =
    | { kind: 'answer'; row: ReportAnswerRow }
    | { kind: 'roleplay'; row: ReportRoleplayRow };
