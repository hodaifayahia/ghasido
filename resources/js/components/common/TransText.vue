<script setup lang="ts">
import { computed, useSlots } from 'vue';
import { useI18n } from '@/composables/useI18n';

/*
 * A translated sentence with markup inside it (I18N-02): the whole sentence
 * is one key, so a language with another word order moves the marked-up
 * words too. Each `:name` in the key renders the slot of the same name.
 *
 *   <TransText text="In Safari, tap the :share button.">
 *       <template #share><strong>{{ $t('Share') }}</strong></template>
 *   </TransText>
 */
type Props = {
    text: string;
    tag?: string;
};

const props = withDefaults(defineProps<Props>(), { tag: 'span' });

const slots = useSlots();
const { t } = useI18n();

const parts = computed(() =>
    t(props.text)
        .split(/(:[A-Za-z_]+)/)
        .filter((part) => part !== '')
        .map((part) => {
            const name = part.startsWith(':') ? part.slice(1) : null;

            return name !== null && slots[name]
                ? { slot: name, text: '' }
                : { slot: null, text: part };
        }),
);
</script>

<template>
    <component :is="tag">
        <template v-for="(part, index) in parts" :key="index">
            <slot v-if="part.slot" :name="part.slot" />
            <template v-else>{{ part.text }}</template>
        </template>
    </component>
</template>
