<script setup lang="ts">
// LOCKED: client-approved chrome matched to desginphotos/ (AGENTS.md §0).
// Change only when the user explicitly asks; verify against the mockup.
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <header :class="cn('relative', props.class)">
        <div :class="cn('min-w-0', $slots.accent && 'md:pe-44')">
            <!-- 28px Poppins Bold, -0.02em: reproduces the mockup title's
                 256px ink width; royal indigo sampled from the mockup. -->
            <h1
                class="font-heading text-ink-royal text-[28px] leading-10 font-bold tracking-[-0.02em]"
            >
                {{ title }}
            </h1>
            <p v-if="description" class="text-ink-slate text-base leading-6">
                {{ description }}
            </p>
        </div>

        <div
            v-if="$slots.accent"
            class="pointer-events-none absolute end-0 top-0"
        >
            <slot name="accent" />
        </div>
    </header>
</template>
