/**
 * The small square action buttons on the rows of the Messages & Reminders
 * list modals (templates, rules, log). 44px on a phone so a thumb can hit
 * them (ACC-03), 28px from md up.
 */
export const messagesListIconButton =
    'border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex size-11 shrink-0 items-center justify-center rounded-md border focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none md:size-7';

/** The Delete variant: danger is outline only (AGENTS.md §3). */
export const messagesListDeleteButton =
    'border-danger/40 text-danger hover:bg-danger-tint bg-surface inline-flex size-11 shrink-0 items-center justify-center rounded-md border focus-visible:border-danger focus-visible:ring-danger/15 focus-visible:ring-3 focus-visible:outline-none md:size-7';
