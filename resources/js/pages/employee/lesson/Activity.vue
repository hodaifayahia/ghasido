<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RotateCcw } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import BestResponseActivity from '@/components/learning/activities/BestResponseActivity.vue';
import GenericActivity from '@/components/learning/activities/GenericActivity.vue';
import ListenChooseActivity from '@/components/learning/activities/ListenChooseActivity.vue';
import ListenMatchActivity from '@/components/learning/activities/ListenMatchActivity.vue';
import LookListenActivity from '@/components/learning/activities/LookListenActivity.vue';
import AttemptHistoryCard from '@/components/learning/attempts/AttemptHistoryCard.vue';
import {
    formatAttemptDate,
    fromPractice,
} from '@/components/learning/attempts/attemptHistory';
import LessonStepHeader from '@/components/learning/LessonStepHeader.vue';
import StepFooterNav from '@/components/learning/StepFooterNav.vue';
import type {
    ActivityBlockRef,
    ActivityResult,
    ActivityView,
    LessonSummary,
    PracticeAttemptRow,
} from '@/types';

/*
 * One practice activity inside a step (PRAC-01..07; spec 0003 Part E,
 * H.3): the practice heading row, the runner for `activity.type`, and the
 * way back to the practice hub. `result` is present after an answer and,
 * when the learner comes back later (after a logout, on another device),
 * holds their latest saved attempt restored from the server. "Try again"
 * opens a fresh runner whose answer is saved as a NEW attempt; every earlier
 * one stays in "Your attempts" to compare (client report 2026-11-05;
 * PROG-01, TEST-06, DATA-01).
 */
type Props = {
    lesson: LessonSummary;
    block: ActivityBlockRef;
    activity: ActivityView;
    backUrl: string;
    answerUrl: string;
    result: ActivityResult | null;
    attempts?: PracticeAttemptRow[];
};

const props = withDefaults(defineProps<Props>(), { attempts: () => [] });

// "Try again" hides the saved result and remounts the runner, so it starts
// empty with a new start time and a new submission key.
const retrying = ref(false);
const round = ref(0);
const shownResult = computed(() => (retrying.value ? null : props.result));
const history = computed(() => fromPractice(props.attempts));

const canTryAgain = computed(
    () =>
        shownResult.value !== null &&
        (props.activity.attemptsLeft === null ||
            props.activity.attemptsLeft > 0),
);

watch(
    () => props.result?.attemptId,
    (id, previous) => {
        if (id !== previous) {
            retrying.value = false;
        }
    },
);

function tryAgain(): void {
    retrying.value = true;
    round.value += 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/*
 * A spoken or written answer is judged by a queued job (pronunciation
 * check or AI evaluation): poll the result until it settles, so the
 * feedback appears without a manual refresh (PERF-04).
 */
const evaluating = computed(
    () =>
        props.result !== null &&
        (props.result.aiStatus === 'pending' ||
            props.result.aiStatus === 'running'),
);

const poll = useIntervalFn(
    () => {
        if (props.result === null) {
            return;
        }

        router.reload({
            only: ['result', 'attempts'],
            data: { attempt: props.result.attemptId },
        });
    },
    3000,
    { immediate: false },
);

watch(
    evaluating,
    (busy) => {
        if (busy) {
            poll.resume();
        } else {
            poll.pause();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="`${activity.label} – ${lesson.title}`" />

    <ListenChooseActivity
        v-if="activity.type === 'listen_choose'"
        :lesson="lesson"
        :activity="activity"
        :key="round"
        :result="shownResult"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <LookListenActivity
        v-else-if="activity.type === 'look_listen'"
        :lesson="lesson"
        :activity="activity"
        :key="round"
        :result="shownResult"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <BestResponseActivity
        v-else-if="activity.type === 'best_response'"
        :lesson="lesson"
        :activity="activity"
        :key="round"
        :result="shownResult"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <ListenMatchActivity
        v-else-if="activity.type === 'listen_match'"
        :lesson="lesson"
        :activity="activity"
        :key="round"
        :result="shownResult"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <template v-else>
        <LessonStepHeader
            :number="block.activityNumber"
            :heading="activity.label"
            :subtitle="activity.description"
            :department="lesson.department.name"
            :lesson-number="lesson.positionInCourse"
            :lesson-count="lesson.courseLessonCount"
        />

        <GenericActivity
            :key="round"
            :activity="activity"
            :result="shownResult"
            :answer-url="answerUrl"
            :block-id="block.id"
        />

        <StepFooterNav
            :prev="{
                label: $t('Back to :step', { step: block.heading }),
                href: backUrl,
            }"
            :next="null"
        />
    </template>

    <div class="mt-4 flex flex-col gap-4">
        <div
            v-if="shownResult"
            class="border-line bg-surface shadow-card flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between md:p-5"
            data-test="saved-attempt-bar"
        >
            <p class="text-ink-slate text-sm leading-6">
                {{
                    shownResult.submittedAt
                        ? $t('Your saved attempt :number, from :date.', {
                              number: shownResult.attemptNo,
                              date: formatAttemptDate(shownResult.submittedAt),
                          })
                        : $t('Your saved attempt :number.', {
                              number: shownResult.attemptNo,
                          })
                }}
                <span v-if="!canTryAgain" class="block">
                    {{
                        $t('You have used all the attempts for this activity.')
                    }}
                </span>
            </p>
            <button
                v-if="canTryAgain"
                type="button"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none"
                data-test="try-again-button"
                @click="tryAgain"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                {{ $t('Try again') }}
            </button>
        </div>

        <AttemptHistoryCard
            v-if="history.length > 0"
            :rows="history"
            :current-id="shownResult?.attemptId ?? null"
        />
    </div>
</template>
