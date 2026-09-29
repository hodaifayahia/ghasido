<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import {
    Check,
    CircleAlert,
    CirclePlus,
    Copy,
    Eye,
    GripVertical,
    LoaderCircle,
    Pencil,
    RefreshCw,
    Save,
    Sparkles,
    Trash2,
    Volume2,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    activitySummary,
    activityTypeSpec,
    correctAnswer,
} from '@/components/activities/activityCatalog';
import { toneClass } from '@/components/lessons/lessonsBlocks';
import LessonsActivityDeleteDialog from '@/components/lessons/activities/LessonsActivityDeleteDialog.vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import TestsAiGenerateDialog from '@/components/tests/TestsAiGenerateDialog.vue';
import TestsQuestionDialog from '@/components/tests/TestsQuestionDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    LessonsImageLibrary,
    TestAiGeneratePayload,
    TestEditor,
    TestEditorQuestion,
    TestEditorSavePayload,
    TestQuestionAudioStatus,
    TestQuestionKind,
} from '@/types';

type Props = {
    editor: TestEditor;
    /** Shows the Delete test action (tests.manage). */
    canDelete?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { canDelete: false });

const { t } = useI18n();

const emit = defineEmits<{
    save: [payload: TestEditorSavePayload];
    publish: [];
    preview: [];
    delete: [];
    'generate-ai': [payload: TestAiGeneratePayload];
    'regenerate-ai': [];
    'generate-all-audio': [];
    'generate-audio': [question: TestEditorQuestion];
    'release-question': [question: TestEditorQuestion];
}>();

const aiDialogOpen = ref(false);

/** The library the question editor's media slots browse (MED-02). */
const library = computed<LessonsImageLibrary>(() => ({
    activeTab: 'guesvia-library',
    tabs: [
        { key: 'guesvia-library', label: tk('GHASIDO Library') },
        { key: 'my-images', label: tk('My Images') },
        { key: 'icons-stickers', label: tk('Icons & Stickers') },
    ],
    search: '',
    category: 'all-categories',
    categories: [{ value: 'all-categories', label: tk('All categories') }],
    images: [],
}));

const aiBusy = computed(
    () =>
        props.editor.ai?.status === 'pending' ||
        props.editor.ai?.status === 'running',
);

const audioLabels: Record<TestQuestionAudioStatus, string> = {
    missing: tk('No audio yet'),
    pending: tk('Audio queued…'),
    running: tk('Generating audio…'),
    failed: tk('Audio failed'),
    done: tk('Audio ready'),
};

function generate(payload: TestAiGeneratePayload): void {
    aiDialogOpen.value = false;
    emit('generate-ai', payload);
}

const type = ref(props.editor.type);
const department = ref(props.editor.department);
const title = ref(props.editor.title);
const timeLimit = ref(props.editor.timeLimit);
const questionCount = ref(props.editor.questionCount);
const description = ref(props.editor.description);
const activeKind = ref<TestQuestionKind>(props.editor.activeKind);

function syncEditor(editor: TestEditor): void {
    type.value = editor.type;
    department.value = editor.department;
    title.value = editor.title;
    timeLimit.value = editor.timeLimit;
    questionCount.value = editor.questionCount;
    description.value = editor.description;
}

watch(() => props.editor, syncEditor, { deep: true, immediate: true });

function onSelect(target: 'type' | 'department', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'type') {
        type.value = value;
        return;
    }

    department.value = value;
}

/*
 * Questions are added and edited in the shared activity editor, the same
 * one the lesson Practice and Quiz blocks use (client report 2026-09-29).
 * Picking a type tile opens it on that type.
 */
const questionDialogOpen = ref(false);
const editing = ref<TestEditorQuestion | null>(null);

function openNew(kind: TestQuestionKind): void {
    activeKind.value = kind;
    editing.value = null;
    questionDialogOpen.value = true;
}

function openEdit(question: TestEditorQuestion): void {
    editing.value = question;
    questionDialogOpen.value = true;
}

/** A copy is a new question with the same content (a new activity). */
function duplicateQuestion(question: TestEditorQuestion): void {
    if (props.editor.questionStoreUrl === null || question.activity === null) {
        return;
    }

    router.post(
        props.editor.questionStoreUrl,
        {
            type: question.activity.type,
            title: null,
            prompt: question.activity.prompt,
            payload: question.activity
                .payload as unknown as FormDataConvertible,
        },
        { preserveScroll: true },
    );
}

