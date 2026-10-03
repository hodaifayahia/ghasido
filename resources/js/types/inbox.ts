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
