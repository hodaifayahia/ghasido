import { CircleAlert, CircleCheck, CircleMinus, CircleX } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { PronunciationLevel, PronunciationWordStatus } from '@/types';

/*
 * How each verdict of the pronunciation check is shown (spec 0006 §5):
 * always an icon and a word as well as a colour, never colour alone
 * (ACC-02). Tones are the Guesvia semantic tint/text pairs.
 */
export type StatusStyle = {
    label: string;
    icon: LucideIcon;
    chip: string;
};

const good = 'bg-success-tint text-success-text';
const close = 'bg-warning-tint text-warning-text';
const wrong = 'bg-danger-tint text-danger-text';
const neutral = 'bg-tint-grid text-ink-slate';

export const WORD_STATUS: Record<PronunciationWordStatus, StatusStyle> = {
    correct: { label: 'Clear', icon: CircleCheck, chip: good },
    unclear: { label: 'Not clear', icon: CircleAlert, chip: close },
    almost: { label: 'Almost', icon: CircleAlert, chip: close },
    mispronounced: { label: 'Sounded different', icon: CircleX, chip: wrong },
    different: { label: 'Different word', icon: CircleX, chip: wrong },
    missed: { label: 'Not heard', icon: CircleX, chip: wrong },
    skipped: { label: 'Not checked', icon: CircleMinus, chip: neutral },
};

export const LEVEL_STYLE: Record<PronunciationLevel, StatusStyle> = {
    excellent: { label: 'Excellent', icon: CircleCheck, chip: good },
    good: { label: 'Good', icon: CircleCheck, chip: good },
    fair: { label: 'Keep practising', icon: CircleAlert, chip: close },
    try_again: { label: 'Try again', icon: CircleX, chip: wrong },
    not_heard: { label: 'Not heard', icon: CircleMinus, chip: neutral },
};

/**
 * One line explaining a weak word: what was heard and the sound, in plain
 * words for a low-level learner.
 */
export function weakWordHint(
    status: PronunciationWordStatus,
    heard: string | null,
    sound: string | null,
): string {
    switch (status) {
        case 'mispronounced':
            return heard
                ? `We heard “${heard}”${sound ? ` (${sound})` : ''}.`
                : 'It sounded like another word.';
        case 'different':
            return heard
                ? `We heard “${heard}”, a different word.`
                : 'We heard a different word.';
        case 'missed':
            return 'Not heard.';
        case 'almost':
            return 'Almost. Say it a little more clearly.';
        case 'unclear':
            return 'Right word, but not very clear.';
        default:
            return '';
    }
}
