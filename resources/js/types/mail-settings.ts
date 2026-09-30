/*
 * Settings → Email (client request 2026-09-29): the SMTP mailbox every
 * email is sent from. The password never comes back from the server.
 */
export type MailMailer = 'smtp' | 'log';

export type MailEncryption = 'ssl' | 'tls' | 'none';

export type MailSettingsValues = {
    mailer: MailMailer;
    host: string;
    port: number;
    encryption: MailEncryption;
    username: string;
    fromAddress: string;
    fromName: string;
    replyTo: string;
    sendImmediately: boolean;
};

export type MailCheckStep = {
    key: string;
    status: 'ok' | 'failed' | 'skipped';
    message: string;
    detail: string | null;
};

export type MailTestResult = {
    status: 'ok' | 'failed';
    to: string;
    mailer: string;
    message: string;
    detail: string | null;
    steps?: MailCheckStep[];
    suggestion?: { port: number; encryption: MailEncryption } | null;
    unsaved?: boolean;
    at: string;
};

export type MailLogRow = {
    id: number;
    to: string | null;
    subject: string | null;
    status: 'sent' | 'failed' | 'logged';
    error: string | null;
    at: string | null;
};

export type MailQueueCounts = {
    waiting: number;
    failed: number;
};

export type MailSettingsPayload = {
    values: MailSettingsValues;
    stored: boolean;
    hasPassword: boolean;
    env: { mailer: string; host: string; fromAddress: string };
    lastTest: MailTestResult | null;
    updatedAt: string | null;
};
