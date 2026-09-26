import type { ProgressTone } from '@/components/data/ProgressBar.vue';
import { tk } from '@/lib/i18n';
import type { EmployeeStatus } from '@/types';

/**
 * The status pill and progress bar looks of an employee row, shared by the
 * table, the mobile cards and the View dialog (11-components.md §11.3).
 */
export const statusText: Record<EmployeeStatus, string> = {
    completed: tk('Completed'),
    in_progress: tk('In Progress'),
    not_started: tk('Not Started'),
    inactive: tk('Inactive'),
};

export const statusTone: Record<EmployeeStatus, string> = {
    completed: 'bg-success-tint text-success-text',
    in_progress: 'bg-brand-100/65 text-brand-700',
    not_started: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
};

export const progressTone: Record<EmployeeStatus, ProgressTone> = {
    completed: 'success',
    in_progress: 'azure',
    not_started: 'azure',
    inactive: 'azure',
};

/** A password for the Generate button: unambiguous letters and digits. */
export function generatePassword(length: number): string {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    const values = new Uint32Array(length);

    for (;;) {
        crypto.getRandomValues(values);
        const password = Array.from(
            values,
            (value) => alphabet[value % alphabet.length],
        ).join('');

        if (/[0-9]/.test(password)) {
            return password;
        }
    }
}
