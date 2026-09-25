<script setup lang="ts">
import { Link, usePoll } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck, Sparkles, Target } from '@lucide/vue';
import { computed, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import TipCard from '@/components/learning/TipCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { CoachSummary } from '@/types';

/*
 * "Your coach" (spec 0005 §3.5): a short, AI-written summary of the
 * learner's own progress, with one next step the server chose. Built on the
 * Home side-card recipe; the AI accent (ai tint) marks it as AI-written. The
 * summary is written by a queued job, so while it is on its way the card
 * says so and polls (PERF-04); it is never a blank card.
 */
type Props = {
    coach: CoachSummary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const writing = computed(
    () =>
        props.coach.status === 'pending' || props.coach.status === 'refreshing',
);

const { start, stop } = usePoll(
    4000,
    { only: ['coach'] },
    { autoStart: false },
);

watch(
    writing,
    (busy) => {
        if (busy) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card overflow-hidden rounded-lg border',
                props.class,
            )
        "
        aria-label="Your coach"
        aria-live="polite"
        data-test="coach-card"
    >
        <header class="bg-brand-50 flex h-[38px] items-center gap-3 ps-5 pe-4">
            <Sparkles
                class="text-ai size-[22px] shrink-0 stroke-[1.75]"
                aria-hidden="true"
            />
            <h2
                class="font-heading text-ink-cobalt text-[17px] leading-6 font-semibold"
            >
                Your coach
            </h2>
            <span
                class="bg-ai-tint text-ai rounded-pill ms-auto px-2 py-0.5 text-[11px] font-semibold"
                title="Written by AI from your own results"
            >
                AI
            </span>
        </header>

        <div class="flex flex-col gap-3 px-5 pt-3 pb-4">
            <template v-if="coach.status === 'pending'">
                <p class="text-ink-slate text-[13px]">
                    Your coach is looking at your progress…
                </p>
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-3 w-full" />
                <Skeleton class="h-3 w-5/6" />
            </template>

            <p
                v-else-if="coach.status === 'empty'"
                class="text-ink-slate text-[13px] leading-5"
            >
                Finish your first lesson step or the Pre-test, and your coach
                will share tips made for you here.
            </p>

            <p
                v-else-if="coach.status === 'failed'"
                class="text-ink-slate text-[13px] leading-5"
            >
                Your coach could not write tips just now and will try again
                later. Your progress is saved.
            </p>

            <template v-else>
                <p
                    class="font-heading text-ink text-[16px] leading-6 font-semibold"
                >
                    {{ coach.headline }}
                </p>

                <ul v-if="coach.strengths.length > 0" class="grid gap-1.5">
                    <li
                        v-for="item in coach.strengths"
                        :key="item"
                        class="flex items-start gap-2 text-[13px] leading-5"
                    >
                        <CircleCheck
                            class="text-success-text mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="text-ink-slate">
                            <span class="sr-only">Going well: </span>{{ item }}
                        </span>
                    </li>
                </ul>

                <ul v-if="coach.focus.length > 0" class="grid gap-1.5">
                    <li
                        v-for="item in coach.focus"
                        :key="item"
                        class="flex items-start gap-2 text-[13px] leading-5"
                    >
                        <Target
                            class="text-brand-600 mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="text-ink-slate">
                            <span class="sr-only">Work on: </span>{{ item }}
                        </span>
                    </li>
                </ul>

                <TipCard v-if="coach.tip" :text="coach.tip" class="p-3" />

                <p
                    v-if="coach.status === 'refreshing'"
                    class="text-ink-faint text-[11.5px]"
                >
                    Updating with your latest results…
                </p>
            </template>

            <div
                class="border-line mt-1 flex flex-col gap-2 border-t pt-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-ink-slate text-[12.5px] leading-5">
                    {{ coach.nextStep.description }}
                </p>
                <Link
                    :href="coach.nextStep.url"
                    class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
                    data-test="coach-next-step-link"
                >
                    {{ coach.nextStep.label }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </section>
</template>
