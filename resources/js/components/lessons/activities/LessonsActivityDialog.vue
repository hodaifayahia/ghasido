<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { ref, watch } from 'vue';
import type { BlockActivityMode } from '@/components/activities/activityCatalog';
import ActivityEditorDialog from '@/components/activities/ActivityEditorDialog.vue';
import type { ActivitySaveData } from '@/components/activities/ActivityEditorDialog.vue';
import { store, update } from '@/routes/activities';
import type {
    LessonActivityRow,
    LessonBlockRow,
    LessonsImageLibrary,
} from '@/types';

/**
 * Add or edit one activity of a Practice block, or one question of a Quiz
 * block (PRAC-01..07, TEST-05), in the shared activity editor — the same
 * ten types the Pre/Post-test builder offers (client report 2026-09-29).
 * It saves through activities.store (placed in the block in the same
 * request) or activities.update, which writes a new version when the
 * content changes (DATA-11).
 */
type Props = {
    block: LessonBlockRow;
    /** Null to add a new one. */
    activity: LessonActivityRow | null;
    mode: Exclude<BlockActivityMode, 'test'>;
    /** The position a new one takes (for its default title). */
    number: number;
    library: LessonsImageLibrary;
    readOnly: boolean;
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
    saving.value = true;
    errors.value = {};

    const body = {
        title: data.title,
        prompt: data.prompt,
        attempts_allowed: data.attempts_allowed,
        payload: data.payload as unknown as FormDataConvertible,
    };
    const options = {
        preserveState: true,
        preserveScroll: true,
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

    if (props.activity === null) {
        router.post(
            store.url(),
            { ...body, type: data.type, block_id: props.block.id },
            options,
        );

        return;
    }

    router.patch(update.url(props.activity.id), body, options);
}
</script>

<template>
    <ActivityEditorDialog
        v-model:open="open"
        :mode="mode"
        :activity="activity"
        :number="number"
        :library="library"
        :media="block.media"
        :audio="block.audio"
        :read-only="readOnly"
        :saving="saving"
        :errors="errors"
        @save="save"
    />
</template>
