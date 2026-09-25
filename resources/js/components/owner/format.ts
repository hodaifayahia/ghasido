/*
 * Number formats for the owner console (spec 0007). Dollars keep two
 * decimals, and four below one dollar so a cheap model's cost is never
 * shown as $0.00; counts use thousands separators.
 */
const count = new Intl.NumberFormat('en-GB');
const dollars = new Intl.NumberFormat('en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const cents = new Intl.NumberFormat('en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

export function formatUsd(value: number): string {
    const sign = value < 0 ? '−' : '';
    const abs = Math.abs(value);

    return `${sign}$${abs >= 1 || abs === 0 ? dollars.format(abs) : cents.format(abs)}`;
}

export function formatCount(value: number): string {
    return count.format(value);
}

export function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
