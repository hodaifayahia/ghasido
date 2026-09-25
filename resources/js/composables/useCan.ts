import { usePage } from '@inertiajs/vue3';

export type UseCanReturn = {
    /** Whether the signed in user holds this permission (spec 0001, AC-6). */
    can: (permission: string) => boolean;
};

/**
 * Reads `auth.permissions` from the shared Inertia props.
 *
 * This is presentation only: it decides what a user is shown, never what they
 * may reach. Every route is authorized again on the server, so hiding an item
 * is a courtesy and not a boundary (spec 0001, invariant 4; ROLE-01, SEC-01).
 */
export function useCan(): UseCanReturn {
    const page = usePage();

    function can(permission: string): boolean {
        return page.props.auth.permissions.includes(permission);
    }

    return { can };
}
