<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';

/*
 * Copies one payment detail (account number, amount, reference) so the
 * customer can paste it into their banking app without typos.
 */
type Props = {
    value: string;
    /** What is copied, for screen readers: "Copy account number". */
    label: string;
};

const props = defineProps<Props>();

const { copy, copied, isSupported } = useClipboard({ legacy: true });

function copyValue(): void {
    void copy(props.value);
}
</script>

<template>
    <button
        v-if="isSupported"
        type="button"
        :aria-label="label"
        :class="
            copied
                ? 'border-success/40 bg-success-tint text-success-text'
                : 'border-line bg-surface text-brand-700 hover:border-brand-300 hover:bg-brand-50'
        "
        class="focus-visible:ring-brand-600/20 inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none"
        @click="copyValue"
    >
        <Check v-if="copied" class="size-4" aria-hidden="true" />
        <Copy v-else class="size-4" aria-hidden="true" />
        <span class="hidden sm:inline">
            {{ copied ? $t('Copied') : $t('Copy') }}
        </span>
        <span class="sr-only" aria-live="polite">
            {{ copied ? $t('Copied') : '' }}
        </span>
    </button>
</template>
