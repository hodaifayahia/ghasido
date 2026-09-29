<script setup lang="ts">
import { CirclePlus, ListChecks } from '@lucide/vue';
import { ref } from 'vue';
import ActivityBuilderDialog from '@/components/lessons/blocks/ActivityBuilderDialog.vue';
import { Button } from '@/components/ui/button';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { settingField, valueLabel } from '@/components/lessons/lessonsBlocks';
import type {
    BlockSettings,
    LessonBlockRow,
    LessonsImageLibrary,
} from '@/types';

/**
 * Practice hub block (spec 0003 B.10, photo_7), also used for Email and Phone
 * activity blocks: the intro copy and the progress label. The activities
 * themselves are their own rows (managed from the practice builder); here they
 * are listed so the admin sees what the step contains.
 */
type Props = {
    block: LessonBlockRow;
    readOnly: boolean;
    library: LessonsImageLibrary;
};

defineProps<Props>();

const activityBuilderOpen = ref(false);

const settings = defineModel<BlockSettings>('settings', { required: true });

const subtitle = settingField(settings, 'subtitle');
const motto = settingField(settings, 'motto');
const progressLabel = settingField(settings, 'progress_label');
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            translatable
            v-model="subtitle"
            :label="$t('Subtitle')"
            type="textarea"
            :rows="2"
        />
        <LessonsField v-model="motto" :label="$t('Motto')" />
        <LessonsField
            v-model="progressLabel"
            :label="$t('Progress label')"
            :hint="$t('e.g. “activities completed”.')"
        />

        <div class="grid gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ $t('Activities in this block') }}
                </span>
                <Button
                    v-if="!readOnly"
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold text-white"
                    data-test="add-lesson-activity"
                    @click="activityBuilderOpen = true"
                >
                    <CirclePlus class="size-4" aria-hidden="true" />
                    {{ $t('Add Activity') }}
                </Button>
            </div>
            <p
                v-if="block.activities.length === 0"
                class="text-ink-muted text-[12.5px]"
            >
                {{ $t('No activities are attached to this block yet.') }}
            </p>
            <ul v-else class="grid gap-1.5">
                <li
                    v-for="activity in block.activities"
                    :key="activity.id"
                    class="border-line bg-surface flex items-center gap-3 rounded-md border px-3 py-2"
                >
                    <span
                        class="bg-brand-100 text-brand-700 grid size-8 shrink-0 place-items-center rounded-md"
                    >
                        <ListChecks class="size-4" aria-hidden="true" />
                    </span>
                    <span class="grid min-w-0 gap-0.5">
                        <span
                            class="text-ink truncate text-[13px] font-semibold"
                        >
                            {{ activity.title ?? activity.label }}
                        </span>
                        <span class="text-ink-muted text-[11.5px]">
                            {{ activity.skillLabel ?? activity.label }} ·
                            {{
                                $tc(
                                    ':count item|:count items',
                                    activity.itemCount,
                                )
                            }}
                        </span>
                    </span>
                    <span class="text-ink-faint ms-auto text-[11px] capitalize">
                        {{ $t(valueLabel(activity.status)) }}
                    </span>
                </li>
            </ul>
        </div>
        <ActivityBuilderDialog
            v-if="!readOnly"
            v-model:open="activityBuilderOpen"
            :block="block"
            :library="library"
        />
    </div>
</template>
