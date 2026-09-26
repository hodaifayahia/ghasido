<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import LessonStepHeader from '@/components/learning/LessonStepHeader.vue';
import StepFooterNav from '@/components/learning/StepFooterNav.vue';
import CompleteStep from '@/components/learning/steps/CompleteStep.vue';
import DialogueStep from '@/components/learning/steps/DialogueStep.vue';
import ExpressionsStep from '@/components/learning/steps/ExpressionsStep.vue';
import GenericStep from '@/components/learning/steps/GenericStep.vue';
import ListenRepeatStep from '@/components/learning/steps/ListenRepeatStep.vue';
import PracticeHubStep from '@/components/learning/steps/PracticeHubStep.vue';
import SituationStep from '@/components/learning/steps/SituationStep.vue';
import VideoStep from '@/components/learning/steps/VideoStep.vue';
import VocabularyStep from '@/components/learning/steps/VocabularyStep.vue';
import { useI18n } from '@/composables/useI18n';
import { home } from '@/routes/learn';
import { complete } from '@/routes/learn/lessons/step';
import type { LessonStepNav, LessonSummary, StepBlock } from '@/types';

/*
 * One lesson step (LESSON-01..04, PROG-03; spec 0003 Part E, H.3): the
 * heading row, the renderer for `block.type`, and the Previous / Next row.
 * "Next" (or "Start Lesson" on the intro) posts the completion, which the
 * server records and answers with the next step. `steps` is read by the
 * layout's tracker.
 */
type Props = {
    lesson: LessonSummary;
    steps: LessonStepNav[];
    block: StepBlock;
    prevUrl: string | null;
    nextUrl: string | null;
    completeUrl: string;
    /** Admin preview uses read-only preview URLs, never learner endpoints. */
    preview?: boolean;
};

const props = defineProps<Props>();

const { t } = useI18n();

const isPreview = computed(() => props.preview === true);

const current = computed(() => props.steps.find((step) => step.current));

const completeForm = computed(() =>
    complete.form({ lesson: props.lesson.id, block: props.block.id }),
);

const prev = computed(() => {
    if (isPreview.value) {
        return props.prevUrl === null
            ? null
            : { label: t('Previous'), href: props.prevUrl };
    }

    if (props.block.type === 'situation' || props.prevUrl === null) {
        return { label: t('Back to Home'), href: home().url };
    }

    return { label: t('Previous'), href: props.prevUrl };
});

const next = computed(() => {
    if (isPreview.value) {
        return props.nextUrl === null
            ? null
            : {
                  label:
                      props.block.type === 'situation'
                          ? t('Start Lesson')
                          : t('Next'),
                  href: props.nextUrl,
              };
    }

    switch (props.block.type) {
        case 'situation':
            return { label: t('Start Lesson'), form: completeForm.value };
        case 'complete':
            return { label: t('Back to Home'), form: completeForm.value };
        default:
            return { label: t('Next'), form: completeForm.value };
    }
});
</script>

<template>
    <Head :title="`${lesson.title} – ${block.heading}`" />

    <LessonStepHeader
        v-if="block.type !== 'complete' && block.type !== 'practice'"
        :number="current?.number"
        :heading="block.heading"
        :department="lesson.department.name"
        :lesson-number="lesson.positionInCourse"
        :lesson-count="lesson.courseLessonCount"
    />

    <SituationStep
        v-if="block.type === 'situation'"
        :lesson="lesson"
        :block="block"
    />
    <VocabularyStep
        v-else-if="block.type === 'vocabulary'"
        :lesson="lesson"
        :block="block"
    />
    <ExpressionsStep
        v-else-if="block.type === 'expressions'"
        :lesson="lesson"
        :block="block"
    />
    <ListenRepeatStep
        v-else-if="block.type === 'listen_repeat'"
        :lesson="lesson"
        :block="block"
    />
    <DialogueStep
        v-else-if="block.type === 'dialogue'"
        :lesson="lesson"
        :block="block"
    />
    <VideoStep
        v-else-if="block.type === 'video'"
        :lesson="lesson"
        :block="block"
    />
    <PracticeHubStep
        v-else-if="block.type === 'practice'"
        :lesson="lesson"
        :block="block"
        :number="current?.number"
    />
    <CompleteStep
        v-else-if="block.type === 'complete'"
        :lesson="lesson"
        :block="block"
        :number="current?.number"
        :complete-url="completeUrl"
        :already-done="current?.done ?? false"
        :preview="isPreview"
    />
    <GenericStep v-else :lesson="lesson" :block="block" />

    <StepFooterNav v-if="block.type !== 'complete'" :prev="prev" :next="next" />
</template>
