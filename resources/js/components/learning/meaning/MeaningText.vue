<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';
import MeaningPanel from '@/components/learning/meaning/MeaningPanel.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import { useMeaning } from '@/composables/useMeaning';
import { cn } from '@/lib/utils';

/*
 * Any English text with its Show Meaning button (CTRL-01..03; client
 * decisions 2026-09-26): the approved labelled button (إظهار المعنى / Show
 * Meaning) sits on top of the text, and the Arabic opens under the text only
 * after a tap and closes again. The button line follows the text's own
 * alignment (centred under a centred quote). `as` keeps the text's own
 * element (a heading stays a heading); `class` styles that element.
 */
type Props = {
    text?: string | null;
    as?: string;
    class?: HTMLAttributes['class'];
    wrapperClass?: HTMLAttributes['class'];
    panelClass?: HTMLAttributes['class'];
};

// Extra attributes (an id for aria-labelledby) belong on the text itself.
defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<Props>(), {
    text: null,
    as: 'p',
    class: undefined,
    wrapperClass: undefined,
    panelClass: undefined,
});

const id = `meaning-${useId()}`;
const meaning = useMeaning(() => props.text ?? '');
</script>

<template>
    <component
        :is="as === 'span' ? 'span' : 'div'"
        :class="cn('block min-w-0', wrapperClass)"
    >
        <!-- A tap on the button never reaches a card or link around it. -->
        <span
            v-if="meaning.enabled()"
            class="mb-2 block not-italic"
            @click.stop.prevent
        >
            <ShowMeaningButton
                size="sm"
                :shown="meaning.shown.value"
                :controls="id"
                class="inline-flex"
                @toggle="meaning.toggle()"
            />
        </span>
        <component :is="as" v-bind="$attrs" :class="props.class">
            <slot>{{ text }}</slot>
        </component>
        <MeaningPanel
            :id="id"
            :shown="meaning.shown.value && meaning.enabled()"
            :state="meaning.state.value"
            :arabic="meaning.arabic.value"
            :class="panelClass"
            @retry="meaning.retry()"
        />
    </component>
</template>
