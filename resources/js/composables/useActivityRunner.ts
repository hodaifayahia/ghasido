import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import type { ActivityResult, ActivityView, AnswerMap } from '@/types';

/** How one option should render given the current answer and any result. */
export type OptionState = 'idle' | 'selected' | 'correct' | 'incorrect';

export type UseActivityRunnerReturn = {
    total: ComputedRef<number>;
    current: Ref<number>;
    /** The 1-based selected option per item, or the raw stored answer. */
    selected: (itemId: string) => string | null;
    /** Choose an option and advance to the next item (single-answer types). */
    choose: (itemId: string, optionId: string) => void;
    goto: (index: number) => void;
    prev: () => void;
    answeredIndexes: ComputedRef<number[]>;
    canCheck: ComputedRef<boolean>;
    locked: ComputedRef<boolean>;
    processing: ComputedRef<boolean>;
    submit: () => void;
    optionState: (itemId: string, optionId: string) => OptionState;
    itemVerdict: (itemId: string) => boolean | null;
};

/*
 * The shared answer state for the option-based practice activities
 * (PRAC-04, TEST-06, DATA-01; spec 0003 B.9): it keeps one answer per item,
 * advances on a choice so the mockups need no Next button, and posts every
 * answer verbatim as one attempt. After the server answers with `result`
 * the options can show correct / not quite (ACC-02). Never used on a test
 * page (that path is the test runner's).
 */
export function useActivityRunner(
    activity: MaybeRefOrGetter<ActivityView>,
    answerUrl: MaybeRefOrGetter<string>,
    result: MaybeRefOrGetter<ActivityResult | null>,
): UseActivityRunnerReturn {
    const startedAt = new Date().toISOString();
    const answers = reactive<AnswerMap>({});
    const form = useForm<{ answers: AnswerMap; started_at: string }>({
        answers: {},
        started_at: startedAt,
    });

    const items = computed(() => toValue(activity).items);
    const total = computed(() => items.value.length);
    const current = ref(0);

    const locked = computed(() => {
        const view = toValue(activity);

        return (
            toValue(result) !== null ||
            (view.attemptsLeft !== null && view.attemptsLeft <= 0)
        );
    });

    function selected(itemId: string): string | null {
        const value = answers[itemId];

        return typeof value === 'string' ? value : null;
    }

    function choose(itemId: string, optionId: string): void {
        if (locked.value) {
            return;
        }

        answers[itemId] = optionId;

        if (current.value < total.value - 1) {
            current.value += 1;
        }
    }

    const answeredIndexes = computed(() =>
        items.value
            .map((item, index) =>
                typeof answers[item.id] === 'string' ? index : -1,
            )
            .filter((index) => index >= 0),
    );

    const canCheck = computed(
        () =>
            total.value > 0 &&
            !locked.value &&
            items.value.every((item) => typeof answers[item.id] === 'string'),
    );

    function goto(index: number): void {
        current.value = Math.min(Math.max(index, 0), total.value - 1);
    }

    function prev(): void {
        if (current.value > 0) {
            current.value -= 1;
        }
    }

    function submit(): void {
        form.answers = { ...answers };
        form.started_at = startedAt;
        form.post(toValue(answerUrl), { preserveScroll: true });
    }

    function optionState(itemId: string, optionId: string): OptionState {
        const done = toValue(result);

        if (done === null) {
            return selected(itemId) === optionId ? 'selected' : 'idle';
        }

        const correct = done.correct[itemId];

        if (typeof correct === 'string' && correct === optionId) {
            return 'correct';
        }

        if (done.perItem[itemId] === false && selected(itemId) === optionId) {
            return 'incorrect';
        }

        return 'idle';
    }

    function itemVerdict(itemId: string): boolean | null {
        return toValue(result)?.perItem[itemId] ?? null;
    }

    return {
        total,
        current,
        selected,
        choose,
        goto,
        prev,
        answeredIndexes,
        canCheck,
        locked,
        processing: computed(() => form.processing),
        submit,
        optionState,
        itemVerdict,
    };
}
