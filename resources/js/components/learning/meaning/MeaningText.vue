<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';
import MeaningButton from '@/components/learning/meaning/MeaningButton.vue';
import MeaningPanel from '@/components/learning/meaning/MeaningPanel.vue';
import { useMeaning } from '@/composables/useMeaning';
import { cn } from '@/lib/utils';

/*
 * Any English text with its Show Meaning button (CTRL-01..03; client
 * decision 2026-09-26): the text is always there, the Arabic opens under
 * it only after a tap and closes again. `as` keeps the text's own element
 * (a heading stays a heading); `class` styles that element.
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
        <component :is="as" v-bind="$attrs" :class="props.class">
            <slot>{{ text }}</slot>
            <MeaningButton
                v-if="meaning.enabled()"
                :shown="meaning.shown.value"
                :state="meaning.state.value"
                :controls="id"
                class="ms-2 -mt-0.5"
                @toggle="meaning.toggle()"
            />
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
