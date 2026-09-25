<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * A small progress ring (spec 0003 H.2; photo_7 "0 / 6 activities
 * completed"): a donut-tint track and a brand-600 arc that fills from zero
 * over 700ms on mount (desgin/10-design-system.md §10.4), skipped under
 * reduced motion. The value is announced as text by the parent.
 */
type Props = {
    /** 0–100. */
    value: number;
    size?: number;
    stroke?: number;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { size: 48, stroke: 6 });

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const shown = ref(0);

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        shown.value = props.value;

        return;
    }

    requestAnimationFrame(() => {
        shown.value = props.value;
    });
});

const offset = computed(
    () =>
        circumference.value *
        (1 - Math.min(100, Math.max(0, shown.value)) / 100),
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 ${size} ${size}`"
        aria-hidden="true"
        :class="cn('shrink-0 -rotate-90', props.class)"
    >
        <circle
            :cx="size / 2"
            :cy="size / 2"
            :r="radius"
            fill="none"
            class="stroke-tint-donut"
            :stroke-width="stroke"
        />
        <circle
            :cx="size / 2"
            :cy="size / 2"
            :r="radius"
            fill="none"
            class="stroke-brand-600 ease-brand transition-[stroke-dashoffset] duration-700 motion-reduce:transition-none"
            :stroke-width="stroke"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="offset"
        />
    </svg>
</template>
