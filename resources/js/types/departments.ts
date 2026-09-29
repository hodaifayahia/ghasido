export type DepartmentMetricKey =
    | 'totalDepartments'
    | 'activeDepartments'
    | 'sharedTemplates'
    | 'assignedEmployees'
    | 'contentReady';

export type DepartmentMetric = {
    key: DepartmentMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type DepartmentSelectOption = {
    value: string;
    label: string;
};

export type DepartmentFilters = {
    search: string;
    scope: string;
    status: string;
    scopes: DepartmentSelectOption[];
    statuses: DepartmentSelectOption[];
};

export type DepartmentScope = 'shared' | 'hotel';

export type DepartmentStatus = 'active' | 'review' | 'draft';

export type DepartmentQuotaState = 'available' | 'full' | 'over';

export type DepartmentRecord = {
    id: number;
    rank: number;
    name: string;
    focus: string;
    scope: DepartmentScope;
    scopeLabel: string;
    /** The owning hotel; null for the shared catalogue (ORG-04). */
    hotelId: number | null;
    hotelCount: number;
    employeeCount: number;
    usedSeats: number;
    totalSeats: number;
    lessonCount: number;
    testCount: number;
    scenarioCount: number;
    status: DepartmentStatus;
    /** False once archived: switched off, nothing deleted (DATA-10). */
    isActive: boolean;
};

export type DepartmentPagination = {
    from: number;
    to: number;
    total: number;
    currentPage: number;
    lastPage: number;
    pages: Array<number | 'ellipsis'>;
};

export type DepartmentHotelAssignment = {
    hotelId: number;
    hotel: string;
    manager: string;
    usedSeats: number;
    totalSeats: number;
    state: DepartmentQuotaState;
};

export type DepartmentOverview = DepartmentRecord & {
    notes: string[];
    assignments: DepartmentHotelAssignment[];
};

/** What a row's buttons and overflow menu can ask the page to do. */
export type DepartmentRowAction =
    | 'view'
    | 'edit'
    | 'content'
    | 'archive'
    | 'restore'
    | 'delete';
