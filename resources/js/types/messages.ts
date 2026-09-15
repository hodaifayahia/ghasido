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

export type MessageFilters = {
    hotel: string;
    department: string;
    consent: string;
    activity: string;
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
    hotel: string;
    department: string;
    lastActivity: string;
    inactivityLabel: string;
    consentStatus: MessageConsentStatus;
    status: MessageRecipientStatus;
};

export type MessageTemplate = {
    id: number;
    name: string;
    audience: string;
    trigger: string;
    updatedAt: string;
};

export type MessageAutomationRule = {
    id: number;
    name: string;
    trigger: string;
    audience: string;
    active: boolean;
};

export type MessageLogStatus = 'sent' | 'scheduled' | 'blocked';

export type MessageLog = {
    id: number;
    recipient: string;
    template: string;
    sentAt: string;
    status: MessageLogStatus;
};
