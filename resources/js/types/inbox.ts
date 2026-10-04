export type InboxReply = {
    id: number;
    body: string;
    sender: string;
    sentAt: string;
};

export type InboxMessage = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    organisation: string | null;
    topic: string | null;
    fromAccount: boolean;
    message: string;
    sentAt: string;
    read: boolean;
    replies: InboxReply[];
};

export type LevelRequestRow = {
    id: number;
    name: string;
    username: string | null;
    hotel: string | null;
    from: string | null;
    to: string;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    note: string | null;
    sentAt: string;
    decidedAt: string | null;
};
