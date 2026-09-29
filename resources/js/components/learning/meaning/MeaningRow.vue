<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';
import MeaningPanel from '@/components/learning/meaning/MeaningPanel.vue';
import { useMeaning } from '@/composables/useMeaning';
import { cn } from '@/lib/utils';

/*
 * Show Meaning for a text that lives inside something already clickable —
 * a lesson link, an answer option (CTRL-01..03; client decisions
 * 2026-09-26). A button may not sit inside a link or another button, so the
 * row's own control goes in the slot, a compact eye control sits beside it,
 * and the Arabic opens underneath.
 */
type Props = {
    text?: string | null;
    class?: HTMLAttributes['class'];
    panelClass?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    text: null,
    class: undefined,
    panelClass: undefined,
});

const id = `meaning-${useId()}`;
const meaning = useMeaning(() => props.text ?? '');
</script>

<template>
    <div :class="cn('min-w-0', props.class)">
        <div v-if="meaning.enabled()" class="flex min-w-0 items-center gap-2">
            <div class="min-w-0 flex-1"><slot /></div>
            <button
                type="button"
                :aria-label="
                    meaning.shown.value
                        ? $t('Hide Meaning')
                        : $t('Show Meaning')
                "
                :aria-expanded="meaning.shown.value"
                :aria-controls="id"
                class="bg-tint-grid text-ink-graphite hover:bg-line focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full focus-visible:ring-3 focus-visible:outline-none"
                @click.stop.prevent="meaning.toggle()"
            >
                <component
                    :is="meaning.shown.value ? EyeOff : Eye"
                    class="size-5"
                    aria-hidden="true"
                />
            </button>
        </div>
        <slot v-else />
        <MeaningPanel
            :id="id"
            :shown="meaning.shown.value && meaning.enabled()"
            :state="meaning.state.value"
            :arabic="meaning.arabic.value"
            :class="panelClass"
            @retry="meaning.retry()"
        />
    </div>
</template>
