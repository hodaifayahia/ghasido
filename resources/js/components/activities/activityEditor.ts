/**
 * Shared pieces of the activity editor (client report 2026-09-29): how its
 * audio chips generate and refresh, and the class strings its rows share.
 */

/** Where the Generate Audio chips render and what they reload. */
export type AudioChipOptions = {
    /** The lesson whose accent voice is used; null = the platform voice. */
    lessonId?: number | null;
    /** The page props to reload while clips generate. */
    reloadOnly?: string[];
};

export const smallButton =
    'text-brand-800 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex size-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';

export const removeButton =
    'text-danger-text hover:bg-danger-tint focus-visible:border-danger focus-visible:ring-danger/15 inline-flex size-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';

export const addButton =
    'border-line text-brand-700 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-9 items-center gap-1.5 self-start rounded-md border border-dashed px-3 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';

export const inputClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full min-w-0 rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';

export const sectionLabel =
    'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
