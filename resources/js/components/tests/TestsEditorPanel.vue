<script setup lang="ts">
import {
    Check,
    CircleAlert,
    CirclePlay,
    CirclePlus,
    Copy,
    Grid2x2,
    GripVertical,
    Image,
    ListChecks,
    ListOrdered,
    LoaderCircle,
    Mic,
    PenLine,
    RefreshCw,
    Sparkles,
    TextCursorInput,
    ToggleLeft,
    Trash2,
    Volume2,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, reactive, ref, watch } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import TestsAiGenerateDialog from '@/components/tests/TestsAiGenerateDialog.vue';
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
    TestAiGeneratePayload,
    TestEditor,
    TestEditorQuestion,
    TestEditorSavePayload,
    TestQuestionAudioStatus,
    TestQuestionKind,
    TestQuestionOption,
    TestQuestionPayload,
    TestSettings,
} from '@/types';

type Props = {
    editor: TestEditor;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { t } = useI18n();

const emit = defineEmits<{
    save: [payload: TestEditorSavePayload];
    publish: [];
    'add-question': [payload: TestQuestionPayload];
    'save-question': [
        question: TestEditorQuestion,
        payload: TestQuestionPayload,
    ];
    'delete-question': [question: TestEditorQuestion];
    'generate-ai': [payload: TestAiGeneratePayload];
    'regenerate-ai': [];
    'generate-all-audio': [];
    'generate-audio': [question: TestEditorQuestion];
    'release-question': [question: TestEditorQuestion];
    'attach-image': [question: TestEditorQuestion, mediaId: number | null];
}>();

const aiDialogOpen = ref(false);
const imagePickerFor = ref<TestEditorQuestion | null>(null);
const imagePickerOpen = computed({
    get: () => imagePickerFor.value !== null,
    set: (open: boolean) => {
        if (!open) imagePickerFor.value = null;
    },
});
const libraryTabs = computed(() => [
    { key: 'guesvia-library', label: t('GHASIDO Library') },
    { key: 'my-images', label: t('My Images') },
    { key: 'icons-stickers', label: t('Icons & Stickers') },
]);
const libraryCategories = computed(() => [
    { value: 'all-categories', label: t('All categories') },
]);

/** Kinds with no options to edit: the prompt is the only field. */
const textOnlyKinds: TestQuestionKind[] = [
    'short_answer',
    'speaking',
    'ordering',
];

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

function chooseImage(media: { id: string }): void {
    if (imagePickerFor.value) {
        emit('attach-image', imagePickerFor.value, Number(media.id));
    }
    imagePickerFor.value = null;
}

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
const questions = ref<TestEditorQuestion[]>([]);
const questionKinds = reactive<Record<number, TestQuestionKind>>({});
const questionDrafts = reactive<Record<number, TestQuestionPayload>>({});

function syncEditor(editor: TestEditor): void {
    type.value = editor.type;
    department.value = editor.department;
    title.value = editor.title;
    timeLimit.value = editor.timeLimit;
    questionCount.value = editor.questionCount;
    description.value = editor.description;
    activeKind.value = editor.activeKind;
    questions.value = editor.questions.map((question) => ({
        ...question,
        options: question.options.map((option) => ({ ...option })),
    }));

    Object.keys(questionKinds).forEach((id) => {
        delete questionKinds[Number(id)];
    });
    Object.keys(questionDrafts).forEach((id) => {
        delete questionDrafts[Number(id)];
    });

    editor.questions.forEach((question) => {
        questionKinds[question.id] = question.kind;
        questionDrafts[question.id] = {
            kind: question.kind,
            text: question.text,
            options: question.options.map((option) => ({ ...option })),
        };
    });
}

watch(() => props.editor, syncEditor, { deep: true, immediate: true });

const kindIcons: Record<TestQuestionKind, Component> = {
    multiple_choice: ListChecks,
    true_false: ToggleLeft,
    fill_blank: TextCursorInput,
    matching: Grid2x2,
    short_answer: PenLine,
    audio: Volume2,
    image: Image,
    video: CirclePlay,
    speaking: Mic,
    ordering: ListOrdered,
};

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

function onQuestionKind(id: number, value: AcceptableValue): void {
    if (typeof value !== 'string' || !questionDrafts[id]) return;

    questionKinds[id] = value as TestQuestionKind;
    questionDrafts[id].kind = value as TestQuestionKind;
}

function setQuestionText(id: number, value: string): void {
    if (questionDrafts[id]) {
        questionDrafts[id].text = value;
    }
}

function questionPayload(question: TestEditorQuestion): TestQuestionPayload {
    const draft = questionDrafts[question.id];

    return {
        kind: draft?.kind ?? question.kind,
        text: draft?.text ?? question.text,
        options: (draft?.options ?? question.options).map((option) => ({
            ...option,
        })),
    };
}

function saveQuestion(question: TestEditorQuestion): void {
    emit('save-question', question, questionPayload(question));
}

function removeQuestion(question: TestEditorQuestion): void {
    emit('delete-question', question);
}

function questionOptions(question: TestEditorQuestion): TestQuestionOption[] {
    return questionDrafts[question.id]?.options ?? question.options;
}

function addOption(question: TestEditorQuestion): void {
    const options = questionOptions(question);
    const next = String.fromCharCode(65 + options.length);
    options.push({ id: next, text: '', correct: false });
}

function setOptionText(
    question: TestEditorQuestion,
    optionId: string,
    value: string,
): void {
    const option = questionOptions(question).find(
        (item) => item.id === optionId,
    );
    if (option) option.text = value;
}

function removeOption(question: TestEditorQuestion, optionId: string): void {
    const draft = questionDrafts[question.id];
    if (!draft) return;

    draft.options = draft.options.filter((option) => option.id !== optionId);
}

function markCorrect(question: TestEditorQuestion, optionId: string): void {
    questionOptions(question).forEach((option) => {
        option.correct = option.id === optionId;
    });
}

function addQuestion(): void {
    emit('add-question', {
        kind: activeKind.value,
        text: 'Write the question prompt here.',
        options: [
            { id: 'A', text: 'Option A', correct: true },
            { id: 'B', text: 'Option B', correct: false },
        ],
    });
}

function duplicateQuestion(question: TestEditorQuestion): void {
    emit('add-question', questionPayload(question));
}

function save(settings?: TestSettings): void {
    const toggle = (key: string, fallback: boolean): boolean =>
        settings?.toggles.find((item) => item.key === key)?.checked ?? fallback;
    const existingSettings = props.editor.settings;
    const resultsVisible = toggle(
        'show_results',
        existingSettings.results_visibility !== 'hidden',
    );
    const passMark = settings?.passMark ?? existingSettings.passMark;

    emit('save', {
        title: title.value.trim(),
        type: type.value as TestEditorSavePayload['type'],
        department_id: Number(department.value),
        hotel_id: props.editor.hotel ? Number(props.editor.hotel) : null,
        description: description.value.trim(),
        time_limit_minutes:
            timeLimit.value === '' ? null : Number(timeLimit.value),
        question_count: Number(questionCount.value) || questions.value.length,
        shuffle_questions: toggle(
            'shuffle_questions',
            existingSettings.shuffle_questions,
        ),
        shuffle_options: toggle(
            'shuffle_options',
            existingSettings.shuffle_options,
        ),
        single_attempt: toggle(
            'single_attempt',
            existingSettings.single_attempt,
        ),
        results_visibility: resultsVisible
            ? existingSettings.results_visibility === 'hidden'
                ? 'score'
                : existingSettings.results_visibility
            : 'hidden',
        show_answers: toggle('show_answers', existingSettings.show_answers),
        motivational_message: toggle(
            'motivational_message',
            existingSettings.motivational_message,
        ),
        show_meaning: toggle('show_meaning', existingSettings.show_meaning),
        pass_mark: passMark === '' ? null : Number(passMark),
    });
}

defineExpose({ save });
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
        <h2 class="font-heading text-brand-800 text-base font-semibold">
            {{ $t('Create / Edit Test') }}
        </h2>

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
                    @click="addQuestion"
                >
                    <CirclePlus class="size-4" aria-hidden="true" />
                    {{ $t('Add Question') }}
                </Button>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
            <button
                v-for="kind in editor.kinds"
                :key="kind.value"
                type="button"
                :class="
                    cn(
                        'flex flex-col items-center justify-center gap-1.5 rounded-md border px-2 py-3 text-center text-[11px] font-semibold transition-colors duration-150',
                        activeKind === kind.value
                            ? 'border-brand-600 bg-brand-50 text-brand-700'
                            : 'border-line text-ink-slate hover:bg-brand-50/60',
                    )
                "
                @click="activeKind = kind.value"
            >
                <component
                    :is="kindIcons[kind.value]"
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
                    v-if="questions.some((q) => q.audio)"
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

        <div
            v-for="question in questions"
            :key="question.id"
            class="border-line bg-surface mt-3 rounded-md border p-3"
        >
            <div class="flex items-center gap-2">
                <GripVertical
                    class="text-ink-faint size-4 shrink-0"
                    aria-hidden="true"
                />
                <span
                    class="text-brand-900 min-w-0 flex-1 truncate text-[13px] font-semibold"
                >
                    {{ $t('Question :number', { number: question.index }) }}
                </span>
                <button
                    type="button"
                    class="text-ink-faint hover:bg-brand-50 inline-flex size-7 items-center justify-center rounded-md"
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
                    class="text-danger-text hover:bg-danger-tint inline-flex size-7 items-center justify-center rounded-md"
                    :aria-label="
                        $t('Delete question :number', {
                            number: question.index,
                        })
                    "
                    @click="removeQuestion(question)"
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

            <div class="mt-2.5 max-w-[240px]">
                <Select
                    :model-value="questionKinds[question.id] ?? question.kind"
                    @update:model-value="onQuestionKind(question.id, $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="kind in editor.questionKinds ?? editor.kinds"
                            :key="kind.value"
                            :value="kind.value"
                            class="text-[13px]"
                        >
                            {{ kind.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="tests-question-layout mt-3 grid gap-3">
                <div class="grid content-start gap-1.5">
                    <label
                        class="text-brand-900 text-[12px] font-semibold"
                        :for="`question-text-${question.id}`"
                    >
                        {{ $t('Question Text') }}
                        <span class="text-danger">*</span>
                    </label>
                    <textarea
                        :id="`question-text-${question.id}`"
                        rows="3"
                        class="border-line text-ink bg-surface min-h-[74px] w-full resize-none rounded-md border px-3 py-2 text-[13px] leading-[1.5] outline-none"
                        :value="
                            questionDrafts[question.id]?.text ?? question.text
                        "
                        @input="
                            setQuestionText(
                                question.id,
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
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
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-8 flex-1 gap-1.5 rounded-md px-2 text-[11.5px] font-semibold shadow-none"
                            @click="imagePickerFor = question"
                        >
                            <Image class="size-3.5" aria-hidden="true" />
                            {{ $t('Change Image') }}
                        </Button>
                        <button
                            type="button"
                            class="border-line text-danger-text hover:bg-danger-tint inline-flex size-8 shrink-0 items-center justify-center rounded-md border disabled:opacity-40"
                            :aria-label="$t('Remove image')"
                            :disabled="!question.media.image"
                            @click="emit('attach-image', question, null)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- What the option editor cannot show: scripts, lines, model answers. -->
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

            <div
                v-if="
                    textOnlyKinds.includes(
                        questionKinds[question.id] ?? question.kind,
                    )
                "
                class="mt-2 flex justify-end"
            >
                <Button
                    type="button"
                    variant="ghost"
                    class="text-brand-700 hover:bg-brand-50 h-8 px-2 text-[11.5px] font-semibold"
                    @click="saveQuestion(question)"
                >
                    {{ $t('Save question') }}
                </Button>
            </div>

            <div v-else class="mt-3 grid gap-2">
                <span class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Options') }} <span class="text-danger">*</span>
                </span>

                <div
                    v-for="option in questionOptions(question)"
                    :key="option.id"
                    class="flex items-center gap-2"
                >
                    <span
                        :class="
                            cn(
                                'grid size-4 shrink-0 place-items-center rounded-full border-2',
                                option.correct
                                    ? 'border-brand-600'
                                    : 'border-line-strong',
                            )
                        "
                        role="button"
                        tabindex="0"
                        @click="markCorrect(question, option.id)"
                    >
                        <span
                            v-if="option.correct"
                            class="bg-brand-600 size-2 rounded-full"
                        />
                    </span>
                    <span
                        class="text-ink-slate w-4 shrink-0 text-[12px] font-semibold"
                    >
                        {{ option.id }}
                    </span>
                    <Input
                        :model-value="option.text"
                        @update:model-value="
                            setOptionText(question, option.id, String($event))
                        "
                        class="border-line text-ink bg-surface h-9 min-w-0 flex-1 rounded-md px-3 text-[12.5px] shadow-none"
                    />
                    <span
                        v-if="option.correct"
                        class="bg-success-tint text-success-text inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-[11px] font-semibold whitespace-nowrap"
                    >
                        <Check class="size-3.5" aria-hidden="true" />
                        {{ $t('Correct answer') }}
                    </span>
                    <button
                        type="button"
                        class="text-ink-faint hover:bg-brand-50 inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                        :aria-label="
                            $t('Delete option :option', { option: option.id })
                        "
                        @click="removeOption(question, option.id)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="addOption(question)"
                    >
                        <CirclePlus class="size-4" aria-hidden="true" />
                        {{ $t('Add Option') }}
                    </Button>
                </div>

                <div class="mt-2 flex justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-brand-700 hover:bg-brand-50 h-8 px-2 text-[11.5px] font-semibold"
                        @click="saveQuestion(question)"
                    >
                        {{ $t('Save question') }}
                    </Button>
                </div>
            </div>
        </div>

        <TestsAiGenerateDialog
            v-if="editor.ai"
            v-model:open="aiDialogOpen"
            :ai="editor.ai"
            :test-title="editor.title"
            @generate="generate"
        />
        <LessonsMediaPicker
            v-model:open="imagePickerOpen"
            :tabs="libraryTabs"
            :categories="libraryCategories"
            @choose="chooseImage"
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
