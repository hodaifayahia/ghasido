import { tk } from '@/lib/i18n';

/*
 * What one learner on an individual plan gets (user request 2026-09-27).
 * Individuals get monthly AI points, never seats. Translated where they
 * render with `$t()`.
 */
export const individualInclusions = [
    tk('Every lesson for the department you choose'),
    tk('AI role-play practice with your monthly AI points'),
    tk('Audio at normal and slow speed'),
    tk('Arabic meaning whenever you need it'),
    tk('Your own phrasebook and progress'),
] as const;
