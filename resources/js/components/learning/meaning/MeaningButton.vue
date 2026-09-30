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
    /** 24px chip for dense copy; the tap area stays 44px (ACC-03). */
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    controls: undefined,
    compact: false,
});

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
                'focus-visible:ring-brand-600/30 relative inline-grid shrink-0 place-items-center rounded-full align-middle transition-colors duration-150 before:absolute focus-visible:ring-3 focus-visible:outline-none active:scale-[.95] motion-reduce:transition-none',
                compact
                    ? 'size-6 before:-inset-2.5'
                    : 'size-8 before:-inset-1.5',
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
            :class="
                cn(
                    'animate-spin motion-reduce:animate-none',
                    compact ? 'size-3.5' : 'size-4',
                )
            "
            aria-hidden="true"
        />
        <Languages
            v-else
            :class="compact ? 'size-3.5' : 'size-4'"
            aria-hidden="true"
        />
    </button>
</template>
