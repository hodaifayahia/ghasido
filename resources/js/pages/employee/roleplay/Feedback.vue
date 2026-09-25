<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    CircleCheck,
    Lightbulb,
    RotateCcw,
    Sparkles,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';

/*
 * Role-play feedback (RP-08, AIE-01, AIE-04, PERF-04; spec 0003 Part E,
 * photo_18). The criterion scores and the encouraging feedback blocks. While
 * the evaluation is still running the page polls (PERF-04).
 */
type Criterion = { key: string; label: string; weight: number };
type Feedback = {
    summary_label?: string;
    summary_text?: string;
    did_well?: string[];
    improve?: { title: string; text: string }[];
    better_expression?: { yours: string; better: string } | null;
    key_phrase?: string | null;
    footnote?: string | null;
};

type Props = {
    attempt: {
        id: number;
        status: string;
        aiStatus: string | null;
        overallScore: number | null;
        criteriaScores: Record<string, number>;
        feedback: Feedback | null;
    };
    scenario: { title: string; icon: string; criteria: Criterion[] };
    attemptsLeft: number;
    retryUrl: string | null;
    lessonUrl: string;
    pollUrl: string;
};

const props = defineProps<Props>();

const evaluating = computed(() => props.attempt.status === 'evaluating');
const failed = computed(() => props.attempt.aiStatus === 'failed');
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => {
        if (evaluating.value && !failed.value) {
            router.reload({ only: ['attempt'] });
        }
    }, 1500);
});

onUnmounted(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head :title="`Feedback – ${scenario.title}`" />

    <section class="mx-auto grid max-w-3xl content-start gap-6 p-4 md:p-6">
        <div
            v-if="evaluating && !failed"
            class="border-line bg-surface shadow-card grid justify-items-center gap-3 rounded-lg border p-10 text-center"
        >
            <span
                class="border-brand-200 border-t-brand-600 size-10 animate-spin rounded-full border-4"
            />
            <p class="text-ink-royal font-heading text-[18px] font-semibold">
                Evaluating your conversation…
            </p>
            <p class="text-ink-slate text-[13.5px]">
                This takes a moment. Your transcript is saved.
            </p>
        </div>

        <div
            v-else-if="failed"
            class="border-line bg-surface shadow-card grid justify-items-center gap-3 rounded-lg border p-10 text-center"
        >
            <p class="text-danger-text font-heading text-[18px] font-semibold">
                The evaluation could not finish.
            </p>
            <p class="text-ink-slate text-[13.5px]">
                Your conversation is saved. Please try again in a moment.
            </p>
            <Link
                :href="lessonUrl"
                class="text-brand-700 text-[14px] font-semibold"
                >Back to the lesson</Link
            >
        </div>

        <template v-else>
            <div
                class="border-line bg-surface shadow-card grid justify-items-center gap-3 rounded-lg border p-8 text-center"
            >
                <span
                    class="bg-success-tint text-success grid size-16 place-items-center rounded-xl"
                >
                    <CircleCheck class="size-8" aria-hidden="true" />
                </span>
                <h1
                    class="font-heading text-ink-royal text-[26px] font-bold tracking-[-0.02em]"
                >
                    {{ attempt.feedback?.summary_label ?? 'Well done!' }}
                </h1>
                <p
                    v-if="attempt.feedback?.summary_text"
                    class="text-ink-slate max-w-lg text-[14px]"
                >
                    {{ attempt.feedback.summary_text }}
                </p>
                <span
                    class="font-heading text-brand-700 mt-1 text-[40px] leading-none font-bold"
                >
                    {{ attempt.overallScore ?? 0
                    }}<span class="text-ink-faint text-[20px]">/100</span>
                </span>
            </div>

            <div
                class="border-line bg-surface shadow-card grid gap-3 rounded-lg border p-5"
            >
                <h2
                    class="font-heading text-ink-royal text-[15px] font-semibold"
                >
                    How you did
                </h2>
                <div
                    v-for="criterion in scenario.criteria"
                    :key="criterion.key"
                    class="grid gap-1"
                >
                    <div class="flex items-center justify-between text-[13px]">
                        <span class="text-ink font-medium">{{
                            criterion.label
                        }}</span>
                        <span class="text-ink-slate"
                            >{{
                                attempt.criteriaScores[criterion.key] ?? 0
                            }}%</span
                        >
                    </div>
                    <div
                        class="bg-tint-track rounded-pill h-1.5 overflow-hidden"
                    >
                        <div
                            class="bg-brand-500 rounded-pill h-full"
                            :style="{
                                width: `${attempt.criteriaScores[criterion.key] ?? 0}%`,
                            }"
                        />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-if="attempt.feedback?.did_well?.length"
                    class="border-line bg-surface shadow-card grid content-start gap-2 rounded-lg border p-5"
                >
                    <h2
                        class="text-success-text flex items-center gap-2 text-[14px] font-semibold"
                    >
                        <CircleCheck class="size-4" aria-hidden="true" />
                        What you did well
                    </h2>
                    <ul class="grid gap-1.5">
                        <li
                            v-for="item in attempt.feedback.did_well"
                            :key="item"
                            class="text-ink-slate flex items-start gap-2 text-[13px]"
                        >
                            <span
                                class="bg-success mt-1.5 size-1.5 shrink-0 rounded-full"
                            />
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <div
                    v-if="attempt.feedback?.improve?.length"
                    class="border-line bg-surface shadow-card grid content-start gap-2 rounded-lg border p-5"
                >
                    <h2
                        class="text-warning-text flex items-center gap-2 text-[14px] font-semibold"
                    >
                        <Lightbulb class="size-4" aria-hidden="true" />
                        To improve
                    </h2>
                    <ul class="grid gap-2">
                        <li
                            v-for="item in attempt.feedback.improve"
                            :key="item.title"
                            class="grid gap-0.5"
                        >
                            <span class="text-ink text-[13px] font-semibold">{{
                                item.title
                            }}</span>
                            <span class="text-ink-slate text-[12.5px]">{{
                                item.text
                            }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-if="attempt.feedback?.better_expression"
                class="border-ai/30 bg-ai-tint/50 grid gap-2 rounded-lg border p-5"
            >
                <h2
                    class="text-ai flex items-center gap-2 text-[14px] font-semibold"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    A better way to say it
                </h2>
                <p class="text-ink-slate text-[13px]">
                    You said:
                    <span class="italic"
                        >“{{ attempt.feedback.better_expression.yours }}”</span
                    >
                </p>
                <p class="text-ink text-[13.5px] font-medium">
                    Try:
                    <span class="text-ai"
                        >“{{ attempt.feedback.better_expression.better }}”</span
                    >
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                <Link
                    :href="lessonUrl"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-[14px] font-semibold text-white active:scale-[.97]"
                >
                    <BookOpen class="size-4" aria-hidden="true" />
                    Back to the lesson
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
                <Link
                    v-if="retryUrl && attemptsLeft > 0"
                    :href="retryUrl"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-11 items-center justify-center gap-2 rounded-md border px-5 text-[14px] font-semibold shadow-none"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                    Try again ({{ attemptsLeft }} left)
                </Link>
            </div>
        </template>
    </section>
</template>
