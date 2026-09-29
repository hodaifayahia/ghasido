export type HotelMetricKey =
    | 'totalHotels'
    | 'activeContracts'
    | 'expiringSoon'
    | 'pausedContracts'
    | 'usedSeats';

export type HotelMetric = {
    key: HotelMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type HotelSelectOption = {
    value: string;
    label: string;
};

export type HotelFilters = {
    search: string;
    status: string;
    capacity: string;
    statuses: HotelSelectOption[];
    capacities: HotelSelectOption[];
};

/**
 * The status the page displays. `expiring` and `ended` are derived from the
 * contract dates on the server; the rest mirror the stored access state
 * (spec 0002, State transitions).
 */
export type HotelContractStatus =
    | 'pending'
    | 'active'
    | 'expiring'
    | 'paused'
    | 'ended'
    | 'archived';

/** The stored state an administrator sets (spec 0002, AC-2). */
export type HotelAccessState = 'pending' | 'active' | 'paused' | 'archived';

export type HotelCapacityState = 'available' | 'full' | 'over';

export type HotelRecord = {
    id: number;
    rank: number;
    name: string;
    manager: string;
    email: string;
    city: string;
    departments: number;
    usedSeats: number;
    totalSeats: number;
    contractEnd: string;
    daysRemaining: number | null;
    status: HotelContractStatus;
    accessState: HotelAccessState;
    capacityState: HotelCapacityState;
    /** ISO dates for the edit dialog; null when no contract is set. */
    contractStartsOn: string | null;
    contractEndsOn: string | null;
};

export type HotelPagination = {
    from: number;
    to: number;
    total: number;
    currentPage: number;
    lastPage: number;
    pages: Array<number | 'ellipsis'>;
};

export type HotelDepartmentQuota = {
    departmentId: number;
    department: string;
    usedSeats: number;
    totalSeats: number;
    state: HotelCapacityState;
};

/**
 * One department the hotel may hold seats in, for the Manage seats dialog.
 * `allowedSeats` is null when the hotel has no quota row for it yet.
 */
export type HotelSeatCatalogueEntry = {
    departmentId: number;
    department: string;
    usedSeats: number;
    allowedSeats: number | null;
};

export type HotelOverview = {
    id: number;
    name: string;
    manager: string;
    email: string;
    city: string;
    contractStart: string;
    contractEnd: string;
    daysRemaining: number | null;
    usedSeats: number;
    totalSeats: number;
    employees: number;
    departments: number;
    status: HotelContractStatus;
    accessState: HotelAccessState;
    alerts: string[];
    quotas: HotelDepartmentQuota[];
    seatCatalogue: HotelSeatCatalogueEntry[];
};

export type HotelEmployeeActivity = {
    id: number;
    name: string;
    username: string;
    department: string;
    accountStatus: HotelEmployeeAccountStatus;
    trainingStatus: 'completed' | 'in_progress' | 'not_started' | 'inactive';
    trainingStatusLabel: string;
    activityStatus: 'activeThisWeek' | 'activeThisMonth' | 'inactive';
    activityStatusLabel: string;
    progress: number;
    lessonsCompleted: number;
    lessonsTotal: number;
    lastActivity: string;
    timeSpentMinutes: number;
    timeSpent: string;
};

export type HotelEmployeeAccountStatus = 'active' | 'inactive';

export type HotelDetailSummary = {
    totalEmployees: number;
    activeAccounts: number;
    activeUsers: number;
    startedTraining: number;
    completedTraining: number;
    inactiveUsers: number;
    totalTimeSpentMinutes: number;
    totalTimeSpent: string;
    averageTimeSpent: string;
};

export type HotelDetailActivity = {
    activeThisWeek: number;
    activeThisMonth: number;
    inactive: number;
};

/** The row actions the overflow menu can raise (spec 0002, AC-17). */
export type HotelRowAction =
    | 'view'
    | 'edit'
    | 'seats'
    | 'departments'
    | 'approve'
    | 'reject'
    | 'extend'
    | 'pause'
    | 'resume'
    | 'archive'
    | 'delete';
