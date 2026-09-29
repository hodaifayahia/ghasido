<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { ref, watch } from 'vue';
import ActivityEditorDialog from '@/components/activities/ActivityEditorDialog.vue';
import type { ActivitySaveData } from '@/components/activities/ActivityEditorDialog.vue';
import type {
    ActivityTypeKey,
    LessonsImageLibrary,
    TestEditorQuestion,
} from '@/types';

/**
 * Add or edit one Pre/Post-test question in the shared activity editor
 * (TEST-05, TEST-06; client report 2026-09-29): the same ten types and
 * fields as a lesson activity, one item per question. It posts to
 * tests.questions.store / tests.questions.update, which validate the item
 * for its type and write a new version on every change (DATA-11). A test
 * question is never shown with Show Meaning or answers to the learner
 * (CTRL-04, TEST-03): the runner strips them server-side.
 */
type Props = {
    /** Null to add a new question. */
    question: TestEditorQuestion | null;
    initialType: ActivityTypeKey | null;
    storeUrl: string | null;
    number: number;
    library: LessonsImageLibrary;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const saving = ref(false);
const errors = ref<Record<string, string>>({});

watch(open, (isOpen) => {
    if (isOpen) {
        errors.value = {};
    }
});

function save(data: ActivitySaveData): void {
    const body: Record<string, FormDataConvertible> = {
        title: data.title,
        prompt: data.prompt,
        payload: data.payload as unknown as FormDataConvertible,
    };
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
        },
        onError: (bag: Record<string, string>) => {
            errors.value = bag;
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    saving.value = true;
    errors.value = {};

    if (props.question !== null) {
        router.patch(props.question.updateUrl, body, options);

        return;
    }

    if (props.storeUrl !== null) {
        router.post(props.storeUrl, { ...body, type: data.type }, options);
    }
}
</script>

<template>
    <ActivityEditorDialog
        v-model:open="open"
        mode="test"
        :activity="question?.activity ?? null"
        :initial-type="initialType"
        :number="number"
        :library="library"
        :media="question?.mediaMap ?? {}"
        :audio="question?.audioMap ?? {}"
        :chips="{ lessonId: null, reloadOnly: ['editor'] }"
        :read-only="false"
        :saving="saving"
        :errors="errors"
        @save="save"
    />
</template>
