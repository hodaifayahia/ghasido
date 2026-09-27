<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

/*
 * The checkout's progress at a glance: each step links to its section and
 * shows a check once it is done. The first step not done yet is current.
 */
export type CheckoutStep = {
    id: string;
    label: string;
    complete: boolean;
};

type Props = {
    steps: CheckoutStep[];
};

const props = defineProps<Props>();

const current = computed(() => {
    const index = props.steps.findIndex((step) => !step.complete);

    return index === -1 ? props.steps.length - 1 : index;
});
</script>

<template>
    <nav
        :aria-label="$t('Checkout steps')"
        class="border-line bg-surface shadow-card rounded-xl border px-2 py-4 sm:px-5"
    >
        <ol
            class="grid"
            :style="{
                gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))`,
            }"
        >
            <li
                v-for="(step, index) in steps"
                :key="step.id"
                class="relative flex justify-center"
            >
                <span
                    v-if="index < steps.length - 1"
                    :class="step.complete ? 'bg-success' : 'bg-tint-track'"
                    class="absolute start-[calc(50%+24px)] top-[17px] h-0.5 w-[calc(100%-48px)] rounded-full transition-colors duration-300 motion-reduce:transition-none"
                    aria-hidden="true"
                />
                <a
                    :href="`#${step.id}`"
                    :aria-current="index === current ? 'step' : undefined"
                    class="group focus-visible:ring-brand-600/30 flex min-h-11 flex-col items-center gap-2 rounded-md px-1 text-center focus-visible:ring-3 focus-visible:outline-none"
                >
                    <span
                        :class="
                            step.complete
                                ? 'bg-success text-surface'
                                : index === current
                                  ? 'bg-brand-600 text-surface ring-brand-100 ring-4'
                                  : 'bg-tint-step text-ink-muted'
                        "
                        class="font-heading grid size-9 place-items-center rounded-full text-[14px] font-bold transition-colors duration-200 motion-reduce:transition-none"
                        aria-hidden="true"
                    >
                        <Check v-if="step.complete" class="size-4.5" />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span
                        :class="
                            index === current
                                ? 'text-brand-700 font-semibold'
                                : 'text-ink-slate group-hover:text-brand-600'
                        "
                        class="max-w-28 text-[11px] leading-tight sm:text-[12.5px]"
                    >
                        {{ step.label }}
                        <span v-if="step.complete" class="sr-only">
                            ({{ $t('done') }})
                        </span>
                    </span>
                </a>
            </li>
        </ol>
    </nav>
</template>
