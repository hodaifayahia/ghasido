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

export type HotelContractStatus = 'active' | 'expiring' | 'paused' | 'ended';

export type HotelCapacityState = 'available' | 'full' | 'over';

export type HotelRecord = {
    id: number;
    rank: number;
    name: string;
    manager: string;
    city: string;
    departments: number;
    usedSeats: number;
    totalSeats: number;
    contractEnd: string;
    daysRemaining: number | null;
    status: HotelContractStatus;
    capacityState: HotelCapacityState;
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
    department: string;
    usedSeats: number;
    totalSeats: number;
    state: HotelCapacityState;
};

export type HotelOverview = {
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
    alerts: string[];
    quotas: HotelDepartmentQuota[];
};
