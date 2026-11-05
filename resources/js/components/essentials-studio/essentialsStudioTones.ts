import type { CourseTone } from '@/types';

/*
 * Colours of the English Essentials studio (client request 2026-11-05):
 * each topic keeps its tone, each step type a chip tint, so a lesson's
 * shape reads at a glance. Token utilities only (AGENTS.md §0.2 rule 3).
 */
export const topicTone: Record<CourseTone, string> = {
    brand: 'bg-brand-50 text-brand-700',
    aqua: 'bg-aqua-tint text-aqua',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning-text',
    gold: 'bg-gold-tint text-warning-text',
    danger: 'bg-danger-tint text-danger-text',
    ai: 'bg-ai-tint text-ai',
    azure: 'bg-brand-50 text-brand-600',
    sunset: 'bg-warning-tint text-warning-text',
    blossom: 'bg-danger-tint text-danger-text',
};

const stepTones: Record<string, string> = {
    situation: 'bg-brand-50 text-brand-700',
    vocabulary: 'bg-success-tint text-success-text',
    expressions: 'bg-gold-tint text-warning-text',
    listen_repeat: 'bg-aqua-tint text-aqua',
    dialogue: 'bg-ai-tint text-ai',
    practice: 'bg-warning-tint text-warning-text',
    quiz: 'bg-warning-tint text-warning-text',
    complete: 'bg-success-tint text-success-text',
};

export function stepTone(type: string): string {
    return stepTones[type] ?? 'bg-tint-header text-ink-slate';
}
