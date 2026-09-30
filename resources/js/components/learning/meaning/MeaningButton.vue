<script setup lang="ts">
import { Languages, LoaderCircle } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import type { MeaningState } from '@/composables/useMeaning';
import { cn } from '@/lib/utils';
import { useHelperLanguage } from '@/composables/useHelperLanguage';

/*
 * The small 🌐 Show Meaning button that sits after any English text
 * (CTRL-01..03; client decision 2026-09-26). A 32px chip in the brand tint;
 * an invisible ring widens the tap area to 44px (ACC-03). The parent owns
 * the state through useMeaning.
 */
type Props = {
    shown: boolean;
    state: MeaningState;
    controls?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { controls: undefined });

const emit = defineEmits<{ toggle: [] }>();

const helper = useHelperLanguage();
</script>

<template>
    <button
        type="button"
        :aria-expanded="shown"
        :aria-controls="controls"
        :aria-label="
            shown
                ? $t('Hide meaning')
                : $t('Show meaning in :language', {
                      language: helper.name.value,
                  })
        "
        :title="shown ? $t('Hide meaning') : $t('Show meaning')"
        :class="
            cn(
                'focus-visible:ring-brand-600/30 relative inline-grid size-8 shrink-0 place-items-center rounded-full align-middle transition-colors duration-150 before:absolute before:-inset-1.5 focus-visible:ring-3 focus-visible:outline-none active:scale-[.95] motion-reduce:transition-none',
                shown
                    ? 'bg-brand-600 text-white'
                    : 'bg-brand-50 text-brand-700 hover:bg-brand-100',
                props.class,
            )
        "
        @click.stop.prevent="emit('toggle')"
    >
        <LoaderCircle
            v-if="state === 'loading' && shown"
            class="size-4 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
        />
        <Languages v-else class="size-4" aria-hidden="true" />
    </button>
</template>
