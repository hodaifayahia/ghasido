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

export type MailTestResult = {
    status: 'ok' | 'failed';
    to: string;
    mailer: string;
    message: string;
    detail: string | null;
    at: string;
};

export type MailSettingsPayload = {
    values: MailSettingsValues;
    stored: boolean;
    hasPassword: boolean;
    env: { mailer: string; host: string; fromAddress: string };
    lastTest: MailTestResult | null;
    updatedAt: string | null;
};
