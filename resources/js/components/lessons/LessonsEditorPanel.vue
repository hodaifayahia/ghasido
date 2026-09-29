<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    CirclePlus,
    Image,
    Link2,
    List,
    ListOrdered,
    MoreHorizontal,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import LessonsBlockList from '@/components/lessons/LessonsBlockList.vue';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import LessonsMaterialsTab from '@/components/lessons/tabs/LessonsMaterialsTab.vue';
import LessonsPreviewTab from '@/components/lessons/tabs/LessonsPreviewTab.vue';
import LessonsQuizTab from '@/components/lessons/tabs/LessonsQuizTab.vue';
import LessonsRoleplayTab from '@/components/lessons/tabs/LessonsRoleplayTab.vue';
import LessonsSettingsTab from '@/components/lessons/tabs/LessonsSettingsTab.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/composables/useCan';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update } from '@/routes/lessons';
import type {
    LessonBlockRow,
    LessonBlockTypeOption,
    LessonDirectoryRow,
    LessonEditor,
    LessonLibraryImage,
    LessonScenarioOption,
    LessonsImageLibrary,
    LessonsTabKey,
    TtsSettings,
} from '@/types';

/**
 * The middle column: the lesson editor on the Lesson Content tab (title,
 * cover, introduction, objectives, and the Lesson Blocks list beneath), and
 * the Preview / Settings / Materials / AI Role-play / Quiz tabs. Every
 * field autosaves on blur through lessons.update and shows a "Saved" hint
 * (BLD-08, CMS-01, CMS-03, CMS-05).
 */
type Props = {
    editor: LessonEditor;
    activeTab: LessonsTabKey;
    tts: TtsSettings;
    lessonDirectory: LessonDirectoryRow[];
    lessonBlocks: LessonBlockRow[];
    blockTypes: LessonBlockTypeOption[];
    scenarios: LessonScenarioOption[];
    library: LessonsImageLibrary;
    class?: HTMLAttributes['class'];
};

type LessonPatch = {
    title?: string;
    introduction?: string;
    objectives?: string[];
    cover_media_id?: number | null;
};

const props = defineProps<Props>();

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const title = ref(props.editor.title);
const introduction = ref(props.editor.introduction);
const objectives = ref<string[]>([...props.editor.objectives]);
const newObjective = ref('');
const addingObjective = ref(false);
const saveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const pickerOpen = ref(false);

watch(
    () => props.editor,
    (editor) => {
        title.value = editor.title;
        introduction.value = editor.introduction;
        objectives.value = [...editor.objectives];
    },
);

const titleCount = computed(() => `${title.value.length}/100`);
const introductionCount = computed(() => `${introduction.value.length}/500`);

let savedTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * One PATCH per field on blur (BLD-08). Only the editor and the tree reload,
 * so the block list and the library keep their state.
 */
function save(patch: LessonPatch): void {
    if (props.editor.id === null || !manage.value) {
        return;
    }

    saveState.value = 'saving';

    router.patch(update.url(props.editor.id), patch, {
        preserveState: true,
        preserveScroll: true,
        only: ['editor', 'courses', 'filters'],
        onSuccess: () => {
            saveState.value = 'saved';

            if (savedTimer !== null) {
                clearTimeout(savedTimer);
            }

            savedTimer = setTimeout(() => {
                saveState.value = 'idle';
            }, 2500);
        },
        onError: () => {
            saveState.value = 'error';
        },
    });
}

function saveTitle(): void {
    const trimmed = title.value.trim();

    if (trimmed === '' || trimmed === props.editor.title) {
        title.value = props.editor.title;

        return;
    }

    save({ title: trimmed });
}

function saveIntroduction(): void {
    if (introduction.value !== props.editor.introduction) {
        save({ introduction: introduction.value });
    }
}

function addObjective(): void {
    const text = newObjective.value.trim();

    if (text === '') {
        addingObjective.value = false;

        return;
    }

    objectives.value = [...objectives.value, text];
    newObjective.value = '';
    addingObjective.value = false;
    save({ objectives: objectives.value });
}

function removeObjective(index: number): void {
    objectives.value = objectives.value.filter((_, i) => i !== index);
    save({ objectives: objectives.value });
}

function onCover(image: LessonLibraryImage): void {
    save({ cover_media_id: Number(image.id) });
}

