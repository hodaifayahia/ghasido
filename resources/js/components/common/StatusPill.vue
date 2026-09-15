<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type Status = 'inactive' | 'not_started' | 'pretest_done';

type Props = {
    status: Status;
    /** Overrides the default label for the status. */
    label?: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const labels: Record<Status, string> = {
    inactive: 'Inactive',
    not_started: 'Not started',
    pretest_done: 'Pre-test done',
};

const tones: Record<Status, string> = {
    inactive: 'bg-danger-tint text-danger-text',
    not_started: 'bg-danger-tint text-danger-text',
    pretest_done: 'bg-warning-tint text-warning-text',
};

const text = computed(() => props.label ?? labels[props.status]);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex h-5 min-w-[66px] items-center justify-center rounded-[5px] px-1.5 text-[11px] leading-none whitespace-nowrap',
                tones[status],
                props.class,
            )
        "
    >
        {{ text }}
    </span>
</template>
