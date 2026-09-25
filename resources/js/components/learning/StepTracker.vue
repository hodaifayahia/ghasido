<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { LessonStepNav } from '@/types';

/*
 * The lesson's step tracker band (LESSON-03, LESSON-04; spec 0003 H.1),
 * measured on desginphotos/employ/photo_1 at 1280×853:
 *   band y 72–181 (109px) on the light-blue tint; circles 32px with their
 *   centre at y 118 (30px under the band top); labels 14px, cap top y 143;
 *   first circle centre x 82, last x 1197 (18px inside the 32px gutters);
 *   a 2px connector at the circle centres joins first to last.
 * Active = brand-600 fill, white number, brand-600 semibold label; done =
 * brand-600 ring + check; upcoming = the sampled grey fill and label.
 * Only done and current steps are links: a learner revisits, never skips.
 * Below md the band becomes a slim progress bar with a "Step n of N" chip.
 */
type Props = {
    steps: LessonStepNav[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const current = computed(
    () => props.steps.find((step) => step.current) ?? props.steps[0],
);
const total = computed(() => props.steps.length);
const percent = computed(() =>
    total.value === 0 ? 0 : ((current.value?.number ?? 0) / total.value) * 100,
);

function circleClass(step: LessonStepNav): string {
    if (step.current) {
        return 'bg-brand-600 text-white';
    }

    if (step.done) {
        return 'border-brand-600 bg-surface text-brand-600 border-2';
    }

    return 'bg-step-idle text-white';
}
</script>

<template>
    <nav
        aria-label="Lesson steps"
        :class="cn('bg-app-alt shrink-0', props.class)"
    >
        <!-- Phones: progress bar + chip (spec 0003 H.4). -->
        <div class="flex items-center gap-3 px-4 py-3 md:hidden">
            <div
                class="bg-tint-track rounded-pill h-2 flex-1 overflow-hidden"
                role="progressbar"
                :aria-valuenow="current?.number ?? 0"
                aria-valuemin="0"
                :aria-valuemax="total"
                :aria-valuetext="`Step ${current?.number ?? 0} of ${total}`"
            >
                <div
                    class="bg-brand-600 ease-brand rounded-pill h-full transition-[width] duration-700 motion-reduce:transition-none"
                    :style="{ width: `${percent}%` }"
                />
            </div>
            <span
                class="rounded-pill bg-brand-50 text-brand-700 shrink-0 px-3 py-1.5 text-xs font-semibold"
            >
                Step {{ current?.number ?? 0 }} of {{ total }}
            </span>
        </div>

        <ol
            class="relative mx-auto hidden h-[109px] w-full max-w-[1280px] list-none justify-between px-[50px] pt-[30px] md:flex"
        >
            <!-- Connector: circle centre to circle centre, under the circles. -->
            <li
                aria-hidden="true"
                class="bg-tint-connector absolute inset-x-[82px] top-[45px] h-0.5"
            />

            <li
                v-for="step in steps"
                :key="step.number"
                class="relative flex w-16 flex-col items-center"
            >
                <component
                    :is="step.done || step.current ? Link : 'span'"
                    :href="step.done || step.current ? step.url : undefined"
                    :aria-current="step.current ? 'step' : undefined"
                    :class="
                        cn(
                            'flex flex-col items-center rounded-md outline-none',
                            (step.done || step.current) &&
                                'focus-visible:ring-brand-600/40 focus-visible:ring-2',
                        )
                    "
                >
                    <span
                        :class="
                            cn(
                                'grid size-8 shrink-0 place-items-center rounded-full text-[15px] leading-none font-semibold',
                                circleClass(step),
                            )
                        "
                    >
                        <Check
                            v-if="step.done && !step.current"
                            class="size-4 stroke-[3]"
                            aria-hidden="true"
                        />
                        <template v-else>{{ step.number }}</template>
                        <span v-if="step.done && !step.current" class="sr-only">
                            {{ step.number }}, done
                        </span>
                    </span>
                    <span
                        :class="
                            cn(
                                'mt-1.5 text-sm leading-5 whitespace-nowrap',
                                step.current
                                    ? 'text-brand-600 font-semibold'
                                    : 'text-ink-steel font-medium',
                            )
                        "
                    >
                        {{ step.label }}
                    </span>
                </component>
            </li>
        </ol>
    </nav>
</template>
