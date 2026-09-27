/*
 * Prices on the public checkout: Algeria pays in DZD, everyone else in USD
 * (client decision 2026-09-26). Numbers keep their own formats whatever the
 * interface language.
 */
export type Currency = 'DZD' | 'USD';

export function formatAmount(amount: number, currency: Currency): string {
    if (currency === 'USD') {
        return `$${new Intl.NumberFormat('en-US', {
            minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount)}`;
    }

    return new Intl.NumberFormat('fr-DZ').format(amount);
}

/** "12 000 DZD" or "$25 USD". */
export function formatPrice(amount: number, currency: Currency): string {
    return `${formatAmount(amount, currency)} ${currency}`;
}

/** A file size for people: "245 KB", "1.4 MB". */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
