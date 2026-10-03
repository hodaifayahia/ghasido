export type AiScenarioSelectOption = {
    value: string;
    label: string;
};

export type AiScenarioTabKey =
    | 'scenarios'
    | 'categories'
    | 'instructions'
    | 'feedback'
    | 'preview';

export type AiScenarioTab = {
    key: AiScenarioTabKey;
    label: string;
};

export type AiScenarioMockupCrop = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type AiScenarioStatus = 'published' | 'draft';

export type AiScenarioLibraryItem = {
    id: string;
    title: string;
    department: string;
    level: string;
    status: AiScenarioStatus;
    crop: AiScenarioMockupCrop;
    /** Stored scenarios only: may the viewer delete it (policy destroy)? */
    canDelete?: boolean;
    /** Learner role-play attempts; above 0 a delete keeps them (DATA-10). */
    learnerAttempts?: number;
    /** Lesson steps that offer it; a delete takes it out of them. */
    lessonSteps?: number;
};

export type AiScenarioDirectoryMetricKey =
    | 'totalScenarios'
    | 'publishedScenarios'
    | 'draftScenarios'
    | 'departments';

export type AiScenarioDirectoryMetric = {
    key: AiScenarioDirectoryMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type CreateAiScenarioPayload = {
    title: string;
    department: string;
    level: string;
};

export type AiScenarioLibrary = {
    search: string;
    department: string;
    status: string;
    departments: AiScenarioSelectOption[];
    statuses: AiScenarioSelectOption[];
    scenarios: AiScenarioLibraryItem[];
    showing: string;
    pages: number[];
    currentPage: number;
};

export type AiScenarioEditor = {
    id?: number;
    status: string;
    title: string;
    titleCount: string;
    department: string;
    level: string;
    departments: AiScenarioSelectOption[];
    levels: AiScenarioSelectOption[];
    coverCrop: AiScenarioMockupCrop;
    description: string;
    descriptionCount: string;
    guestRole: string;
    employeeRole: string;
    objectives: string[];
    situation?: string;
    objective?: string;
    /** The guest's first line in a call; empty uses the default. */
    openingLine?: string;
    usefulPhrases?: string[];
    aiStatus?: 'pending' | 'running' | 'done' | 'failed' | null;
    aiDraft?: Record<string, unknown> | null;
    saveUrl?: string;
    generateUrl?: string;
    applyDraftUrl?: string;
    publishUrl?: string;
    usedInLessons: AiScenarioLessonUsage[];
};

export type AiScenarioLessonUsage = {
    id: number;
    title: string;
    course: string | null;
    unit: string | null;
    blockId: number;
    status: AiScenarioStatus;
    url: string;
};

export type AiScenarioSavePayload = {
    title: string;
    department_id: number;
    difficulty: 'beginner' | 'intermediate' | 'advanced';
    description: string;
    situation: string;
    ai_role: string;
    employee_role: string;
    objective: string;
    opening_line?: string;
    goals: string[];
    useful_phrases: string[];
    settings?: {
        attempts_allowed: number;
        feedback_style: string;
        focus_areas: AiScenarioFocusArea[];
        allow_hints: boolean;
        show_suggestions: boolean;
        tags: string[];
    };
};

export type AiScenarioPreviewMessage = {
    id: number;
    actor: 'guest' | 'employee';
    text: string;
    avatarCrop?: AiScenarioMockupCrop;
};

export type AiScenarioPreview = {
    messages: AiScenarioPreviewMessage[];
    placeholder: string;
};

export type AiScenarioFocusArea = {
    label: string;
    checked: boolean;
};

export type AiScenarioSettings = {
    attempts: string;
    attemptOptions: AiScenarioSelectOption[];
    feedbackStyle: string;
    feedbackStyles: AiScenarioSelectOption[];
    focusAreas: AiScenarioFocusArea[];
    allowHints: boolean;
    showSuggestions: boolean;
    tags: string[];
    saveUrl?: string;
};

export type AiScenarioSummaryStat = {
    label: string;
    value: string;
    tone: 'brand' | 'success' | 'ai' | 'warning';
};

export type AiScenarioCategory = {
    id: string;
    name: string;
    description: string;
    department: string;
    scenarioCount: number;
    status: AiScenarioStatus;
};

export type AiScenarioCategories = {
    summary: AiScenarioSummaryStat[];
    items: AiScenarioCategory[];
    saveUrl?: string;
};

export type AiScenarioGuardrail = {
    id: string;
    text: string;
};

export type AiScenarioVariable = {
    token: string;
    description: string;
};

export type AiScenarioInstructions = {
    systemPrompt: string;
    systemPromptCount: string;
    tone: string;
    toneOptions: AiScenarioSelectOption[];
    strictness: string;
    strictnessOptions: AiScenarioSelectOption[];
    guardrails: AiScenarioGuardrail[];
    variables: AiScenarioVariable[];
    saveUrl?: string;
};

export type AiScenarioInstructionsSavePayload = {
    systemPrompt: string;
    tone: string;
    strictness: string;
    guardrails: AiScenarioGuardrail[];
};

export type AiScenarioFeedbackCriterion = {
    label: string;
    weight: number;
};

export type AiScenarioFeedbackTemplate = {
    id: string;
    name: string;
    description: string;
    tone: string;
    status: AiScenarioStatus;
    isDefault: boolean;
    criteria: AiScenarioFeedbackCriterion[];
};

export type AiScenarioFeedbackTemplates = {
    sections: string[];
    templates: AiScenarioFeedbackTemplate[];
    saveUrl?: string;
};

export type AiScenarioPreviewScenario = {
    value: string;
    label: string;
    department: string;
    level: string;
    situation: string;
    aiRole: string;
    employeeRole: string;
    objective: string;
    quote: string | null;
    icon: string;
    status: string;
};

export type AiScenarioPreviewTurn = {
    id: number;
    role: 'guest' | 'employee';
    text: string;
    audio: string | null;
    at: string;
};

export type AiScenarioPreviewFeedback = {
    summary_label?: string;
    summary_text?: string;
    did_well?: string[];
    improve?: { title: string; text: string }[];
    better_expression?: { yours: string; better: string };
    key_phrase?: string;
    footnote?: string;
};

export type AiScenarioPreviewAttempt = {
    id: number;
    scenarioId: string;
    scenarioTitle: string;
    status: string;
    aiStatus: string | null;
    pendingReply: boolean;
    failedReason: string | null;
    transcript: AiScenarioPreviewTurn[];
    employeeTurns: number;
    minTurns: number;
    canEnd: boolean;
    overallScore: number | null;
    criteriaScores: Record<string, number>;
    feedback: AiScenarioPreviewFeedback | null;
    messageUrl: string;
    endUrl: string;
    resetUrl: string;
};

export type AiScenarioPreviewTest = {
    scenarios: AiScenarioPreviewScenario[];
    selected: string;
    startUrl: string;
    placeholder: string;
    notSavedNote: string;
    attempt: AiScenarioPreviewAttempt | null;
    /** The scenario open in the editor, for its Test tab. */
    editorScenario?: AiScenarioPreviewScenario | null;
};
