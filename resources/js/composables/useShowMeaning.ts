import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { computed, ref, toValue, watch } from 'vue';

export type UseShowMeaningReturn = {
    /** Whether the Arabic panel is open right now. */
    shown: Ref<boolean>;
    /** False during a test, or when the CMS switched it off for this item. */
    enabled: ComputedRef<boolean>;
    toggle: () => void;
    hide: () => void;
};

/*
 * The Show Meaning contract for one item (CTRL-01..04): English is always
 * there, the Arabic panel opens only on an explicit tap and closes again.
 * When `enabled` is false the panel can never open, which is the test-page
 * rule (CTRL-04, TEST-03); the test payload carries no Arabic anyway.
 */
export function useShowMeaning(
    enabled: MaybeRefOrGetter<boolean> = true,
): UseShowMeaningReturn {
    const shown = ref(false);
    const isEnabled = computed(() => toValue(enabled));

    watch(isEnabled, (value) => {
        if (!value) {
            shown.value = false;
        }
    });

    function toggle(): void {
        shown.value = isEnabled.value ? !shown.value : false;
    }

    function hide(): void {
        shown.value = false;
    }

    return { shown, enabled: isEnabled, toggle, hide };
}
