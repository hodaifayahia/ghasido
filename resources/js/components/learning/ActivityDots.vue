<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * The item pager dots under a multi-item practice activity (spec 0003 B.9,
 * H.2): the current item is a 20px brand pill, answered items are success,
 * the rest the step tint. Position is also given as text for AT.
 */
type Props = {
    count: number;
    /** 0-based index of the item on screen. */
    current: number;
    answered?: number[];
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { answered: () => [] });
</script>

<template>
    <div
        role="status"
        :class="cn('flex items-center justify-center gap-2', props.class)"
    >
        <span class="sr-only">Item {{ current + 1 }} of {{ count }}</span>
        <span
            v-for="index in count"
            :key="index"
            aria-hidden="true"
            :class="
                cn(
                    'ease-brand rounded-pill h-2 transition-[width,background-color] duration-150 motion-reduce:transition-none',
                    index - 1 === current
                        ? 'bg-brand-600 w-5'
                        : props.answered.includes(index - 1)
                          ? 'bg-success w-2'
                          : 'bg-tint-step w-2',
                )
            "
        />
    </div>
</template>
