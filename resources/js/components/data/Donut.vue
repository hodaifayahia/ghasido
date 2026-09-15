<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type DonutSegment = {
    label: string;
    value: number;
    /** Text-colour utility, e.g. `text-success`; the arc strokes currentColor. */
    colorClass: string;
};

type Props = {
    /** Drawn clockwise from 12 o'clock, in order. */
    segments: DonutSegment[];
    /** Accessible summary of the numbers; the drawing itself is decorative. */
    label: string;
    /** Outer diameter in px. */
    size?: number;
    /** Ring thickness in px. */
    thickness?: number;
    /** Value of a full turn. Defaults to the sum of the segments; when larger, the rest shows as track. */
    max?: number;
    /** Blank space between neighbouring segments, in px along the ring. */
    gap?: number;
    rounded?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    size: 164,
    thickness: 14,
    gap: 0,
    rounded: false,
});

const radius = computed(() => (props.size - props.thickness) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);

const sum = computed(() =>
    props.segments.reduce((total, s) => total + Math.max(0, s.value), 0),
);
const total = computed(() => Math.max(props.max ?? 0, sum.value));
const showTrack = computed(() => total.value === 0 || total.value > sum.value);

// Arcs grow from 0 on mount and glide to new values when the data changes
// (desgin/10-design-system.md §10.4: 700ms, animating from 0).
const drawn = ref(false);
onMounted(() => {
    requestAnimationFrame(() => {
        drawn.value = true;
    });
});

const arcs = computed(() => {
    const visible = props.segments.filter((s) => s.value > 0).length;
    const separated = visible > 1 || (visible === 1 && showTrack.value);
    // A round cap overhangs each end by half the thickness.
    const trim =
        (separated ? props.gap : 0) + (props.rounded ? props.thickness : 0);
    let start = 0;

    return props.segments.map((segment) => {
        const length =
            total.value === 0
                ? 0
                : (Math.max(0, segment.value) / total.value) *
                  circumference.value;
        const arcStart = start;
        start += length;
        const dash = drawn.value ? Math.max(0, length - trim) : 0;

        return {
            ...segment,
            dash,
            offset: drawn.value ? arcStart + trim / 2 : 0,
        };
    });
});
</script>

<template>
    <div
        role="img"
        :aria-label="label"
        :class="cn('relative grid shrink-0 place-items-center', props.class)"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <svg
            aria-hidden="true"
            class="absolute inset-0 size-full -rotate-90"
            :viewBox="`0 0 ${size} ${size}`"
            fill="none"
        >
            <circle
                v-if="showTrack"
                class="text-tint-donut"
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                stroke="currentColor"
                :stroke-width="thickness"
            />
            <circle
                v-for="arc in arcs"
                :key="arc.label"
                :class="
                    cn(
                        'ease-brand transition-[stroke-dasharray,stroke-dashoffset] duration-700 motion-reduce:transition-none',
                        arc.colorClass,
                    )
                "
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                stroke="currentColor"
                :stroke-width="thickness"
                :stroke-linecap="rounded ? 'round' : 'butt'"
                :stroke-opacity="rounded && arc.dash === 0 ? 0 : 1"
                :style="{
                    strokeDasharray: `${arc.dash} ${circumference}`,
                    strokeDashoffset: -arc.offset,
                }"
            />
        </svg>

        <div class="relative text-center">
            <slot />
        </div>
    </div>
</template>
