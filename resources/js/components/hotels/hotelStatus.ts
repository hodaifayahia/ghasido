import { tk } from '@/lib/i18n';
import type { HotelCapacityState, HotelContractStatus } from '@/types';

/**
 * The status and capacity pills the Hotels screen shares between the table,
 * the mobile cards and the sidebar, so the two never drift apart.
 *
 * `pending` and `archived` were added with spec 0002: pending takes the
 * warning tint (it is waiting on somebody), archived the neutral grid tint.
 */
export const statusText: Record<HotelContractStatus, string> = {
    pending: tk('Pending'),
    active: tk('Active'),
    expiring: tk('Expiring Soon'),
    paused: tk('Paused'),
    ended: tk('Ended'),
    archived: tk('Archived'),
};

export const statusTone: Record<HotelContractStatus, string> = {
    pending: 'bg-warning-tint text-warning-text',
    active: 'bg-success-tint text-success-text',
    expiring: 'bg-warning-tint text-warning-text',
    paused: 'bg-brand-100/70 text-brand-700',
    ended: 'bg-danger-tint text-danger-text',
    archived: 'bg-tint-grid text-ink-muted',
};

export const capacityText: Record<HotelCapacityState, string> = {
    available: tk('Seats Available'),
    full: tk('At Capacity'),
    over: tk('Over Quota'),
};

export const capacityTone: Record<HotelCapacityState, string> = {
    available: 'bg-brand-100/65 text-brand-700',
    full: 'bg-warning-tint text-warning-text',
    over: 'bg-danger-tint text-danger-text',
};

export const quotaText: Record<HotelCapacityState, string> = {
    available: tk('Available'),
    full: tk('Full'),
    over: tk('Over quota'),
};

export const progressTone: Record<HotelCapacityState, 'brand' | 'warning'> = {
    available: 'brand',
    full: 'warning',
    over: 'warning',
};

export function seatPercent(used: number, total: number): number {
    if (total === 0) {
        return 0;
    }

    return Math.min(100, Math.round((used / total) * 100));
}

export function capacityOf(used: number, total: number): HotelCapacityState {
    if (total === 0) {
        return used > 0 ? 'over' : 'available';
    }

    if (used > total) {
        return 'over';
    }

    return used === total ? 'full' : 'available';
}
