/**
 * Individual subscribers: learners with no hotel, each on their own
 * configuration (IndividualsController, user request 2026-09-25).
 */
export type IndividualWindowState = 'upcoming' | 'active' | 'ended';

export type IndividualRow = {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
    /** One or more; the first is their main department. */
    departmentIds: string[];
    /** Their department names, main one first. */
    department: string;
    status: 'active' | 'inactive';
    windowState: IndividualWindowState;
    startsOn: string | null;
    endsOn: string | null;
    daysRemaining: number | null;
    aiEnabled: boolean;
    voiceEnabled: boolean;
    aiPoints: number;
    aiPointsUsed: number;
    aiPointsLeft: number;
    dailyAiTurns: number | null;
    aiActionPoints: number;
    voicePointsPer10Minutes: number;
    priceDzd: number | null;
    paymentReference: string | null;
    notes: string | null;
    lastActivity: string | null;
};

export type IndividualStats = {
    total: number;
    active: number;
    endingSoon: number;
    withAi: number;
};

export type IndividualDefaults = {
    aiPoints: number;
    aiActionPoints: number;
    voicePointsPer10Minutes: number;
    dailyAiTurns: number;
};

export type IndividualFilters = {
    search: string;
    /** `all`, `inactive` or `ended`; the server falls back to `all`. */
    state: string;
};

export type IndividualPagination = {
    currentPage: number;
    lastPage: number;
    total: number;
    from: number;
    to: number;
};

export type IndividualOption = { value: string; label: string };
