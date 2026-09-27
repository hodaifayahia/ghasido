<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/*
 * One labelled checkout field: the label, the control (default slot), an
 * optional hint and the server's error. The control should point
 * `aria-describedby` at `${id}-hint` / `${id}-error`.
 */
type Props = {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    optional?: boolean;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <div :class="cn('grid content-start gap-2', props.class)">
        <Label
            :for="id"
            class="text-ink-indigo text-[13px] font-semibold tracking-normal"
        >
            {{ label }}
            <span v-if="optional" class="text-ink-faint font-normal">
                {{ $t('(optional)') }}
            </span>
        </Label>
        <slot />
        <p
            v-if="hint && !error"
            :id="`${id}-hint`"
            class="text-ink-muted text-[12px] leading-5"
        >
            {{ hint }}
        </p>
        <p
            v-if="error"
            :id="`${id}-error`"
            class="text-danger-text flex items-start gap-1.5 text-[13px] leading-5"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ error }}
        </p>
    </div>
</template>
