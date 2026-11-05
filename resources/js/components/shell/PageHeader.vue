<script setup lang="ts">
// Shared page heading and brand accent used across authenticated pages.
// `#leading` (optional) puts a mark before the title, e.g. the English
// Essentials sprout; without it the approved geometry is unchanged.
import type { HTMLAttributes } from 'vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
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
        <div :class="cn('min-w-0 md:pe-44')">
            <!-- 28px Poppins Bold, -0.02em: reproduces the mockup title's
                 256px ink width; royal indigo sampled from the mockup. -->
            <div v-if="$slots.leading" class="flex min-w-0 items-center gap-3">
                <slot name="leading" />
                <h1
                    class="font-heading text-ink-royal min-w-0 text-[28px] leading-10 font-bold tracking-[-0.02em]"
                >
                    {{ title }}
                </h1>
            </div>
            <h1
                v-else
                class="font-heading text-ink-royal text-[28px] leading-10 font-bold tracking-[-0.02em]"
            >
                {{ title }}
            </h1>
            <p v-if="description" class="text-ink-slate text-base leading-6">
                {{ description }}
            </p>
        </div>

        <div class="pointer-events-none absolute end-0 top-0">
            <slot name="accent">
                <ScriptAccent />
            </slot>
        </div>
    </header>
</template>
