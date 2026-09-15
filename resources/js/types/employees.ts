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
    hotel: string;
    department: string;
    status: string;
    hotels: EmployeeSelectOption[];
    departments: EmployeeSelectOption[];
    statuses: EmployeeSelectOption[];
};

export type EmployeeStatus =
    | 'completed'
    | 'in_progress'
    | 'not_started'
    | 'inactive';

export type EmployeeRecord = {
    id: number;
    rank: number;
    name: string;
    username: string;
    hotel: string;
    department: string;
    email: string;
    progress: number;
    status: EmployeeStatus;
    lastLogin: string;
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
    departments: EmployeeSelectOption[];
    statuses: EmployeeSelectOption[];
    defaultHotel: string;
    defaultDepartment: string;
    defaultStatus: string;
    allowReminderEmails: boolean;
};
