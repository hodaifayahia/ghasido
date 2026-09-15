export type ReportSelectOption = {
    value: string;
    label: string;
};

export type ReportsFilters = {
    range: string;
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
    preScore: number;
    postScore: number;
    lessonsCompleted: number;
    lessonsTotal: number;
    scenariosCompleted: number;
    scenariosTotal: number;
    lastActivity: string;
    status: ReportRowStatus;
    statusLabel: string;
};

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

export type ReportExportAction = {
    id: string;
    label: string;
    tone: ReportExportActionTone;
};
