<script setup lang="ts">
import { Edit3, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Button } from '@/components/ui/button';
import TtsVoiceStudio from '@/components/tts/TtsVoiceStudio.vue';
import { useI18n } from '@/composables/useI18n';
import type { LessonDirectoryRow, TtsSettings } from '@/types';

/*
 * The voice editor belongs to an AI Role-play record, not the general lesson
 * canvas. The directory keeps the lesson/department context visible while the
 * actual Deepgram form stays behind an explicit Create/Edit action (RP-01,
 * TTS-04, CMS-01).
 */
type Props = {
    lessons: LessonDirectoryRow[];
    settings: TtsSettings;
};

const props = defineProps<Props>();

const { t } = useI18n();

const open = ref(false);
const mode = ref<'create' | 'edit'>('create');
const selectedLesson = ref<LessonDirectoryRow | null>(null);

const dialogTitle = computed(() =>
    mode.value === 'create'
        ? t('Create AI Role-play voice')
        : t('Edit voice · :lesson', {
              lesson: selectedLesson.value?.title ?? t('Lesson'),
          }),
);

const dialogDescription = computed(() =>
    selectedLesson.value
        ? selectedLesson.value.department + ' · ' + selectedLesson.value.course
        : t(
              'Set the voice used by AI Role-play guest replies and stored lesson audio.',
          ),
);

const voiceLabel = computed(() =>
    props.settings.voice
        .replace('flux-', '')
        .replace('-en', '')
        .replaceAll('-', ' '),
);

function create(): void {
    mode.value = 'create';
    selectedLesson.value = null;
    open.value = true;
}

function edit(lesson: LessonDirectoryRow): void {
    mode.value = 'edit';
    selectedLesson.value = lesson;
    open.value = true;
}
</script>

<template>
    <PanelCard
        :title="$t('AI Role-play voice settings')"
        title-id="ai-roleplay-voice-settings-title"
        class="px-3 pt-3 pb-3 md:px-4"
        body-class="mt-2"
    >
        <template #actions>
            <Button
                type="button"
                class="bg-brand-600 hover:bg-brand-700 shadow-btn h-8 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold text-white"
                @click="create"
            >
                <Plus class="size-3.5" aria-hidden="true" />
                {{ $t('Create') }}
            </Button>
        </template>

        <p class="text-ink-slate mb-2 text-[11.5px]">
            {{
                $t(
                    'Choose a lesson to edit its AI Role-play voice configuration. The selected voice is used for stored guest replies and lesson audio.',
                )
            }}
        </p>

        <div
            v-if="props.lessons.length > 0"
            class="border-line max-h-[300px] overflow-y-auto rounded-md border"
        >
            <table class="w-full table-fixed text-start">
                <colgroup>
                    <col class="w-[34%]" />
                    <col class="w-[24%]" />
                    <col class="w-[20%]" />
                    <col class="w-[22%]" />
                </colgroup>
                <thead class="bg-brand-50/70 sticky top-0 z-[1]">
                    <tr class="border-line border-b">
                        <th
                            class="text-brand-900 px-2.5 py-2 text-[10.5px] font-semibold md:px-3"
                        >
                            {{ $t('Lesson') }}
                        </th>
                        <th
                            class="text-brand-900 px-2 py-2 text-[10.5px] font-semibold"
                        >
                            {{ $t('Department') }}
                        </th>
                        <th
                            class="text-brand-900 px-2 py-2 text-[10.5px] font-semibold"
                        >
                            {{ $t('Voice') }}
                        </th>
                        <th
                            class="text-brand-900 px-2 py-2 text-[10.5px] font-semibold"
                        >
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="lesson in props.lessons"
                        :key="lesson.id"
                        class="border-line hover:bg-brand-50/45 border-b transition-colors last:border-0"
                    >
                        <td class="px-2.5 py-2 align-top md:px-3">
                            <span
                                class="text-brand-700 block truncate text-[12px] font-semibold"
                            >
                                {{ lesson.title }}
                            </span>
                            <span
                                class="text-ink-faint block truncate text-[10px]"
                            >
                                {{ lesson.course }} · {{ lesson.unit }}
                            </span>
                        </td>
                        <td
                            class="text-ink-slate px-2 py-2 align-top text-[11px]"
                        >
                            <span class="block truncate">{{
                                lesson.department
                            }}</span>
                        </td>
                        <td class="px-2 py-2 align-top">
                            <span
                                class="bg-ai-tint text-ai rounded-pill inline-flex max-w-full truncate px-1.5 py-0.5 text-[9.5px] font-semibold capitalize"
                            >
                                {{ voiceLabel }}
                            </span>
                        </td>
                        <td class="px-2 py-2 align-top">
                            <Button
                                type="button"
                                variant="outline"
                                class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-2.5 text-[11px] font-semibold shadow-none"
                                :data-test="'edit-tts-lesson-' + lesson.id"
                                @click="edit(lesson)"
                            >
                                <Edit3 class="size-3.5" aria-hidden="true" />
                                {{ $t('Edit') }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p
            v-else
            class="border-line bg-brand-50/35 text-ink-slate rounded-md border border-dashed px-3 py-5 text-center text-[12px]"
        >
            {{
                $t(
                    'Create a lesson first, then configure its AI Role-play voice.',
                )
            }}
        </p>

        <LessonsModal
            v-model:open="open"
            size="xl"
            :title="dialogTitle"
            :description="dialogDescription"
        >
            <TtsVoiceStudio
                :settings="props.settings"
                compact
                class="border-0 p-0 shadow-none"
            />
        </LessonsModal>
    </PanelCard>
</template>
