<script setup lang="ts">
import { Check, Loader2, X } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';

/*
 * The practice "Check" button (PRAC-04, ACC-02; spec 0003 H.2): disabled
 * until the learner has answered; after the result it turns into the
 * success or danger tint with an icon AND a word, never colour alone.
 * Never used inside a test (TEST-03): the runner saves on Next.
 */
type Props = {
    disabled?: boolean;
    loading?: boolean;
    label?: string;
    state?: 'idle' | 'correct' | 'incorrect' | 'submitted';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    loading: false,
    label: undefined,
    state: 'idle',
});

const emit = defineEmits<{ click: [] }>();

const { t } = useI18n();

const text = computed(() => {
    switch (props.state) {
        case 'correct':
            return t('Correct');
        case 'incorrect':
            return t('Not quite');
        case 'submitted':
            return t('Answer saved');
        default:
            return props.label ?? t('Check');
    }
});

const tone = computed(() => {
    switch (props.state) {
        case 'correct':
            return 'bg-success-tint text-success-text';
        case 'incorrect':
            return 'bg-danger-tint text-danger-text';
        case 'submitted':
            return 'bg-brand-50 text-brand-700';
        default:
            return 'bg-brand-600 shadow-btn hover:bg-brand-700 text-white';
    }
});
</script>

<template>
    <button
        type="button"
        :disabled="disabled || loading || state !== 'idle'"
        :aria-live="state !== 'idle' ? 'polite' : undefined"
        :class="
            cn(
                'font-heading ease-brand focus-visible:ring-brand-600/40 inline-flex h-12 min-w-40 items-center justify-center gap-2 rounded-lg px-8 text-base font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:cursor-not-allowed motion-reduce:transition-none',
                tone,
                state === 'idle' && 'disabled:opacity-50',
                state === 'incorrect' &&
                    'animate-shake motion-reduce:animate-none',
                props.class,
            )
        "
        data-test="check-answer-button"
        @click="emit('click')"
    >
        <Loader2
            v-if="loading"
            class="size-5 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
        />
        <Check
            v-else-if="state === 'correct' || state === 'submitted'"
            class="size-5 stroke-[3]"
            aria-hidden="true"
        />
        <X
            v-else-if="state === 'incorrect'"
            class="size-5 stroke-[3]"
            aria-hidden="true"
        />
        {{ loading ? $t('Checking…') : text }}
    </button>
</template>
