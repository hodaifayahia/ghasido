<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import { computed, useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * One answer option (PRAC-01, TEST-03, ACC-02, ACC-03; spec 0003 H.2): a
 * native radio behind a 56px row with a letter badge and the option's
 * content in the slot. Selected = brand border + tint. After a practice
 * check the row can show correct / incorrect with an icon and a word;
 * a test never passes a `state` (no correctness colours, TEST-03).
 */
type Props = {
    id: string;
    name: string;
    selected: boolean;
    letter?: string | null;
    disabled?: boolean;
    state?: 'idle' | 'correct' | 'incorrect';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    letter: null,
    disabled: false,
    state: 'idle',
});

const emit = defineEmits<{ select: [id: string] }>();

const inputId = `option-${useId()}`;

const frame = computed(() => {
    if (props.state === 'correct') {
        return 'border-success bg-success-tint';
    }

    if (props.state === 'incorrect') {
        return 'border-danger bg-danger-tint animate-shake motion-reduce:animate-none';
    }

    return props.selected
        ? 'border-brand-600 bg-brand-50'
        : 'border-line bg-surface hover:border-brand-300';
});
</script>

<template>
    <label
        :for="inputId"
        :class="
            cn(
                'shadow-card ease-brand has-focus-visible:ring-brand-600/40 flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border px-4 py-2 transition-colors duration-150 has-focus-visible:ring-3 motion-reduce:transition-none',
                frame,
                disabled && 'cursor-default',
                props.class,
            )
        "
    >
        <input
            :id="inputId"
            type="radio"
            :name="name"
            :value="id"
            :checked="selected"
            :disabled="disabled"
            class="sr-only"
            @change="emit('select', id)"
        />
        <span
            aria-hidden="true"
            :class="
                cn(
                    'grid size-5 shrink-0 place-items-center rounded-full border-2',
                    selected ? 'border-brand-600' : 'border-line-strong',
                )
            "
        >
            <span
                v-if="selected"
                class="bg-brand-600 block size-2.5 rounded-full"
            />
        </span>
        <span
            v-if="letter"
            class="bg-tint-grid text-ink grid size-7 shrink-0 place-items-center rounded-full text-sm font-semibold"
        >
            {{ letter }}
        </span>
        <span class="text-ink min-w-0 flex-1 text-base leading-6">
            <slot />
        </span>
        <span
            v-if="state === 'correct'"
            class="text-success-text flex shrink-0 items-center gap-1 text-sm font-semibold"
        >
            <Check class="size-5 stroke-[3]" aria-hidden="true" />
            Correct
        </span>
        <span
            v-else-if="state === 'incorrect'"
            class="text-danger-text flex shrink-0 items-center gap-1 text-sm font-semibold"
        >
            <X class="size-5 stroke-[3]" aria-hidden="true" />
            Not quite
        </span>
    </label>
</template>
