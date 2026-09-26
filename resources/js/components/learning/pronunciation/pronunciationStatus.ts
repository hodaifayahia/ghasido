import { CircleAlert, CircleCheck, CircleMinus, CircleX } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { t, tk } from '@/lib/i18n';
import type { PronunciationLevel, PronunciationWordStatus } from '@/types';

/*
 * How each verdict of the pronunciation check is shown (spec 0006 §5):
 * always an icon and a word as well as a colour, never colour alone
 * (ACC-02). Tones are the Guesvia semantic tint/text pairs. Labels are
 * translation keys, translated where they render (`$t(style.label)`).
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
    correct: {
        label: tk('Clear (pronunciation)'),
        icon: CircleCheck,
        chip: good,
    },
    unclear: { label: tk('Not clear'), icon: CircleAlert, chip: close },
    almost: { label: tk('Almost'), icon: CircleAlert, chip: close },
    mispronounced: {
        label: tk('Sounded different'),
        icon: CircleX,
        chip: wrong,
    },
    different: { label: tk('Different word'), icon: CircleX, chip: wrong },
    missed: { label: tk('Not heard'), icon: CircleX, chip: wrong },
    skipped: { label: tk('Not checked'), icon: CircleMinus, chip: neutral },
};

export const LEVEL_STYLE: Record<PronunciationLevel, StatusStyle> = {
    excellent: { label: tk('Excellent'), icon: CircleCheck, chip: good },
    good: { label: tk('Good'), icon: CircleCheck, chip: good },
    fair: { label: tk('Keep practising'), icon: CircleAlert, chip: close },
    try_again: { label: tk('Try again'), icon: CircleX, chip: wrong },
    not_heard: { label: tk('Not heard'), icon: CircleMinus, chip: neutral },
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
            if (!heard) {
                return t('It sounded like another word.');
            }

            return sound
                ? t('We heard “:heard” (:sound).', { heard, sound })
                : t('We heard “:heard”.', { heard });
        case 'different':
            return heard
                ? t('We heard “:heard”, a different word.', { heard })
                : t('We heard a different word.');
        case 'missed':
            return t('Not heard.');
        case 'almost':
            return t('Almost. Say it a little more clearly.');
        case 'unclear':
            return t('Right word, but not very clear.');
        default:
            return '';
    }
}
