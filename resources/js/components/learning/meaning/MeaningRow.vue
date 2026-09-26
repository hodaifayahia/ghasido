<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';
import MeaningButton from '@/components/learning/meaning/MeaningButton.vue';
import MeaningPanel from '@/components/learning/meaning/MeaningPanel.vue';
import { useMeaning } from '@/composables/useMeaning';
import { cn } from '@/lib/utils';

/*
 * Show Meaning for a text that lives inside something already clickable —
 * a lesson link, an answer option (CTRL-01..03; client decision
 * 2026-09-26). A button may not sit inside a link or another button, so the
 * row's own control goes in the slot and the meaning button sits beside it,
 * with the Arabic opening underneath.
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
        <div class="flex items-center gap-2">
            <div class="min-w-0 flex-1">
                <slot />
            </div>
            <MeaningButton
                v-if="meaning.enabled()"
                :shown="meaning.shown.value"
                :state="meaning.state.value"
                :controls="id"
                @toggle="meaning.toggle()"
            />
        </div>
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
