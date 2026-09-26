<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BestResponseActivity from '@/components/learning/activities/BestResponseActivity.vue';
import GenericActivity from '@/components/learning/activities/GenericActivity.vue';
import ListenChooseActivity from '@/components/learning/activities/ListenChooseActivity.vue';
import ListenMatchActivity from '@/components/learning/activities/ListenMatchActivity.vue';
import LookListenActivity from '@/components/learning/activities/LookListenActivity.vue';
import LessonStepHeader from '@/components/learning/LessonStepHeader.vue';
import StepFooterNav from '@/components/learning/StepFooterNav.vue';
import type {
    ActivityBlockRef,
    ActivityResult,
    ActivityView,
    LessonSummary,
} from '@/types';

/*
 * One practice activity inside a step (PRAC-01..07; spec 0003 Part E,
 * H.3): the practice heading row, the runner for `activity.type`, and the
 * way back to the practice hub. `result` is present after an answer.
 */
type Props = {
    lesson: LessonSummary;
    block: ActivityBlockRef;
    activity: ActivityView;
    backUrl: string;
    answerUrl: string;
    result: ActivityResult | null;
};

const props = defineProps<Props>();
</script>

<template>
    <Head :title="`${activity.label} – ${lesson.title}`" />

    <ListenChooseActivity
        v-if="activity.type === 'listen_choose'"
        :lesson="lesson"
        :activity="activity"
        :result="result"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <LookListenActivity
        v-else-if="activity.type === 'look_listen'"
        :lesson="lesson"
        :activity="activity"
        :result="result"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <BestResponseActivity
        v-else-if="activity.type === 'best_response'"
        :lesson="lesson"
        :activity="activity"
        :result="result"
        :answer-url="answerUrl"
        :back-url="backUrl"
        :number="block.activityNumber"
    />
    <ListenMatchActivity
        v-else-if="activity.type === 'listen_match'"
        :lesson="lesson"
        :activity="activity"
        :result="result"
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
            :activity="activity"
            :result="result"
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
</template>