function removeCover(): void {
    save({ cover_media_id: null });
}

defineExpose({ setCover: onCover });

const saveHint = computed(() => {
    switch (saveState.value) {
        case 'saving':
            return t('Saving…');
        case 'saved':
            return t('Saved');
        case 'error':
            return t('Could not save');
        default:
            return '';
    }
});

const toolbarButton =
    'text-brand-900 border-line flex h-7 w-7 items-center justify-center rounded-md border';
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-3',
                props.class,
            )
        "
        aria-live="polite"
    >
        <div
            v-if="editor.id === null"
            class="text-ink-slate flex min-h-40 items-center justify-center text-center text-[13px]"
        >
            {{
                $t(
                    'No lesson selected. Pick one in the Course Structure, or add one under a unit.',
                )
            }}
        </div>

        <LessonsPreviewTab
            v-else-if="activeTab === 'preview'"
            :editor="editor"
            :blocks="lessonBlocks"
        />

        <LessonsSettingsTab
            v-else-if="activeTab === 'settings'"
            :editor="editor"
        />

        <LessonsMaterialsTab
            v-else-if="activeTab === 'materials'"
            :editor="editor"
            :blocks="lessonBlocks"
        />

        <LessonsRoleplayTab
            v-else-if="activeTab === 'roleplay'"
            :blocks="lessonBlocks"
            :lessons="lessonDirectory"
            :scenarios="scenarios"
            :tts="tts"
        />

        <LessonsQuizTab
            v-else-if="activeTab === 'quiz'"
            :lesson-id="editor.id"
            :blocks="lessonBlocks"
            :library="library"
            :read-only="!manage"
        />

        <!-- minmax(0,1fr): wide content (the block list) must not widen
             the column past its panel. -->
        <div v-else class="grid grid-cols-[minmax(0,1fr)] gap-4">
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between gap-3">
                    <label
                        for="lesson-title"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        {{ $t('Lesson Title *') }}
                    </label>
                    <span class="text-ink-faint text-[11px] font-medium">
                        <span
                            v-if="saveHint !== ''"
                            :class="
                                cn(
                                    'me-2',
                                    saveState === 'error'
                                        ? 'text-danger-text'
                                        : 'text-success-text',
                                )
                            "
                            data-test="lesson-save-hint"
                        >
                            {{ saveHint }}
                        </span>
                        {{ titleCount }}
                    </span>
                </div>
                <Input
                    id="lesson-title"
                    v-model="title"
                    maxlength="100"
                    :readonly="!manage"
                    data-test="lesson-title-input"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                    @blur="saveTitle"
                    @keydown.enter.prevent="saveTitle"
                />
            </div>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Lesson Image (Cover) *') }}
                </label>

                <img
                    v-if="editor.coverUrl !== null"
                    :src="editor.coverUrl"
                    :alt="editor.coverAlt"
                    class="border-line bg-brand-50 aspect-[351/93] w-full rounded-md border object-cover"
                />
                <div
                    v-else
                    class="border-line bg-brand-50/40 text-ink-slate flex aspect-[351/93] w-full items-center justify-center rounded-md border border-dashed text-[12.5px]"
                >
                    <Image class="me-1.5 size-4" aria-hidden="true" />
                    {{ $t('No cover image yet') }}
                </div>

                <div
                    v-if="manage"
                    class="flex flex-wrap items-center gap-2 pt-0.5"
                >
                    <Button
                        type="button"
                        variant="outline"
                        data-test="change-cover-button"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="pickerOpen = true"
                    >
                        <Image class="size-3.5" aria-hidden="true" />
                        {{ $t('Change Image') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="editor.coverMediaId === null"
                        data-test="remove-cover-button"
                        class="border-line text-danger-text hover:bg-danger-tint h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="removeCover"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />
                        {{ $t('Remove') }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="lesson-introduction"
                    class="text-brand-900 text-[12px] font-semibold"
                >
                    {{ $t('Lesson Introduction *') }}
                </label>

                <div class="border-line overflow-hidden rounded-md border">
                    <div
                        class="border-line bg-surface flex flex-wrap items-center gap-1 border-b px-2 py-1.5"
                        aria-hidden="true"
                    >
                        <span
                            class="text-ink border-line flex h-7 items-center gap-1 rounded-md border px-2 text-[11.5px] font-medium"
                        >
                            {{ $t('Paragraph') }}
                            <ChevronDown class="size-3.5" />
                        </span>
                        <span
                            :class="cn(toolbarButton, 'text-[12px] font-bold')"
                        >
                            B
                        </span>
                        <span :class="cn(toolbarButton, 'text-[12px] italic')">
                            I
                        </span>
                        <span
                            :class="cn(toolbarButton, 'text-[12px] underline')"
                        >
                            U
                        </span>
                        <span :class="toolbarButton">
                            <List class="size-3.5" />
                        </span>
                        <span :class="toolbarButton">
                            <ListOrdered class="size-3.5" />
                        </span>
                        <span :class="toolbarButton">
                            <Link2 class="size-3.5" />
                        </span>
                        <span :class="toolbarButton">
                            <Image class="size-3.5" />
                        </span>
                        <span :class="toolbarButton">
                            <MoreHorizontal class="size-3.5" />
                        </span>
                    </div>

                    <div class="bg-surface px-3 py-2">
                        <textarea
                            id="lesson-introduction"
                            v-model="introduction"
                            rows="5"
                            maxlength="500"
                            :readonly="!manage"
                            data-test="lesson-introduction-input"
                            class="text-ink min-h-[108px] w-full resize-none border-0 bg-transparent p-0 text-[13px] leading-[1.65] outline-none"
                            @blur="saveIntroduction"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ introductionCount }}
                    </span>
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Lesson Objectives') }}
                    </h2>
                    <Button
                        v-if="manage"
                        type="button"
                        variant="outline"
                        data-test="add-objective-button"
                        class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="addingObjective = true"
                    >
                        <CirclePlus class="size-3.5" aria-hidden="true" />
                        {{ $t('Add Objective') }}
                    </Button>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="(objective, index) in objectives"
                        :key="`${index}-${objective}`"
                        class="border-line bg-surface flex min-h-11 items-center gap-3 rounded-md border px-3 py-2"
                    >
                        <span
                            class="bg-brand-100 text-brand-700 rounded-pill grid size-6 shrink-0 place-items-center"
                        >
                            <Check class="size-3.5" aria-hidden="true" />
                        </span>
                        <span class="text-ink min-w-0 flex-1 text-[13px]">
                            {{ objective }}
                        </span>
                        <button
                            v-if="manage"
                            type="button"
                            class="text-ink-faint hover:bg-brand-50 focus-visible:ring-brand-600/15 inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none"
                            :aria-label="
                                $t('Remove :item', { item: objective })
                            "
                            :data-test="`remove-objective-${index}`"
                            @click="removeObjective(index)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>

                    <div
                        v-if="addingObjective"
                        class="border-brand-400 bg-surface flex min-h-11 items-center gap-3 rounded-md border px-3 py-2"
                    >
                        <span
                            class="bg-brand-100 text-brand-700 rounded-pill grid size-6 shrink-0 place-items-center"
                        >
                            <CirclePlus class="size-3.5" aria-hidden="true" />
                        </span>
                        <input
                            v-model="newObjective"
                            type="text"
                            maxlength="160"
                            :placeholder="
                                $t('What will the learner be able to do?')
                            "
                            :aria-label="$t('New objective')"
                            data-test="new-objective-input"
                            class="text-ink placeholder:text-ink-faint min-w-0 flex-1 border-0 bg-transparent p-0 text-[13px] outline-none"
                            autofocus
                            @keydown.enter.prevent="addObjective"
                            @keydown.esc="addingObjective = false"
                            @blur="addObjective"
                        />
                    </div>

                    <p
                        v-if="objectives.length === 0 && !addingObjective"
                        class="text-ink-faint text-[12px]"
                    >
                        {{ $t('No objectives yet.') }}
                    </p>
                </div>
            </div>

            <LessonsBlockList
                :lesson-id="editor.id"
                :blocks="lessonBlocks"
                :block-types="blockTypes"
                :scenarios="scenarios"
                :library="library"
                class="border-line border-t pt-4"
            />
        </div>

        <LessonsMediaPicker
            v-model:open="pickerOpen"
            :tabs="library.tabs"
            :categories="library.categories"
            :initial-tab="library.activeTab"
            @choose="onCover"
        />
    </section>
</template>
