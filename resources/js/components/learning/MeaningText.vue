<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { computed, useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { LessonMeaning } from '@/types';

/*
 * Any piece of lesson text with its Show Meaning (CTRL-01..03; user request
 * 2026-09-25): the English stays as it is (the default slot), and when the
 * admin wrote an Arabic meaning a Show Meaning control sits under it; one tap
 * opens the Arabic (Cairo, RTL) and the simple explanation, a second closes
 * them. With no meaning the slot renders alone, so the layout never changes.
 *
 * `compact` is the small eye chip for tight rows (objective lines); the
 * default is the approved 44px Show Meaning button. Never used in a test
 * (CTRL-04): tests carry no lesson copy.
 */
type Props = {
    meaning?: LessonMeaning | null;
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    meaning: null,
    compact: false,
});

const id = useId();
const hasMeaning = computed(() => (props.meaning?.arabic ?? '').trim() !== '');
const { shown, toggle } = useShowMeaning(hasMeaning);
</script>

<template>
    <slot v-if="!hasMeaning" />

    <div v-else :class="cn('grid min-w-0 gap-2', props.class)">
        <div v-if="compact" class="flex min-w-0 items-center gap-2">
            <div class="min-w-0 flex-1"><slot /></div>
            <button
                type="button"
                :aria-expanded="shown"
                :aria-controls="`${id}-meaning`"
                class="bg-tint-grid text-ink-graphite hover:bg-line focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full transition-colors focus-visible:ring-3 focus-visible:outline-none"
                :title="shown ? 'Hide Meaning' : 'Show Meaning'"
                @click="toggle"
            >
                <component
                    :is="shown ? EyeOff : Eye"
                    class="size-5"
                    aria-hidden="true"
                />
                <span class="sr-only">{{
                    shown ? 'Hide Meaning' : 'Show Meaning'
                }}</span>
            </button>
        </div>
        <template v-else>
            <slot />
            <ShowMeaningButton
                size="sm"
                :shown="shown"
                :controls="`${id}-meaning`"
                class="w-fit"
                @toggle="toggle"
            />
        </template>

        <ShowMeaningPanel
            :id="`${id}-meaning`"
            :shown="shown"
            :arabic="meaning?.arabic ?? null"
            :explanation="meaning?.explanation ?? null"
        />
    </div>
</template>
