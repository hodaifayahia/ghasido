<script setup lang="ts">
import { TransitionPresets, useTransition } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type StatTone = 'brand' | 'azure' | 'ai' | 'success' | 'warning';

type Props = {
    value: number;
    /** Rendered straight after the number, e.g. `%`. */
    unit?: string;
    label: string;
    /** Muted line under the label. The default slot replaces it. */
    detail?: string;
    tone?: StatTone;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { tone: 'brand' });

// Chip tints sampled from the Admin Dashboard mockup.
const chip: Record<StatTone, string> = {
    brand: 'bg-azure/20 text-brand-600',
    azure: 'bg-azure/35 text-brand-600',
    ai: 'bg-ai/20 text-ai',
    success: 'bg-success/25 text-success',
    warning: 'bg-gold/35 text-sunset',
};

const figure: Record<StatTone, string> = {
    brand: 'text-brand-800',
    azure: 'text-brand-800',
    ai: 'text-brand-800',
    success: 'text-brand-800',
    warning: 'text-sunset',
};

// Count up from 0 over 800ms on first paint (desgin/10-design-system.md
// §10.4). Server render and reduced motion show the final figure straight away.
const target = ref(0);
const counted = useTransition(target, {
    duration: 800,
    transition: TransitionPresets.easeOutCubic,
});
const counting = ref(false);

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    counting.value = true;
    target.value = props.value;
});

watch(
    () => props.value,
    (value) => {
        target.value = value;
    },
);

const shown = computed(() =>
    counting.value ? Math.round(counted.value) : props.value,
);
</script>

<template>
    <div
        :class="
            cn(
                'border-line bg-surface shadow-card flex h-full items-start gap-2.5 rounded-lg border ps-[11px] pe-1 pt-3.5 pb-[13px]',
                'ease-brand hover:shadow-hover transition duration-150 hover:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0',
                props.class,
            )
        "
    >
        <div
            :class="
                cn(
                    'mt-px grid size-12 shrink-0 place-items-center rounded-full',
                    chip[tone],
                )
            "
        >
            <slot name="icon" />
        </div>

        <div class="flex min-w-0 flex-col 2xl:whitespace-nowrap">
            <p
                :class="
                    cn(
                        'font-heading text-[26px] leading-7 font-bold',
                        figure[tone],
                    )
                "
            >
                <span aria-hidden="true">{{ shown }}{{ unit }}</span>
                <span class="sr-only">{{ value }}{{ unit }}</span>
            </p>
            <p
                class="text-brand-900 mt-0.5 text-xs leading-4 font-medium tracking-tight"
            >
                {{ label }}
            </p>
            <slot>
                <p
                    v-if="detail"
                    class="text-ink-muted mt-[3px] text-xs leading-4"
                >
                    {{ detail }}
                </p>
            </slot>
        </div>
    </div>
</template>
