export type EmployeeMetricKey =
    | 'totalEmployees'
    | 'activeAccounts'
    | 'inactiveAccounts'
    | 'startedTraining'
    | 'completedTraining'
    | 'notStarted';

export type EmployeeMetric = {
    key: EmployeeMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type EmployeeSelectOption = {
    value: string;
    label: string;
};

export type EmployeeFilters = {
    search: string;
    /** A hotel id as a string, or 'all-hotels'. */
    hotel: string;
    /** A department id as a string, or 'all-departments'. */
    department: string;
    /** An EmployeeStatus, or 'all-statuses'. */
    status: string;
    hotels: EmployeeSelectOption[];
    departments: EmployeeSelectOption[];
    statuses: EmployeeSelectOption[];
};

/**
 * The status pill, derived on the server from the account status, the two
 * training timestamps and the lesson completions (PROG-02, DATA-07).
 */
export type EmployeeStatus =
    | 'completed'
    | 'in_progress'
    | 'not_started'
    | 'inactive';

export type EmployeeAccountStatus = 'active' | 'inactive';

export type EmployeeRecord = {
    id: number;
    rank: number;
    name: string;
    username: string;
    hotel: string;
    department: string;
    /** The learner's level label, or null before they choose one. */
    level: string | null;
    /** Display value: the address, or a dash when none is on file. */
    email: string;
    progress: number;
    status: EmployeeStatus;
    /** 'd M Y', or '-' when the employee never signed in. */
    lastLogin: string;
    // What the View and Edit dialogs need beyond the table (spec 0003 Part D).
    hotelId: number | null;
    departmentId: number | null;
    /** The raw address for the edit form; null when none is on file. */
    emailAddress: string | null;
    accountStatus: EmployeeAccountStatus;
    /** Reminder-email consent is recorded (REM-05, PRIV-02). */
    emailConsent: boolean;
    /** Consent AND an address: a reminder email would go out. */
    canRemind: boolean;
    /** The anonymised research identifier (REP-07). */
    participantCode: string | null;
    lastActivity: string;
    trainingStarted: string;
    trainingCompleted: string;
    lessonsCompleted: number;
    lessonsTotal: number;
    /** Latest submitted Pre-test / Post-test as a percentage, or null. */
    preTestScore: number | null;
    postTestScore: number | null;
};

export type EmployeePagination = {
    from: number;
    to: number;
    total: number;
    currentPage: number;
    lastPage: number;
    pages: Array<number | 'ellipsis'>;
};

export type EmployeeCreateForm = {
    hotels: EmployeeSelectOption[];
    /** The departments of the default hotel; see departmentsByHotel. */
    departments: EmployeeSelectOption[];
    /** Each hotel's seat-quota departments, keyed by hotel id (SUB-01). */
    departmentsByHotel: Record<string, EmployeeSelectOption[]>;
    statuses: EmployeeSelectOption[];
    defaultHotel: string;
    defaultDepartment: string;
    defaultStatus: string;
    allowReminderEmails: boolean;
    /** Length of the password the Generate button produces. */
    passwordLength: number;
};

/** The row actions the icons and the overflow menu can raise. */
export type EmployeeRowAction =
    | 'view'
    | 'edit'
    | 'remind'
    | 'reset-password'
    | 'activate'
    | 'deactivate'
    | 'delete';

/** The Bulk Actions panel's choices (`select-action` is the placeholder). */
export type EmployeeBulkAction = 'activate' | 'deactivate' | 'remind';

/**
 * The one-time credentials flashed after a password reset; shown once in a
 * dialog and never kept (AUTH-07).
 */
export type EmployeeCredentials = {
    id: number;
    name: string;
    username: string;
    password: string;
};
