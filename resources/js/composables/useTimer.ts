import { useTimestamp } from '@vueuse/core';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';

export type UseTimerReturn = {
    /** Whole seconds left, never negative; null when there is no deadline. */
    remainingSeconds: ComputedRef<number | null>;
    expired: ComputedRef<boolean>;
    /** `mm:ss` (or `h:mm:ss`), `--:--` without a deadline. */
    label: ComputedRef<string>;
};

/*
 * A countdown driven by the SERVER's deadline (TIME-05). The page receives
 * `deadline_at` (and optionally the server's `now`) and the clock is
 * recomputed from those on every tick, so a refresh, a device switch or a
 * client clock that is wrong cannot reset or stretch a timed test. The
 * server remains the authority when the answer is submitted.
 */
export function useTimer(
    deadlineAt: MaybeRefOrGetter<string | null | undefined>,
    serverNow?: MaybeRefOrGetter<string | null | undefined>,
): UseTimerReturn {
    const now = useTimestamp({ interval: 500 });

    // Skew between the client clock and the server clock, captured once.
    const initialServerNow = toValue(serverNow);
    const skew =
        typeof initialServerNow === 'string'
            ? Date.parse(initialServerNow) - Date.now()
            : 0;

    const remainingSeconds = computed((): number | null => {
        const deadline = toValue(deadlineAt);

        if (!deadline) {
            return null;
        }

        const target = Date.parse(deadline);

        if (Number.isNaN(target)) {
            return null;
        }

        return Math.max(0, Math.ceil((target - (now.value + skew)) / 1000));
    });

    const expired = computed(() => remainingSeconds.value === 0);

    const label = computed((): string => {
        const total = remainingSeconds.value;

        if (total === null) {
            return '--:--';
        }

        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const seconds = total % 60;
        const mmss = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

        return hours > 0 ? `${hours}:${mmss}` : mmss;
    });

    return { remainingSeconds, expired, label };
}
