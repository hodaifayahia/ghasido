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
    hotelCount: number;
    employeeCount: number;
    usedSeats: number;
    totalSeats: number;
    lessonCount: number;
    testCount: number;
    scenarioCount: number;
    status: DepartmentStatus;
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
    hotel: string;
    manager: string;
    usedSeats: number;
    totalSeats: number;
    state: DepartmentQuotaState;
};

export type DepartmentOverview = {
    name: string;
    scopeLabel: string;
    focus: string;
    hotelCount: number;
    employeeCount: number;
    lessonCount: number;
    testCount: number;
    scenarioCount: number;
    status: DepartmentStatus;
    notes: string[];
    assignments: DepartmentHotelAssignment[];
};
