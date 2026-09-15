<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** Id for the heading, so the section is labelled by it. */
    titleId: string;
    class?: HTMLAttributes['class'];
    titleClass?: HTMLAttributes['class'];
    bodyClass?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <section
        :aria-labelledby="titleId"
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 flex-col rounded-lg border px-4 pt-3 pb-4',
                props.class,
            )
        "
    >
        <header class="flex min-h-8 items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <slot name="icon" />
                <h2
                    :id="titleId"
                    :class="
                        cn(
                            'font-heading text-brand-800 truncate text-base font-semibold',
                            titleClass,
                        )
                    "
                >
                    {{ title }}
                </h2>
            </div>
            <slot name="actions" />
        </header>

        <div :class="cn('mt-3 min-w-0 flex-1', bodyClass)">
            <slot />
        </div>
    </section>
</template>
