// Props of pages/Dashboard.vue, as rendered by DashboardController.

export type DashboardStatKey =
    | 'hotels'
    | 'departments'
    | 'employees'
    | 'trainingStarted'
    | 'trainingCompleted'
    | 'averageProgress';

export type DashboardStat = {
    key: DashboardStatKey;
    value: number;
    /** Rendered after the number, e.g. `54%`. */
    unit?: '%';
    label: string;
    /** Muted line under the label: hotel name, "Active", "77.5%"… */
    detail?: string;
};

export type TrainingBreakdown = {
    completed: number;
    inProgress: number;
    notStarted: number;
};

export type DepartmentTraining = {
    id: number;
    name: string;
    breakdown: TrainingBreakdown;
};

export type TrainingOverview = {
    all: TrainingBreakdown;
    departments: DepartmentTraining[];
};

export type DepartmentProgress = {
    id: number;
    name: string;
    /** 0–100 */
    percent: number;
};

export type AttentionGroupKey = 'inactive' | 'notStarted' | 'pretestFinished';

export type AttentionEmployee = {
    id: number;
    name: string;
    department: string;
    /** Server-formatted relative time, e.g. "5 days ago". */
    lastLogin: string;
};

export type AttentionGroup = {
    key: AttentionGroupKey;
    label: string;
    total: number;
    employees: AttentionEmployee[];
};

export type ActivityType =
    | 'lessonCompleted'
    | 'pretestFinished'
    | 'roleplayUsed'
    | 'trainingCompleted'
    | 'trainingStarted';

export type RecentActivity = {
    id: number;
    /** Server-formatted, e.g. "07 Sep 2026". */
    date: string;
    /** Server-formatted, e.g. "14:32". */
    time: string;
    employee: string;
    type: ActivityType;
    activity: string;
    details: string;
};
