export type AppNotification = {
    id: number;
    channel: 'email' | 'in_app';
    subject: string;
    body: string;
    sentAt: string;
    /** Recharge requests remain until paid; reminders expire after 24 hours. */
    expiresAt: string | null;
    read: boolean;
    readUrl: string;
};

export type NotificationData = {
    unread: number;
    items: AppNotification[];
};
