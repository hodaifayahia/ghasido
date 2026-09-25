export type MessageMetricKey =
    | 'consentedEmployees'
    | 'scheduledReminders'
    | 'followUpNeeded'
    | 'sentToday'
    | 'templates';

export type MessageMetric = {
    key: MessageMetricKey;
    value: number;
    label: string;
    detail?: string;
};

export type MessageSelectOption = {
    value: string;
    label: string;
};

/** The five filter values the recipients table runs on (REM-02). */
export type MessageFilterValues = {
    hotel: string;
    department: string;
    consent: string;
    activity: string;
    search: string;
};

export type MessageFilters = MessageFilterValues & {
    hotels: MessageSelectOption[];
    departments: MessageSelectOption[];
    consents: MessageSelectOption[];
    activities: MessageSelectOption[];
};

export type MessageRecipientStatus =
    | 'active'
    | 'in_progress'
    | 'inactive'
    | 'consent_pending';

export type MessageConsentStatus = 'granted' | 'not_granted';

export type MessageRecipient = {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
    hotel: string;
    hotelId: number | null;
    department: string;
    departmentId: number | null;
    lastActivity: string;
    inactivityLabel: string;
    consentStatus: MessageConsentStatus;
    status: MessageRecipientStatus;
};

export type MessageTemplate = {
    id: number;
    name: string;
    subject: string;
    body: string;
    audience: string;
    trigger: string;
    isActive: boolean;
    updatedAt: string;
};

export type MessageAutomationRule = {
    id: number;
    name: string;
    /** The trigger as a sentence, e.g. "No progress for 5 days". */
    trigger: string;
    /** The stored trigger key, for the edit form (AutomationTrigger). */
    triggerKey: string;
    days: number | null;
    templateId: number;
    templateName: string;
    audience: string;
    hotelIds: number[];
    departmentIds: number[];
    active: boolean;
    lastRunAt: string | null;
};

export type MessageLogStatus =
    | 'sent'
    | 'scheduled'
    | 'queued'
    | 'blocked'
    | 'failed';

export type MessageLog = {
    id: number;
    recipient: string;
    template: string;
    channel: string;
    sentAt: string;
    status: MessageLogStatus;
    reason: string | null;
    automatic: boolean;
};

export type MessagePagination = {
    from: number;
    to: number;
    total: number;
    currentPage: number;
    lastPage: number;
    pages: Array<number | 'ellipsis'>;
};

export type MessageOptions = {
    channels: MessageSelectOption[];
    triggers: MessageSelectOption[];
    /** The placeholders a template may use, without braces (REM-04). */
    variables: string[];
    inactiveDays: number;
};

/**
 * What the signed in user may do, decided by the policies on the server
 * (ROLE-01). Presentation only: every route authorizes again.
 */
export type MessageAbilities = {
    send: boolean;
    manageTemplates: boolean;
    manageRules: boolean;
};

/**
 * Who the Send Reminder dialog addresses: explicit ids, or everyone matching
 * the current filters, resolved on the server (REM-02).
 */
export type MessageSendSelection = {
    ids: number[];
    all: boolean;
};

/** The rendered preview of a template for one sample employee. */
export type MessageTemplatePreview = {
    sample: {
        id: number;
        name: string;
        hotel: string | null;
        department: string | null;
    } | null;
    subject: string;
    body: string;
    values: Record<string, string>;
};
