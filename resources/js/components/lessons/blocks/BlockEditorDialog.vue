<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import CompleteEditor from '@/components/lessons/blocks/CompleteEditor.vue';
import DialogueEditor from '@/components/lessons/blocks/DialogueEditor.vue';
import GenericEditor from '@/components/lessons/blocks/GenericEditor.vue';
import LexiconEditor from '@/components/lessons/blocks/LexiconEditor.vue';
import ListenRepeatEditor from '@/components/lessons/blocks/ListenRepeatEditor.vue';
import PracticeEditor from '@/components/lessons/blocks/PracticeEditor.vue';
import QuizEditor from '@/components/lessons/blocks/QuizEditor.vue';
import RoleplayEditor from '@/components/lessons/blocks/RoleplayEditor.vue';
import SituationEditor from '@/components/lessons/blocks/SituationEditor.vue';
import VideoEditor from '@/components/lessons/blocks/VideoEditor.vue';
import { cloneData } from '@/components/lessons/lessonsBlocks';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { update } from '@/routes/blocks';
import type {
    BlockSettings,
    BlockTypeKey,
    LessonBlockRow,
    LessonBlockTypeOption,
    LessonScenarioOption,
    LessonsImageLibrary,
} from '@/types';

/**
 * The block editor (BLD-03; spec 0003 B.10): one dialog, one editor
 * component per block type. Settings are edited as a local copy and saved
 * whole through blocks.update; list-like children (lexicon items,
 * activities) save their own rows straight away.
 */
type Props = {
    block: LessonBlockRow;
    blockTypes: LessonBlockTypeOption[];
    scenarios: LessonScenarioOption[];
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const title = ref<string>(props.block.title ?? '');
const settings = ref<BlockSettings>(cloneData(props.block.settings));
const scenarioIds = ref<number[]>([...props.block.scenarioIds]);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

function resetFrom(block: LessonBlockRow): void {
    title.value = block.title ?? '';
    settings.value = cloneData(block.settings);
    scenarioIds.value = [...block.scenarioIds];
    errors.value = {};
}

watch(
    () => props.block.id,
    () => resetFrom(props.block),
);

watch(open, (isOpen) => {
    if (isOpen) {
        resetFrom(props.block);
    }
});

const editors: Record<BlockTypeKey, Component> = {
    situation: SituationEditor,
    vocabulary: LexiconEditor,
    expressions: LexiconEditor,
    listen_repeat: ListenRepeatEditor,
    dialogue: DialogueEditor,
    video: VideoEditor,
    practice: PracticeEditor,
    quiz: QuizEditor,
    ai_roleplay: RoleplayEditor,
    complete: CompleteEditor,
    text: GenericEditor,
    note: GenericEditor,
    image: GenericEditor,
    audio: GenericEditor,
    email_activity: PracticeEditor,
    phone_activity: PracticeEditor,
};

const typeOption = computed(() =>
    props.blockTypes.find((type) => type.value === props.block.type),
);

/** Only the role-play editor edits the scenario list; nothing else receives it. */
const roleplayBindings = computed(() =>
    props.block.type === 'ai_roleplay'
        ? {
              scenarioIds: scenarioIds.value,
              'onUpdate:scenarioIds': (ids: number[]): void => {
                  scenarioIds.value = ids;
              },
          }
        : {},
);

const description = computed(
    () =>
        typeOption.value?.description ??
        t('Edit what the learner sees on this step.'),
);

function save(): void {
    if (props.readOnly) {
        open.value = false;

        return;
    }

    saving.value = true;
    errors.value = {};

    router.patch(
        update.url(props.block.id),
        {
            title: title.value.trim() === '' ? null : title.value.trim(),
            settings: settings.value as unknown as FormDataConvertible,
            ...(props.block.type === 'ai_roleplay'
                ? { scenario_ids: scenarioIds.value }
                : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onError: (bag) => {
                errors.value = bag;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="`${block.stepLabel}: ${block.label}`"
        :description="description"
        size="xl"
    >
        <div class="mt-2 grid gap-4">
            <LessonsField
                v-model="title"
                :label="$t('Step title (optional)')"
                :placeholder="typeOption?.label ?? block.label"
                :hint="
                    $t(
                        'Shown as the step heading; leave empty for the default.',
                    )
                "
                :error="errors.title"
            />

            <component
                :is="editors[block.type]"
                v-model:settings="settings"
                v-bind="roleplayBindings"
                :block="block"
                :scenarios="scenarios"
                :library="library"
                :read-only="readOnly"
            />

            <p
                v-if="errors.settings"
                class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
                role="alert"
            >
                {{ errors.settings }}
            </p>

            <div
                class="mt-1 flex flex-col-reverse gap-2 md:flex-row md:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="block-editor-cancel"
                    @click="open = false"
                >
                    {{ readOnly ? $t('Close') : $t('Cancel') }}
                </Button>
                <Button
                    v-if="!readOnly"
                    type="button"
                    :disabled="saving"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="block-editor-save"
                    data-tour="save-block"
                    @click="save"
                >
                    {{ saving ? $t('Saving…') : $t('Save block') }}
                </Button>
            </div>
        </div>
    </LessonsModal>
</template>
