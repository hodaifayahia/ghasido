/**
 * Payments sent from the checkout (client request 2026-09-27): the admin
 * list at /payments (Admin\PaymentsController).
 */
import type { RequestedHelperLanguage } from './learning';
export type PaymentStatus = 'pending' | 'confirmed' | 'rejected';

export type PaymentStatusFilter = PaymentStatus | 'all';

export type PaymentCustomerType = 'hotel' | 'individual';

export type PaymentTypeFilter = PaymentCustomerType | 'all';

export type PaymentRow = {
    id: number;
    type: PaymentCustomerType;
    /** The hotel's name, or the individual subscriber's name. */
    customer: string;
    city: string | null;
    payerName: string;
    payerEmail: string;
    payerPhone: string | null;
    planName: string;
    amount: number;
    currency: string;
    /** The payment method's name, e.g. BaridiMob. */
    method: string;
    reference: string | null;
    /** Streams the private receipt inline; null when only a reference was sent. */
    receiptUrl: string | null;
    receiptName: string | null;
    /** In bytes. */
    receiptSize: number | null;
    isImage: boolean;
    status: PaymentStatus;
    /** Already translated by the server. */
    statusLabel: string;
    rejectionReason: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    unread: boolean;
    /** Where the account is approved: the hotel page or the individuals list. */
    accountUrl: string | null;
    accountState: string | null;
    hotelId: number | null;
    individualId: number | null;
    /** Asked for at sign-up (client request 2026-10-01). */
    helperLanguages: RequestedHelperLanguage[];
};

export type PaymentFilters = {
    status: PaymentStatusFilter;
    type: PaymentTypeFilter;
    search: string;
};

export type PaymentCounts = Record<PaymentStatus, number>;

export type PaymentPagination = {
    currentPage: number;
    lastPage: number;
    total: number;
    from: number;
    to: number;
};