/*
 * Removing a question takes it off this test only: its activity and every
 * answer given to it are kept (DATA-10, DATA-11).
 */
const removing = ref<TestEditorQuestion | null>(null);
const removeBusy = ref(false);
const removeOpen = computed({
    get: () => removing.value !== null,
    set: (open: boolean) => {
        if (!open) removing.value = null;
    },
});

function confirmRemove(): void {
    const question = removing.value;

    if (question === null) {
        return;
    }

    router.delete(question.deleteUrl, {
        preserveScroll: true,
        onStart: () => {
            removeBusy.value = true;
        },
        onFinish: () => {
            removeBusy.value = false;
            removing.value = null;
        },
    });
}

function typeOf(question: TestEditorQuestion) {
    return activityTypeSpec(question.activity?.type ?? question.kind);
}

function summary(question: TestEditorQuestion): string {
    return question.activity === null
        ? question.text
        : activitySummary(question.activity);
}

function answer(question: TestEditorQuestion): string | null {
    return question.activity === null ? null : correctAnswer(question.activity);
}

/*
 * Saves the header fields. The per-test rules (shuffling, attempts, result
 * visibility, Show Meaning, pass mark) lost their card when the client
 * removed "Test Settings" (2026-09-29); their stored values are sent back
 * unchanged so a save never resets them.
 */
