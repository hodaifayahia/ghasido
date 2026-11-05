<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, watch } from 'vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useMeaning } from '@/composables/useMeaning';

/*
 * The Show Meaning panel of a lesson word, sentence or dialogue line whose
 * meaning is the stored translation of its English text (client request
 * 2026-11-05: no more fixed Arabic fields, any helper language). The parent
 * keeps its own button and open state; opening the panel asks the server
 * for the translation (POST /meaning, CTRL-02), and the panel says when it
 * is loading or not added yet. The simple English explanation, when there
 * is one, shows either way.
 */
type Props = {
    text: string;
    shown: boolean;
    explanation?: string | null;
    id?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    explanation: null,
    id: undefined,
    class: undefined,
});

const meaning = useMeaning(() => props.text);

watch(
    () => props.shown,
    (open) => {
        if (open !== meaning.shown.value && meaning.enabled()) {
            meaning.toggle();
        }
    },
    { immediate: true },
);

const state = computed(() =>
    meaning.enabled() ? meaning.state.value : 'ready',
);
</script>

<template>
    <ShowMeaningPanel
        :id="id"
        :shown="shown"
        :state="state"
        :arabic="meaning.arabic.value"
        :explanation="explanation"
        :class="props.class"
        @retry="meaning.retry()"
    />
</template>
