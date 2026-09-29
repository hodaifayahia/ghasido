import { router, usePage } from '@inertiajs/vue3';
import { lessonsContent } from '@/routes';

/**
 * Every control of the Lessons & Content screen lives in the query string
 * (spec 0003 Part D): the five selects, the active tab, the tree's expanded
 * nodes and the image library filters. A reload lands on the same lesson.
 */
export type LessonsQueryKey =
    | 'hotel'
    | 'department'
    | 'course'
    | 'unit'
    | 'lesson'
    | 'tab'
    | 'open'
    | 'lib'
    | 'libCategory'
    | 'libSearch';

export type LessonsQueryPatch = Partial<Record<LessonsQueryKey, string | null>>;

type VisitOptions = {
    only?: string[];
    replace?: boolean;
};

type LessonsFilterProps = Partial<
    Record<'hotel' | 'department' | 'course' | 'unit' | 'lesson', string>
>;

/**
 * The current query string as a plain record. The dedicated builder URL
 * (`/lessons/{id}/edit`) carries no query, so there the open lesson's scope
 * comes from the page's filters: otherwise the first tab click or save would
 * land on a URL without the lesson and close the builder.
 */
export function currentLessonsQuery(): Record<string, string> {
    if (typeof window === 'undefined') {
        return {};
    }

    const query: Record<string, string> = {};

    if (/^\/lessons\/\d+\/edit\/?$/.test(window.location.pathname)) {
        const filters = (usePage().props.filters ?? {}) as LessonsFilterProps;

        for (const key of [
            'hotel',
            'department',
            'course',
            'unit',
            'lesson',
        ] as const) {
            const value = filters[key];

            if (typeof value === 'string' && value !== '') {
                query[key] = value;
            }
        }
    }

    new URLSearchParams(window.location.search).forEach((value, key) => {
        query[key] = value;
    });

    return query;
}

/** The screen URL with the patch applied; empty and null values drop the key. */
export function lessonsUrl(patch: LessonsQueryPatch): string {
    const query = currentLessonsQuery();

    for (const [key, value] of Object.entries(patch)) {
        if (value === null || value === undefined || value === '') {
            delete query[key];
        } else {
            query[key] = value;
        }
    }

    return lessonsContent.url({ query });
}

/**
 * Navigate with the patch, keeping component state and scroll (AGENTS.md §5).
 * `only` narrows the reload to the props that can change.
 */
export function visitLessons(
    patch: LessonsQueryPatch,
    options: VisitOptions = {},
): void {
    router.get(
        lessonsUrl(patch),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: options.replace ?? false,
            only: options.only,
        },
    );
}

/** Selecting a parent resets every child select and the hand-opened nodes. */
export function selectionPatch(
    key: 'hotel' | 'department' | 'course' | 'unit' | 'lesson',
    value: string,
): LessonsQueryPatch {
    const order = ['hotel', 'department', 'course', 'unit', 'lesson'] as const;
    const patch: LessonsQueryPatch = { [key]: value, open: null };

    for (const child of order.slice(order.indexOf(key) + 1)) {
        patch[child] = null;
    }

    return patch;
}

/**
 * Expand or collapse one tree node. `c<id>` / `u<id>` opens a node the
 * server would keep closed; `-c<id>` / `-u<id>` closes one it would open
 * (the selected course and unit).
 */
export function toggleOpenPatch(
    open: string[] | undefined,
    node: string,
    currentlyExpanded: boolean,
): LessonsQueryPatch {
    const set = new Set(open ?? []);

    if (currentlyExpanded) {
        set.delete(node);
        set.add(`-${node}`);
    } else {
        set.delete(`-${node}`);
        set.add(node);
    }

    return { open: set.size === 0 ? null : [...set].join(',') };
}
