import { intlLocale, tk } from '@/lib/i18n';

/**
 * Formatting shared by the payment review on the hotel page and on the
 * individuals list (client request 2026-09-27).
 */
export type ReviewPaymentStatus = 'pending' | 'confirmed' | 'rejected';

/** Status pill pairs from the design system (AGENTS.md §3 colour law). */
export const paymentStatusTone: Record<ReviewPaymentStatus, string> = {
    pending: 'bg-warning-tint text-warning-text',
    confirmed: 'bg-success-tint text-success-text',
    rejected: 'bg-danger-tint text-danger-text',
};

/** Labels for rows that do not carry a translated `statusLabel`. */
export const paymentStatusLabel: Record<ReviewPaymentStatus, string> = {
    pending: tk('Awaiting approval'),
    confirmed: tk('Confirmed'),
    rejected: tk('Rejected'),
};

/** DZD without decimals (`12 000 DZD`), anything else as a currency. */
export function formatMoney(amount: number, currency: string): string {
    if (currency === 'DZD') {
        return `${new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 0 }).format(amount)} DZD`;
    }

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${amount} ${currency}`;
    }
}

/** Day, month, year and time in the interface language. */
export function formatSubmitted(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(intlLocale(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

/**
 * A wa.me link for a phone number typed at checkout. `+213…` and `00213…`
 * keep their country code; a local number starting with a single 0 is
 * read as Algerian, the home market.
 */
export function whatsappUrl(phone: string): string | null {
    let digits = phone.replace(/[^\d+]/g, '');

    if (digits.startsWith('+')) {
        digits = digits.slice(1);
    } else if (digits.startsWith('00')) {
        digits = digits.slice(2);
    } else if (digits.startsWith('0')) {
        digits = `213${digits.slice(1)}`;
    }

    digits = digits.replace(/\D/g, '');

    return digits.length >= 8 ? `https://wa.me/${digits}` : null;
}

/** `tel:` keeps the leading + and the digits only. */
export function telUrl(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}
