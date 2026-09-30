<script setup lang="ts">
import { Check } from '@lucide/vue';

/*
 * One numbered step of the checkout (client payment-flow reference,
 * 2026-09-27): its number turns into a green check once the step is done.
 */
type Props = {
    id: string;
    step: number;
    title: string;
    description?: string;
    complete?: boolean;
};

defineProps<Props>();
</script>

<template>
    <section
        :id="id"
        :aria-labelledby="`${id}-title`"
        class="border-line bg-surface shadow-card scroll-mt-6 rounded-xl border p-5 sm:p-7"
    >
        <header class="flex items-start gap-3.5">
            <span
                :class="
                    complete
                        ? 'bg-success text-surface'
                        : 'bg-brand-600 text-surface shadow-btn'
                "
                class="font-heading grid size-9 shrink-0 place-items-center rounded-full text-[15px] font-bold transition-colors duration-200 motion-reduce:transition-none"
                aria-hidden="true"
            >
                <Check v-if="complete" class="size-5" />
                <template v-else>{{ step }}</template>
            </span>
            <div class="min-w-0 pt-0.5">
                <h2
                    :id="`${id}-title`"
                    class="font-heading text-brand-900 text-[18px] leading-7 font-semibold tracking-[-0.01em] sm:text-[20px]"
                >
                    <span class="sr-only">
                        {{ $t('Step :number:', { number: step }) }}
                    </span>
                    {{ title }}
                    <span v-if="complete" class="sr-only">
                        ({{ $t('done') }})
                    </span>
                </h2>
                <p
                    v-if="description"
                    class="text-ink-slate mt-1 text-[13px] leading-5"
                >
                    {{ description }}
                </p>
            </div>
        </header>
        <div class="mt-6">
            <slot />
        </div>
    </section>
</template>
