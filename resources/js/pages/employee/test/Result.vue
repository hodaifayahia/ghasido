<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Award, BookOpen, CircleCheck } from '@lucide/vue';
import { computed } from 'vue';

/*
 * The test result (TEST-04, JOURNEY-01/05; spec 0003 Part E). What the
 * learner sees is the admin's `results_visibility`: nothing, the score, or
 * the score with a per-skill breakdown. Whatever the setting, the answers
 * are already saved.
 */
type Band = { skill: string; score: number; max: number; percent: number };
type Result = {
    score: number;
    maxScore: number;
    percent: number;
    breakdown?: Band[];
};

type Props = {
    test: { id: number; type: string; title: string; label: string };
    visibility: string;
    result: Result | null;
    isPost: boolean;
    continueUrl: string;
    certificateUrl: string;
    certificateAvailable: boolean;
};

const props = defineProps<Props>();

const showScore = computed(() => props.result !== null);
</script>

<template>
    <Head :title="`${test.label} complete`" />

    <section class="mx-auto grid max-w-2xl content-start gap-6 p-4 md:p-6">
        <div
            class="border-line bg-surface shadow-card grid justify-items-center gap-4 rounded-lg border p-8 text-center"
        >
            <span
                class="bg-success-tint text-success grid size-16 place-items-center rounded-xl"
            >
                <CircleCheck class="size-8" aria-hidden="true" />
            </span>
            <div class="grid gap-1">
                <h1
                    class="font-heading text-ink-royal text-[26px] font-bold tracking-[-0.02em]"
                >
                    {{ test.label }} complete
                </h1>
                <p class="text-ink-slate text-[15px]">
                    Thank you — your answers have been saved.
                </p>
            </div>

            <div
                v-if="showScore && result"
                class="border-line bg-app grid w-full gap-4 rounded-lg border p-6"
            >
                <div class="grid gap-1">
                    <span
                        class="font-heading text-brand-700 text-[44px] leading-none font-bold"
                    >
                        {{ result.percent }}%
                    </span>
                    <span class="text-ink-slate text-[13.5px]">
                        {{ result.score }} / {{ result.maxScore }} points
                    </span>
                </div>

                <div
                    v-if="result.breakdown && result.breakdown.length > 0"
                    class="grid gap-2.5"
                >
                    <div
                        v-for="band in result.breakdown"
                        :key="band.skill"
                        class="grid gap-1"
                    >
                        <div
                            class="flex items-center justify-between text-[13px]"
                        >
                            <span class="text-ink font-medium">{{
                                band.skill
                            }}</span>
                            <span class="text-ink-slate"
                                >{{ band.percent }}%</span
                            >
                        </div>
                        <div
                            class="bg-tint-track rounded-pill h-1.5 overflow-hidden"
                        >
                            <div
                                class="bg-brand-500 rounded-pill h-full"
                                :style="{ width: `${band.percent}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="mt-2 flex w-full flex-col gap-3 sm:flex-row sm:justify-center"
            >
                <Link
                    :href="continueUrl"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-[14px] font-semibold text-white active:scale-[.97]"
                >
                    <BookOpen class="size-4" aria-hidden="true" />
                    {{ isPost ? 'Back to My Lessons' : 'Start My Lessons' }}
                </Link>
                <Link
                    v-if="isPost && certificateAvailable"
                    :href="certificateUrl"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-11 items-center justify-center gap-2 rounded-md border px-5 text-[14px] font-semibold shadow-none"
                >
                    <Award class="size-4" aria-hidden="true" />
                    View Certificate
                </Link>
            </div>
        </div>
    </section>
</template>
