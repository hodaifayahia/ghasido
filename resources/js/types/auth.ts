export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    /** The user's single role name, or null when they hold none (spec 0001). */
    role: string | null;
    /** Login name (AUTH-01); null on accounts that still sign in by email. */
    username?: string | null;
    /** The employee's department, for the topbar role line; null for admins. */
    department_name: string | null;
    /** The employee's or manager's hotel; null for the Super Admin. */
    hotel_name: string | null;
    /** True until the one-time welcome animation has been shown. */
    show_welcome?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * The employee journey's gates and figures, shared on every response for
 * users holding the employee role and null for everyone else (JOURNEY-01..05,
 * PROG-05; spec 0003 Part E).
 */
export type LearnerJourney = {
    preTestSubmitted: boolean;
    /**
     * Gate 1 as the lessons read it: the Pre-test is submitted, or no
     * published Pre-test exists for the learner (JOURNEY-01).
     */
    lessonsUnlocked: boolean;
    lessonsCompleted: number;
    lessonsTotal: number;
    postTestUnlocked: boolean;
    certificateAvailable: boolean;
    /** "Continue where you left off": the next step's URL, or null. */
    continueUrl: string | null;
};

export type Auth = {
    user: User;
    /**
     * Every permission name the signed in user holds, `[]` for a guest.
     * Read it through useCan(); it decides what the sidebar shows, never
     * what the server allows (spec 0001, AC-5, invariant 4).
     */
    permissions: string[];
};

/** Current-month employee allowance or hotel pool shown in the navbar (AIL-01). */
export type AiPointsBalance = {
    role: 'employee' | 'manager';
    total: number;
    used: number;
    remaining: number;
    /** Remaining points as a percentage of this month's allowance. */
    percent: number;
};

/**
 * A manager's training department switcher (client decision 2026-09-23): the
 * departments they may train in and the one they are training in now. Null
 * unless a manager is on a learner route.
 */
export type TrainingContext = {
    departments: { id: number; name: string }[];
    currentDepartmentId: number | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
