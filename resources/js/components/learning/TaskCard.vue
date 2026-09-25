<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { CourseTone } from '@/types';

/*
 * A white card with a tinted icon chip and a title: the instruction /
 * task frame the practice screens and generic steps share (spec 0003 H.2).
 * Card recipe from AGENTS.md §3: 1px line border AND the soft blue shadow.
 */
type Props = {
    title: string;
    text?: string | null;
    icon?: Component;
    tone?: CourseTone;
    class?: HTMLAttributes['class'];
    bodyClass?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    text: null,
    icon: undefined,
    tone: 'brand',
});

const chip: Record<CourseTone, string> = {
    brand: 'bg-brand-50 text-brand-600',
    aqua: 'bg-aqua-tint text-aqua',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    gold: 'bg-gold-tint text-gold',
    danger: 'bg-danger-tint text-danger',
    ai: 'bg-ai-tint text-ai',
    azure: 'bg-azure-tint text-azure',
    sunset: 'bg-warning-tint text-sunset',
    blossom: 'bg-blossom-tint text-blossom',
};
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col rounded-lg border p-5',
                props.class,
            )
        "
    >
        <header class="flex items-center gap-3">
            <span
                v-if="icon"
                :class="
                    cn(
                        'grid size-11 shrink-0 place-items-center rounded-xl',
                        chip[tone],
                    )
                "
            >
                <component :is="icon" class="size-[22px]" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h2
                    class="font-heading text-ink-night text-xl leading-7 font-semibold"
                >
                    {{ title }}
                </h2>
                <p v-if="text" class="text-ink-slate text-sm leading-5">
                    {{ text }}
                </p>
            </div>
        </header>
        <div v-if="$slots.default" :class="cn('mt-4 min-w-0', bodyClass)">
            <slot />
        </div>
    </section>
</template>
