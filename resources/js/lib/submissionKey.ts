/*
 * One key per practice submission (client report 2026-11-05; AGENTS.md §7,
 * PROG-04): the page sends it with "Check", so a retried request of the
 * same submission finds the attempt it already saved instead of writing a
 * second one. A new try ("Try again") gets a new key, so it is a new attempt.
 */
export function newSubmissionKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    const random = Math.random().toString(36).slice(2);

    return `${Date.now().toString(36)}-${random}`;
}
