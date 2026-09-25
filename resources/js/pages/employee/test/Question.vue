<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Clock, Flag } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import GenericActivity from '@/components/learning/activities/GenericActivity.vue';
import { Button } from '@/components/ui/button';
import { useTimer } from '@/composables/useTimer';
import { cn } from '@/lib/utils';
import type { ActivityView, AnswerMap } from '@/types';

/*
 * The Pre/Post-test runner (TEST-01..06, TIME-05, CTRL-04; spec 0003 Part E,
 * photos 21–28). One question per page, the sitting timed from the server's
 * deadline, answers saved on every move. No Show Meaning, no correctness and
 * no Arabic — the payload the server sent already has none (TEST-03).
 */
type Props = {
    test: { id: number; type: string; title: string; label: string };
    attempt: {
        id: number;
        deadlineAt: string | null;
        remainingSeconds: number | null;
        onTimeout: string;
    };
    question: { number: number; total: number };
    activity: ActivityView;
    savedAnswer: AnswerMap | null;
    questions: { number: number; answered: boolean; url: string }[];
    answerUrl: string;
    finishUrl: string;
};

const props = defineProps<Props>();

const activityRef = ref<InstanceType<typeof GenericActivity> | null>(null);
const mountedAt = Date.now();
const busy = ref(false);

const { label: timeLabel, expired } = useTimer(() => props.attempt.deadlineAt);

const percent = computed(() =>
    props.question.total > 0
        ? Math.round((props.question.number / props.question.total) * 100)
        : 0,
);
const isLast = computed(() => props.question.number >= props.question.total);
const isFirst = computed(() => props.question.number <= 1);

function collect(): AnswerMap {
    return activityRef.value?.collect() ?? {};
}

function elapsed(): number {
    return Date.now() - mountedAt;
}

function go(to: number): void {
    if (busy.value) {
        return;
    }

    busy.value = true;
    router.put(
        props.answerUrl,
        { answer: collect(), to, time_taken_ms: elapsed() },
        { onFinish: () => (busy.value = false) },
    );
}

function finish(): void {
    if (busy.value) {
        return;
    }

    busy.value = true;
    router.post(
        props.finishUrl,
        {
            answer: collect(),
            number: props.question.number,
            time_taken_ms: elapsed(),
        },
        { onFinish: () => (busy.value = false) },
    );
}

watch(expired, (isExpired) => {
    if (isExpired) {
        finish();
    }
});
</script>

<template>
    <Head :title="`${test.label} – ${test.title}`" />
    <h1 class="sr-only">{{ test.label }}: {{ test.title }}</h1>

    <section
        class="max-w-content mx-auto grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_300px]"
    >
        <div class="grid content-start gap-5">
            <header class="grid gap-2">
                <p
                    class="font-heading text-ink-royal text-[28px] leading-9 font-bold tracking-[-0.02em]"
                >
                    {{ test.label }}
                </p>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-ink-slate text-[14px]">
                        Question {{ question.number }} of {{ question.total }}
                    </span>
                    <span class="text-brand-700 text-[13px] font-semibold"
                        >{{ percent }}%</span
                    >
                </div>
                <div class="bg-tint-track rounded-pill h-2 overflow-hidden">
                    <div
                        class="bg-brand-600 rounded-pill h-full transition-[width] duration-500 ease-out"
                        :style="{ width: `${percent}%` }"
                    />
                </div>
            </header>

            <GenericActivity
                ref="activityRef"
                :activity="activity"
                :result="null"
                :answer-url="answerUrl"
                :block-id="0"
                :initial-answers="savedAnswer ?? undefined"
            />

            <div
                class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="isFirst || busy"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-11 gap-2 rounded-md px-5 text-[14px] font-semibold shadow-none"
                    @click="go(question.number - 1)"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Previous Question
                </Button>
                <Button
                    v-if="!isLast"
                    type="button"
                    :disabled="busy"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-2 rounded-md px-5 text-[14px] font-semibold text-white active:scale-[.97]"
                    @click="go(question.number + 1)"
                >
                    Next Question
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    v-else
                    type="button"
                    :disabled="busy"
                    class="bg-success shadow-btn h-11 gap-2 rounded-md px-5 text-[14px] font-semibold text-white hover:opacity-90 active:scale-[.97]"
                    @click="finish"
                >
                    <Flag class="size-4" aria-hidden="true" />
                    Finish Test
                </Button>
            </div>
        </div>

        <aside class="grid content-start gap-4">
            <div
                class="border-line bg-surface shadow-card grid gap-2 rounded-lg border p-5"
            >
                <span
                    class="text-ink-slate flex items-center gap-2 text-[12.5px] font-semibold tracking-[0.02em] uppercase"
                >
                    <Clock class="text-brand-700 size-4" aria-hidden="true" />
                    Time Remaining
                </span>
                <span
                    :class="
                        cn(
                            'font-heading text-[34px] leading-none font-bold tabular-nums',
                            expired ? 'text-danger' : 'text-ink-royal',
                        )
                    "
                >
                    {{ timeLabel }}
                </span>
            </div>

            <div
                class="border-line bg-surface shadow-card grid gap-3 rounded-lg border p-5"
            >
                <span
                    class="text-ink-slate text-[12.5px] font-semibold tracking-[0.02em] uppercase"
                >
                    Questions
                </span>
                <div class="grid grid-cols-5 gap-2">
                    <button
                        v-for="cell in questions"
                        :key="cell.number"
                        type="button"
                        :disabled="busy"
                        :aria-current="
                            cell.number === question.number ? 'true' : undefined
                        "
                        :class="
                            cn(
                                'grid h-9 place-items-center rounded-md text-[12.5px] font-semibold transition',
                                cell.number === question.number
                                    ? 'bg-brand-600 text-white'
                                    : cell.answered
                                      ? 'bg-brand-100 text-brand-700'
                                      : 'bg-tint-grid text-ink-slate hover:bg-brand-50',
                            )
                        "
                        @click="go(cell.number)"
                    >
                        {{ cell.number }}
                    </button>
                </div>
            </div>
        </aside>
    </section>
</template>
