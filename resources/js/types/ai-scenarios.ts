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
};
