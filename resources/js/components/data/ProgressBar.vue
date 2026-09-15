<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type ProgressTone = 'brand' | 'azure' | 'success' | 'warning';

type Props = {
    /** 0–100 */
    value: number;
    tone?: ProgressTone;
    label: string;
    /** Restyles the track (height, colour…). */
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { tone: 'brand' });

const fill: Record<ProgressTone, string> = {
    brand: 'bg-brand-600',
    azure: 'bg-azure',
    success: 'bg-success',
    warning: 'bg-warning',
};

const clamped = computed(() => Math.min(100, Math.max(0, props.value)));

// Fill animates from 0 on mount and to the new value when it changes
// (desgin/10-design-system.md §10.4).
const width = ref(0);
onMounted(() => {
    requestAnimationFrame(() => {
        width.value = clamped.value;
    });
});
watch(clamped, (value) => {
    width.value = value;
});
</script>

<template>
    <div
        role="progressbar"
        :aria-label="label"
        :aria-valuenow="clamped"
        aria-valuemin="0"
        aria-valuemax="100"
        :class="
            cn(
                'rounded-pill bg-tint-track h-2 w-full overflow-hidden',
                props.class,
            )
        "
    >
        <div
            :class="
                cn(
                    'rounded-pill ease-brand h-full transition-[width] duration-700 motion-reduce:transition-none',
                    fill[tone],
                )
            "
            :style="{ width: `${width}%` }"
        />
    </div>
</template>
