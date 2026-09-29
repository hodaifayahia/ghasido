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
 * Practice hub block (spec 0003 B.10, photo_7), also used for Email and Phone
 * activity blocks: the intro copy and the progress label, then the block's
 * activities, which the admin adds, edits, reorders and deletes here
 * (PRAC-01..07; client report 2026-09-29). Activities save their own rows
 * straight away; "Save block" saves the copy above them.
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
const progressLabel = settingField(settings, 'progress_label');
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            meaning
            v-model="subtitle"
            :label="$t('Subtitle')"
            type="textarea"
            :rows="2"
        />
        <LessonsField meaning v-model="motto" :label="$t('Motto')" />
        <LessonsField
            meaning
            v-model="progressLabel"
            :label="$t('Progress label')"
            :hint="$t('e.g. “activities completed”.')"
        />

        <LessonsActivityList
            :block="block"
            mode="practice"
            :library="library"
            :read-only="readOnly"
        />
    </div>
</template>
