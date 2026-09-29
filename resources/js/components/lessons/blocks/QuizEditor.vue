<script setup lang="ts">
import LessonsActivityList from '@/components/lessons/activities/LessonsActivityList.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { settingField } from '@/components/lessons/lessonsBlocks';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonsImageLibrary,
} from '@/types';

/**
 * Quiz / Test block (client report 2026-09-29): a short intro, then the
 * quiz questions. Each question is one activity with one item (like a test
 * question, spec 0003 B.9): the admin picks its type, writes it, enters the
 * answers and marks the correct one; questions are reordered and deleted
 * here and saved straight away. Learners' answers are stored verbatim
 * against the question's version (TEST-06, DATA-11).
 */
type Props = {
    block: LessonBlockRow;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const motto = settingField(settings, 'motto');
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            meaning
            v-model="subtitle"
            :label="$t('Introduction')"
            type="textarea"
            :rows="2"
        />
        <LessonsField
            meaning
            v-model="motto"
            :label="$t('Encouragement')"
            :hint="$t('Shown next to the learner’s progress.')"
        />

        <LessonsActivityList
            :block="block"
            mode="quiz"
            :library="library"
            :read-only="readOnly"
        />
    </div>
</template>
