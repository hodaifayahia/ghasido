import { intlLocale, t } from '@/lib/i18n';
import type {
    PracticeAttemptRow,
    PronunciationHistoryEntry,
    TestSittingRow,
} from '@/types';

/** How one attempt went, always shown as an icon AND a word (ACC-02). */
export type AttemptVerdict =
    | 'correct'
    | 'incorrect'
    | 'evaluating'
    | 'failed'
    | 'done'
    | 'in_progress';

/*
 * One row of "Your attempts" (client report 2026-11-05), whatever it
 * records: a practice answer, a pronunciation check, a role-play
 * conversation or a test sitting. Each try is its own saved row on the
 * server; this is only its shape on the page.
 */
export type AttemptHistoryRow = {
    id: number;
    attemptNo: number;
    date: string | null;
    /** Already formatted: "2 / 3", "80%", "75 / 100". */
    score: string | null;
    verdict: AttemptVerdict | null;
    answer: string | null;
    feedback: string | null;
    recordingUrl: string | null;
    /** Opens the attempt on its own page; otherwise the row emits select. */
    href: string | null;
};

export function formatAttemptDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(intlLocale(), {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}

export function verdictLabel(verdict: AttemptVerdict): string {
    switch (verdict) {
        case 'correct':
            return t('Correct');
        case 'incorrect':
            return t('Not quite');
        case 'evaluating':
            return t('Evaluating…');
        case 'failed':
            return t('Not evaluated');
        case 'in_progress':
            return t('In progress');
        default:
            return t('Done');
    }
}

function number(value: number): string {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

export function fromPractice(rows: PracticeAttemptRow[]): AttemptHistoryRow[] {
    return rows.map((row) => {
        let verdict: AttemptVerdict | null = null;

        if (row.aiStatus === 'pending' || row.aiStatus === 'running') {
            verdict = 'evaluating';
        } else if (row.aiStatus === 'failed') {
            verdict = 'failed';
        } else if (row.isCorrect === true) {
            verdict = 'correct';
        } else if (row.isCorrect === false) {
            verdict = 'incorrect';
        } else if (row.aiStatus === 'done') {
            verdict = 'done';
        }

        return {
            id: row.id,
            attemptNo: row.attemptNo,
            date: row.submittedAt,
            score:
                row.score === null
                    ? null
                    : row.maxScore === null
                      ? number(row.score)
                      : `${number(row.score)} / ${number(row.maxScore)}`,
            verdict,
            answer: row.answer !== '' ? row.answer : null,
            feedback: row.feedback,
            recordingUrl: row.recordingUrl,
            href: row.url,
        };
    });
}

export function fromPronunciation(
    entries: PronunciationHistoryEntry[],
): AttemptHistoryRow[] {
    return entries.map((entry) => ({
        id: entry.id,
        attemptNo: entry.attemptNo,
        date: entry.createdAt,
        score: entry.score === null ? null : `${Math.round(entry.score)}%`,
        verdict:
            entry.status === 'failed'
                ? 'failed'
                : entry.settled
                  ? 'done'
                  : 'evaluating',
        answer: null,
        feedback: entry.levelLabel ?? entry.feedback?.headline ?? null,
        recordingUrl: entry.recordingUrl,
        href: null,
    }));
}

export type RoleplayAttemptRow = {
    id: number;
    attemptNo: number;
    startedAt: string;
    status: string;
    aiStatus: string | null;
    score: number | null;
    turns: number;
    summary: string | null;
    url: string;
};

export function fromRoleplay(rows: RoleplayAttemptRow[]): AttemptHistoryRow[] {
    return rows.map((row) => {
        let verdict: AttemptVerdict = 'done';

        if (row.status === 'in_progress') {
            verdict = 'in_progress';
        } else if (row.aiStatus === 'failed') {
            verdict = 'failed';
        } else if (row.aiStatus !== 'done' && row.status !== 'abandoned') {
            verdict = 'evaluating';
        }

        return {
            id: row.id,
            attemptNo: row.attemptNo,
            date: row.startedAt,
            score: row.score === null ? null : `${row.score} / 100`,
            verdict,
            answer: t(':count replies', { count: row.turns }),
            feedback: row.summary,
            recordingUrl: null,
            href: row.url,
        };
    });
}

export function fromTestSittings(rows: TestSittingRow[]): AttemptHistoryRow[] {
    return rows.map((row) => ({
        id: row.id,
        attemptNo: row.attemptNo,
        date: row.submittedAt,
        score: row.percent === null ? null : `${row.percent}%`,
        verdict: null,
        answer: null,
        feedback:
            row.passed === null
                ? null
                : row.passed
                  ? t('Passed')
                  : t('Not passed yet'),
        recordingUrl: null,
        href: row.url,
    }));
}