function save(): void {
    const existing = props.editor.settings;

    emit('save', {
        title: title.value.trim(),
        type: type.value as TestEditorSavePayload['type'],
        department_id: Number(department.value),
        hotel_id: props.editor.hotel ? Number(props.editor.hotel) : null,
        description: description.value.trim(),
        time_limit_minutes:
            timeLimit.value === '' ? null : Number(timeLimit.value),
        question_count:
            Number(questionCount.value) || props.editor.questions.length,
        shuffle_questions: existing.shuffle_questions,
        shuffle_options: existing.shuffle_options,
        single_attempt: existing.single_attempt,
        results_visibility: existing.results_visibility,
        show_answers: existing.show_answers,
        motivational_message: existing.motivational_message,
        show_meaning: existing.show_meaning,
        pass_mark: existing.passMark === '' ? null : Number(existing.passMark),
    });
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-4',
                props.class,
            )
        "
    >
        <!-- Save and Preview moved here from the removed "Test Settings"
             card (client request 2026-09-29): they were the only way to
             save the fields below and to preview the test. -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-heading text-brand-800 text-base font-semibold">
                {{ $t('Create / Edit Test') }}
            </h2>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button
                    v-if="canDelete && editor.id !== null"
                    type="button"
                    variant="outline"
                    class="border-danger/60 text-danger-text hover:bg-danger-tint hover:text-danger-text h-11 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none md:h-9"
                    data-test="delete-test-button"
                    @click="emit('delete')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
                <Button
                    v-if="editor.id !== null"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none md:h-9"
                    data-test="preview-test-button"
                    @click="emit('preview')"
                >
                    <Eye class="size-4" aria-hidden="true" />
                    {{ $t('Preview Test') }}
                </Button>
                <Button
                    v-if="editor.updateUrl"
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white md:h-9"
                    :disabled="title.trim() === ''"
                    data-test="save-test-button"
                    @click="save"
                >
                    <Save class="size-4" aria-hidden="true" />
                    {{ $t('Save Test') }}
                </Button>
            </div>
        </div>

        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <div class="grid gap-1.5">
                <label
                    for="test-title"
                    class="text-brand-900 text-[12px] font-semibold"
                >
                    {{ $t('Test Title') }} <span class="text-danger">*</span>
                </label>
                <Input
                    id="test-title"
                    v-model="title"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                />
            </div>

            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Test Type') }}
                </label>
                <Select
                    :model-value="type"
                    @update:model-value="onSelect('type', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in editor.typeOptions"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="mt-3 grid gap-3 md:grid-cols-3">
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Department') }} <span class="text-danger">*</span>
                </label>
                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in editor.departments"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="test-time-limit"
                    class="text-brand-900 text-[12px] font-semibold"
                >
                    {{ $t('Time Limit (minutes)') }}
                </label>
                <Input
                    id="test-time-limit"
                    type="number"
                    v-model="timeLimit"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                />
            </div>

            <div class="grid gap-1.5">
                <label
                    for="test-question-count"
                    class="text-brand-900 text-[12px] font-semibold"
                >
                    {{ $t('Number of Questions') }}
                </label>
                <Input
                    id="test-question-count"
                    type="number"
                    v-model="questionCount"
                    class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[13px] shadow-none"
                />
            </div>
        </div>

        <div class="mt-3 grid gap-1.5">
            <label
                for="test-description"
                class="text-brand-900 text-[12px] font-semibold"
            >
                {{ $t('Test Description (optional)') }}
            </label>
            <textarea
                id="test-description"
                rows="2"
                class="border-line text-ink bg-surface min-h-[64px] w-full resize-none rounded-md border px-3 py-2 text-[13px] leading-[1.55] outline-none"
                v-model="description"
            />
            <div class="flex justify-end">
                <span class="text-ink-faint text-[11px] font-medium">
                    {{ description.length }}/300
                </span>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <h3 class="font-heading text-brand-800 text-base font-semibold">
                {{ $t('Questions') }}
            </h3>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button
                    v-if="editor.publishUrl"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-9 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="emit('publish')"
                >
                    {{ $t('Publish Test') }}
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                    :disabled="editor.questionStoreUrl === null"
                    data-test="add-question-button"
                    @click="openNew(activeKind)"
                >
                    <CirclePlus class="size-4" aria-hidden="true" />
                    {{ $t('Add Question') }}
                </Button>
            </div>
        </div>

        <!-- The client's ten question types: a tile opens the question
             editor on that type (client report 2026-09-29). -->
        <div
            class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5"
            role="list"
            :aria-label="$t('Question types')"
        >
            <button
                v-for="kind in editor.kinds"
                :key="kind.value"
                type="button"
                role="listitem"
                :disabled="editor.questionStoreUrl === null"
                :data-test="`question-kind-${kind.value}`"
                :class="
                    cn(
                        'focus-visible:ring-brand-600/15 flex min-h-11 flex-col items-center justify-center gap-1.5 rounded-md border px-2 py-3 text-center text-[11px] font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none disabled:opacity-50',
                        activeKind === kind.value
                            ? 'border-brand-600 bg-brand-50 text-brand-700'
                            : 'border-line text-ink-slate hover:bg-brand-50/60',
                    )
                "
                @click="openNew(kind.value)"
            >
                <component
                    :is="activityTypeSpec(kind.value).icon"
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />
                {{ kind.label }}
            </button>
        </div>

        <!-- AI questions: generate, state, regenerate, audio (GEN-01, GEN-04,
             PERF-04). One block, so the mockup's "Questions + Add Question"
             header keeps its layout. -->
        <div
            v-if="editor.ai"
            class="border-line bg-brand-50/35 mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 rounded-md border px-3 py-2 text-[12px]"
            role="status"
            data-test="ai-questions-strip"
        >
            <div class="flex min-w-48 flex-1 items-start gap-2">
                <LoaderCircle
                    v-if="aiBusy"
                    class="text-brand-600 mt-0.5 size-4 shrink-0 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <CircleAlert
                    v-else-if="editor.ai.status === 'failed'"
                    class="text-danger mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <Check
                    v-else-if="editor.ai.status === 'done'"
                    class="text-success mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <Sparkles
                    v-else
                    class="text-ai mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p class="min-w-0 leading-5">
                    <span v-if="aiBusy" class="text-brand-900 font-semibold">
                        {{ $t('Generating questions with AI…') }}
                    </span>
                    <template v-else-if="editor.ai.status === 'failed'">
                        <span class="text-danger-text font-semibold">
                            {{ $t('Generation failed.') }}
                        </span>
                        <span class="text-ink-slate">
                            {{ editor.ai.failedReason }}
                        </span>
                    </template>
                    <span
                        v-else-if="editor.ai.status === 'done'"
                        class="text-brand-900 font-semibold"
                    >
                        {{
                            $t(
                                'AI drafts added. Review them, then approve or publish.',
                            )
                        }}
                    </span>
                    <span v-else class="text-brand-900">
                        {{
                            $t(
                                'Draft new questions with AI. They stay hidden until you approve them.',
                            )
                        }}
                    </span>
                </p>
            </div>
            <div class="ms-auto flex flex-wrap items-center justify-end gap-1">
                <Button
                    v-if="
                        editor.ai.canRegenerate &&
                        !aiBusy &&
                        editor.ai.status !== null
                    "
                    type="button"
                    variant="ghost"
                    class="text-brand-700 hover:bg-brand-50 h-11 gap-1.5 px-2 text-[11.5px] font-semibold md:h-8"
                    data-test="regenerate-ai-questions-button"
                    @click="emit('regenerate-ai')"
                >
                    <RefreshCw class="size-3.5" aria-hidden="true" />
                    {{ $t('Regenerate') }}
                </Button>
                <Button
                    v-if="editor.questions.some((q) => q.audio)"
                    type="button"
                    variant="ghost"
                    class="text-brand-700 hover:bg-brand-50 h-11 gap-1.5 px-2 text-[11.5px] font-semibold md:h-8"
                    data-test="generate-all-audio-button"
                    @click="emit('generate-all-audio')"
                >
                    <Volume2 class="size-3.5" aria-hidden="true" />
                    {{ $t('Generate all audio') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="aiBusy"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
                    data-test="open-ai-generate-button"
                    @click="aiDialogOpen = true"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{ $t('Generate with AI') }}
                </Button>
            </div>
        </div>

        <p
            v-if="editor.questions.length === 0"
            class="border-line text-ink-slate mt-3 rounded-md border border-dashed px-3 py-6 text-center text-[12.5px]"
        >
            {{ $t('No questions yet. Pick a question type above to add one.') }}
        </p>

        <article
            v-for="question in editor.questions"
            :key="question.id"
            class="border-line bg-surface mt-3 rounded-md border p-3"
            :data-test="`test-question-${question.index}`"
        >
            <div class="flex items-center gap-2">
                <GripVertical
                    class="text-ink-faint size-4 shrink-0"
                    aria-hidden="true"
                />
                <span
                    :class="
                        cn(
                            'grid size-7 shrink-0 place-items-center rounded-lg',
                            toneClass(typeOf(question).tone),
                        )
                    "
                >
                    <component
                        :is="typeOf(question).icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                </span>
                <span
                    class="text-brand-900 min-w-0 flex-1 truncate text-[13px] font-semibold"
                >
                    {{ $t('Question :number', { number: question.index }) }}
                    <span class="text-ink-slate font-medium">
                        · {{ question.typeLabel }}
                    </span>
                </span>
                <button
                    type="button"
                    class="text-brand-700 hover:bg-brand-50 inline-flex size-9 items-center justify-center rounded-md md:size-7"
                    :aria-label="
                        $t('Edit question :number', { number: question.index })
                    "
                    :data-test="`edit-question-${question.index}-button`"
                    @click="openEdit(question)"
                >
                    <Pencil class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="text-ink-faint hover:bg-brand-50 inline-flex size-9 items-center justify-center rounded-md md:size-7"
                    :aria-label="
                        $t('Duplicate question :number', {
                            number: question.index,
                        })
                    "
                    @click="duplicateQuestion(question)"
                >
                    <Copy class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="text-danger-text hover:bg-danger-tint inline-flex size-9 items-center justify-center rounded-md md:size-7"
                    :aria-label="
                        $t('Delete question :number', {
                            number: question.index,
                        })
                    "
                    :data-test="`delete-question-${question.index}`"
                    @click="removing = question"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </div>

            <!-- AI draft (GEN-03): hidden from learners until approved. -->
            <div
                v-if="question.aiDraft"
                class="mt-2 flex flex-wrap items-center gap-2"
            >
                <span
                    class="bg-ai-tint text-ai rounded-pill inline-flex items-center gap-1 px-2 py-0.5 text-[10.5px] font-semibold whitespace-nowrap"
                >
                    <Sparkles class="size-3" aria-hidden="true" />
                    {{ $t('AI draft · hidden from learners') }}
                </span>
                <Button
                    v-if="question.releaseUrl"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1 rounded-md px-2.5 text-[11px] font-semibold shadow-none md:h-7"
                    :data-test="`approve-question-${question.index}-button`"
                    @click="emit('release-question', question)"
                >
                    <Check class="size-3.5" aria-hidden="true" />
                    {{ $t('Approve') }}
                </Button>
            </div>

            <div class="tests-question-layout mt-3 grid gap-3">
                <div class="grid content-start gap-1.5">
                    <p class="text-ink text-[13px] leading-[1.5]">
                        {{ summary(question) }}
                    </p>
                    <p
                        v-if="answer(question)"
                        class="text-success-text flex items-start gap-1 text-[12px] font-semibold"
                    >
                        <Check
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            {{ $t('Correct answer') }}: {{ answer(question) }}
                        </span>
                    </p>
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 mt-1 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                            @click="openEdit(question)"
                        >
                            <Pencil class="size-3.5" aria-hidden="true" />
                            {{ $t('Edit question') }}
                        </Button>
                    </div>
                </div>

                <div class="grid content-start gap-2">
                    <img
                        v-if="question.media.image"
                        :src="
                            question.media.image.thumbUrl ||
                            question.media.image.url
                        "
                        :alt="
                            question.media.image.alt ||
                            question.media.image.label
                        "
                        class="border-line aspect-[4/3] w-full rounded-md border object-cover"
                    />
                    <LessonsMockupCrop
                        v-else-if="question.imageCrop"
                        :crop="question.imageCrop"
                        src="/decor/tests-mockup.jpg"
                        :alt="
                            $t('Question :number image', {
                                number: question.index,
                            })
                        "
                        class="border-line w-full rounded-md border"
                    />
                </div>
            </div>

            <!-- What the summary cannot show: scripts, lines, model answers. -->
            <dl
                v-if="question.details?.length"
                class="border-line bg-app/60 mt-3 grid gap-1.5 rounded-md border px-3 py-2"
            >
                <div
                    v-for="(detail, i) in question.details"
                    :key="i"
                    class="grid gap-0.5 text-[12px] sm:grid-cols-[112px_minmax(0,1fr)] sm:gap-2"
                >
                    <dt class="text-ink-slate font-semibold">
                        {{ detail.label }}
                    </dt>
                    <dd class="text-ink leading-snug">{{ detail.text }}</dd>
                </div>
            </dl>

            <!-- Stored listening audio (TTS-01, CTRL-05, PERF-04). -->
            <div
                v-if="question.audio"
                class="mt-2 flex flex-wrap items-center gap-2 text-[12px]"
            >
                <Volume2 class="text-brand-600 size-4" aria-hidden="true" />
                <span
                    :class="
                        cn(
                            'inline-flex items-center gap-1 font-semibold',
                            question.audio.status === 'done'
                                ? 'text-success-text'
                                : question.audio.status === 'failed'
                                  ? 'text-danger-text'
                                  : 'text-ink-slate',
                        )
                    "
                >
                    <Check
                        v-if="question.audio.status === 'done'"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <CircleAlert
                        v-else-if="question.audio.status === 'failed'"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <LoaderCircle
                        v-else-if="
                            question.audio.status === 'pending' ||
                            question.audio.status === 'running'
                        "
                        class="size-3.5 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    {{ $t(audioLabels[question.audio.status]) }}
                </span>
                <span
                    v-if="question.audio.failedReason"
                    class="text-ink-slate min-w-0 flex-1 truncate"
                >
                    {{ question.audio.failedReason }}
                </span>
                <Button
                    v-if="
                        question.audio.status === 'missing' ||
                        question.audio.status === 'failed'
                    "
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none"
                    @click="emit('generate-audio', question)"
                >
                    {{ $t('Generate audio') }}
                </Button>
            </div>
        </article>

        <TestsAiGenerateDialog
            v-if="editor.ai"
            v-model:open="aiDialogOpen"
            :ai="editor.ai"
            :test-title="editor.title"
            @generate="generate"
        />
        <TestsQuestionDialog
            v-model:open="questionDialogOpen"
            :question="editing"
            :initial-type="editing === null ? activeKind : null"
            :store-url="editor.questionStoreUrl"
            :number="editor.questions.length + 1"
            :library="library"
        />
        <LessonsActivityDeleteDialog
            v-model:open="removeOpen"
            :title="
                $t('Delete question :number?', {
                    number: removing?.index ?? '',
                })
            "
            :description="
                $t(
                    'It is removed from this test. Answers employees already gave are kept.',
                )
            "
            :processing="removeBusy"
            @confirm="confirmRemove"
        />
    </section>
</template>

<style scoped>
@media (min-width: 768px) {
    .tests-question-layout {
        grid-template-columns: minmax(0, 1fr) 168px;
    }
}
</style>
