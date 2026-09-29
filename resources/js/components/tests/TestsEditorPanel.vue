<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
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
import { computed, nextTick, reactive, ref, watch } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import FieldMeaningButton from '@/components/translations/FieldMeaningButton.vue';
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
    TestQuestionDraft,
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
    'attach-media': [
        question: TestEditorQuestion,
        kind: 'image' | 'audio' | 'video',
        mediaId: number | null,
    ];
}>();

const aiDialogOpen = ref(false);
const mediaPickerTarget = ref<{
    question: TestEditorQuestion;
    kind: 'image' | 'audio' | 'video';
    optionId?: string;
} | null>(null);
const imagePickerOpen = computed({
    get: () => mediaPickerTarget.value !== null,
    set: (open: boolean) => {
        if (!open) mediaPickerTarget.value = null;
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

/** Kinds whose answer is text or a recording rather than a choice. */
const textOnlyKinds: TestQuestionKind[] = [
    'short_answer',
    'speaking',
    'writing',
    'fill_blank',
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

function chooseImage(media: {
    id: string;
    label: string;
    url?: string;
    thumbUrl?: string;
    alt?: string;
}): void {
    const target = mediaPickerTarget.value;

    if (target?.optionId) {
        const option = questionDrafts[target.question.id]?.options.find(
            (item) => item.id === target.optionId,
        );

        if (option) {
            const reference = {
                id: media.id,
                label: media.label,
                url: media.url ?? '',
                thumbUrl: media.thumbUrl ?? media.url ?? '',
                alt: media.alt ?? '',
            };

            if (target.kind === 'audio') {
                option.audioId = Number(media.id);
                option.audio = reference;
            } else if (target.kind === 'image') {
                option.imageId = Number(media.id);
                option.image = reference;
            }
            saveQuestion(target.question);
        }
    } else if (target) {
        emit('attach-media', target.question, target.kind, Number(media.id));
    }
    mediaPickerTarget.value = null;
}

function openMediaPicker(
    question: TestEditorQuestion,
    kind: 'image' | 'audio' | 'video',
    optionId?: string,
): void {
    mediaPickerTarget.value = { question, kind, optionId };
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
const questionDrafts = reactive<Record<number, TestQuestionDraft>>({});

function syncEditor(editor: TestEditor): void {
    type.value = editor.type;
    department.value = editor.department;
    title.value = editor.title;
    timeLimit.value = editor.timeLimit;
    questionCount.value = editor.questionCount;
    description.value = editor.description;
    // activeKind is the admin's pick for the next "Add Question": it is not
    // reset here, or every add and every poll would snap it back to the
    // server's default (multiple choice).
    const previousIds = new Set(questions.value.map((question) => question.id));
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
            pairs: question.pairs.map((pair) => ({ ...pair })),
            acceptedAnswers: [...question.acceptedAnswers],
            audioText: question.audioText,
            requestText: question.requestText,
            information: [...question.information],
            speakingSeconds: question.speakingSeconds,
            media: {
                image: question.media.image
                    ? Number(question.media.image.id)
                    : null,
                audio: question.media.audio
                    ? Number(question.media.audio.id)
                    : null,
                video: question.media.video
                    ? Number(question.media.video.id)
                    : null,
            },
        };
    });

    // After "Add Question" or "Duplicate", bring the new card into view and
    // put the cursor in its text: it is appended at the end of a long list,
    // and without this the add looked like nothing happened.
    const added = editor.questions.find(
        (question) => !previousIds.has(question.id),
    );

    if (awaitingNewQuestion.value && added !== undefined) {
        awaitingNewQuestion.value = false;
        revealQuestion(added.id);
    }
}

const awaitingNewQuestion = ref(false);
const highlightedQuestion = ref<number | null>(null);
let highlightTimer: ReturnType<typeof setTimeout> | null = null;

function revealQuestion(id: number): void {
    highlightedQuestion.value = id;

    if (highlightTimer !== null) {
        clearTimeout(highlightTimer);
    }

    highlightTimer = setTimeout(() => {
        highlightedQuestion.value = null;
    }, 2400);

    void nextTick(() => {
        const card = document.getElementById(`question-card-${id}`);
        const text = document.getElementById(
            `question-text-${id}`,
        ) as HTMLTextAreaElement | null;
        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        card?.scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'center',
        });
        text?.focus({ preventScroll: true });
        text?.select();
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
    writing: PenLine,
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

    const kind = value as TestQuestionKind;
    const wasTrueFalse = questionDrafts[id].kind === 'true_false';

    questionKinds[id] = kind;
    questionDrafts[id].kind = kind;

    // True / False always answers with exactly "True" and "False".
    if (kind === 'true_false') {
        questionDrafts[id].options = trueFalseOptions();
    } else if (wasTrueFalse) {
        questionDrafts[id].options = starterOptions();
    } else if (
        !textOnlyKinds.includes(kind) &&
        kind !== 'matching' &&
        questionDrafts[id].options.length === 0
    ) {
        questionDrafts[id].options = starterOptions();
    } else if (textOnlyKinds.includes(kind) || kind === 'matching') {
        questionDrafts[id].options = [];
    }

    if (kind === 'matching' && questionDrafts[id].pairs.length === 0) {
        questionDrafts[id].pairs = [{ left: '', right: '' }];
    }
}

function trueFalseOptions(): TestQuestionOption[] {
    return [
        { id: 'A', text: 'True', correct: true },
        { id: 'B', text: 'False', correct: false },
    ];
}

function starterOptions(): TestQuestionOption[] {
    return [
        { id: 'A', text: 'Option A', correct: true },
        { id: 'B', text: 'Option B', correct: false },
    ];
}

function setQuestionText(id: number, value: string): void {
    if (questionDrafts[id]) {
        questionDrafts[id].text = value;
    }
}

function questionPayload(question: TestEditorQuestion): TestQuestionPayload {
    const draft = questionDrafts[question.id];
    const sourceOptions = draft?.options ?? question.options;

    return {
        kind: draft?.kind ?? question.kind,
        text: draft?.text ?? question.text,
        options: sourceOptions.map((option) => ({
            id: option.id,
            text: option.text,
            correct: option.correct,
            image_id:
                option.imageId ??
                (option.image ? Number(option.image.id) : null),
            audio_id:
                option.audioId ??
                (option.audio ? Number(option.audio.id) : null),
            audio_text: option.audioText ?? null,
        })),
        pairs: (draft?.pairs ?? question.pairs).map((pair) => ({ ...pair })),
        accepted_answers: [
            ...(draft?.acceptedAnswers ?? question.acceptedAnswers),
        ],
        audio_text: draft?.audioText ?? question.audioText,
        request_text: draft?.requestText ?? question.requestText,
        information: [...(draft?.information ?? question.information)],
        speaking_seconds: draft?.speakingSeconds ?? question.speakingSeconds,
        media: draft?.media ?? {
            image: question.media.image
                ? Number(question.media.image.id)
                : null,
            audio: question.media.audio
                ? Number(question.media.audio.id)
                : null,
            video: question.media.video
                ? Number(question.media.video.id)
                : null,
        },
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

function setOptionAudioText(
    question: TestEditorQuestion,
    optionId: string,
    value: string,
): void {
    const option = questionOptions(question).find(
        (item) => item.id === optionId,
    );
    if (option) option.audioText = value;
}

function setAcceptedAnswers(question: TestEditorQuestion, value: string): void {
    questionDrafts[question.id].acceptedAnswers = value
        .split(/[,\n]/u)
        .map((answer) => answer.trim())
        .filter((answer) => answer !== '');
}

function setPairText(
    question: TestEditorQuestion,
    index: number,
    side: 'left' | 'right',
    value: string,
): void {
    const pair = questionDrafts[question.id]?.pairs[index];
    if (pair) pair[side] = value;
}

function questionKind(question: TestEditorQuestion): TestQuestionKind {
    return questionKinds[question.id] ?? question.kind;
}

function removeQuestionMedia(
    question: TestEditorQuestion,
    kind: 'image' | 'audio' | 'video',
): void {
    emit('attach-media', question, kind, null);
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

function addPair(question: TestEditorQuestion): void {
    questionDrafts[question.id].pairs.push({ left: '', right: '' });
}

function removePair(question: TestEditorQuestion, index: number): void {
    questionDrafts[question.id].pairs.splice(index, 1);
}

function moveOrderingOption(
    question: TestEditorQuestion,
    optionId: string,
    delta: -1 | 1,
): void {
    const options = questionOptions(question);
    const index = options.findIndex((option) => option.id === optionId);
    const next = index + delta;

    if (index < 0 || next < 0 || next >= options.length) return;

    options.splice(next, 0, options.splice(index, 1)[0]);
}

/** A new question of the kind chosen in the tiles, with starter answers. */
function addQuestion(): void {
    const kind = activeKind.value;
    let options: TestQuestionOption[] = starterOptions();

    if (kind === 'true_false') {
        options = trueFalseOptions();
    } else if (textOnlyKinds.includes(kind) || kind === 'matching') {
        options = [];
    }

    awaitingNewQuestion.value = true;
    emit('add-question', {
        kind,
        text: 'Write the question prompt here.',
        options,
        pairs: kind === 'matching' ? [{ left: '', right: '' }] : [],
        accepted_answers: [],
        audio_text: '',
        request_text: '',
        information: [],
        speaking_seconds: 30,
    });
}

function duplicateQuestion(question: TestEditorQuestion): void {
    awaitingNewQuestion.value = true;
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
                    data-test="add-question-button"
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
            :id="`question-card-${question.id}`"
            :key="question.id"
            :class="
                cn(
                    'bg-surface mt-3 scroll-mt-24 rounded-md border p-3 transition-[border-color,box-shadow] duration-300 motion-reduce:transition-none',
                    highlightedQuestion === question.id
                        ? 'border-brand-600 ring-brand-600/15 ring-3'
                        : 'border-line',
                )
            "
            :data-test="`test-question-${question.index}`"
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
                    <div class="flex flex-wrap items-center gap-2">
                        <label
                            class="text-brand-900 text-[12px] font-semibold"
                            :for="`question-text-${question.id}`"
                        >
                            {{ $t('Question Text') }}
                            <span class="text-danger">*</span>
                        </label>
                        <FieldMeaningButton
                            :text="
                                questionDrafts[question.id]?.text ??
                                question.text
                            "
                        />
                    </div>
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
                    <LessonsField
                        v-if="questionKind(question) === 'audio'"
                        v-model="questionDrafts[question.id].audioText"
                        :label="$t('Listening script for stored audio')"
                        :hint="
                            $t(
                                'Used to generate stored pronunciation when no uploaded audio is attached.',
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
                    <audio
                        v-if="question.media.audio"
                        controls
                        preload="none"
                        class="w-full"
                        :src="question.media.audio.url"
                    />
                    <video
                        v-if="question.media.video"
                        controls
                        preload="metadata"
                        class="max-h-32 w-full rounded-md"
                        :src="question.media.video.url"
                    />
                    <div class="grid grid-cols-3 gap-1.5">
                        <Button
                            v-for="kind in ['image', 'audio', 'video'] as const"
                            :key="kind"
                            type="button"
                            variant="outline"
                            class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-1.5 text-[10.5px] font-semibold shadow-none"
                            @click="openMediaPicker(question, kind)"
                        >
                            <Image
                                v-if="kind === 'image'"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <Volume2
                                v-else-if="kind === 'audio'"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <CirclePlay
                                v-else
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{
                                kind === 'image'
                                    ? $t('Image')
                                    : kind === 'audio'
                                      ? $t('Audio')
                                      : $t('Video')
                            }}
                        </Button>
                    </div>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="kind in ['image', 'audio', 'video'] as const"
                            :key="`remove-${kind}`"
                            type="button"
                            :disabled="!question.media[kind]"
                            class="text-ink-muted hover:bg-danger-tint hover:text-danger-text min-h-7 rounded-md text-[10.5px] disabled:opacity-40"
                            @click="removeQuestionMedia(question, kind)"
                        >
                            {{ $t('Remove :kind', { kind: $t(kind) }) }}
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
                v-if="questionKind(question) === 'matching'"
                class="border-line bg-app/60 mt-3 grid gap-2 rounded-md border p-3"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-brand-900 text-[12px] font-semibold">{{
                        $t('Matching pairs')
                    }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 h-8 gap-1 px-2 text-[11px]"
                        @click="addPair(question)"
                    >
                        <CirclePlus class="size-3.5" aria-hidden="true" />
                        {{ $t('Add Pair') }}
                    </Button>
                </div>
                <div
                    v-for="(pair, index) in questionDrafts[question.id]
                        ?.pairs ?? question.pairs"
                    :key="index"
                    class="grid items-center gap-2 sm:grid-cols-[minmax(0,1fr)_24px_minmax(0,1fr)_32px]"
                >
                    <Input
                        :model-value="pair.left"
                        :placeholder="$t('Word or phrase')"
                        class="border-line text-ink h-9 text-[12px]"
                        @update:model-value="
                            setPairText(question, index, 'left', String($event))
                        "
                    />
                    <span class="text-ink-faint text-center">↔</span>
                    <Input
                        :model-value="pair.right"
                        :placeholder="$t('Matching item')"
                        class="border-line text-ink h-9 text-[12px]"
                        @update:model-value="
                            setPairText(
                                question,
                                index,
                                'right',
                                String($event),
                            )
                        "
                    />
                    <button
                        type="button"
                        class="text-ink-faint hover:bg-danger-tint hover:text-danger-text grid size-8 place-items-center rounded-md"
                        :aria-label="$t('Remove pair')"
                        @click="removePair(question, index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    class="ms-auto h-8 px-2 text-[11.5px]"
                    @click="saveQuestion(question)"
                    >{{ $t('Save question') }}</Button
                >
            </div>

            <div
                v-else-if="
                    questionKind(question) === 'short_answer' ||
                    questionKind(question) === 'fill_blank'
                "
                class="mt-3 grid gap-2"
            >
                <p
                    v-if="questionKind(question) === 'fill_blank'"
                    class="text-ink-slate text-[11.5px]"
                >
                    {{
                        $t(
                            'Put ___ where the learner should type the missing word.',
                        )
                    }}
                </p>
                <label
                    class="text-brand-900 text-[12px] font-semibold"
                    :for="`accepted-${question.id}`"
                    >{{ $t('Accepted answer(s)') }}
                    <span class="text-danger">*</span></label
                >
                <textarea
                    :id="`accepted-${question.id}`"
                    rows="2"
                    :value="
                        (
                            questionDrafts[question.id]?.acceptedAnswers ??
                            question.acceptedAnswers
                        ).join('\n')
                    "
                    :placeholder="$t('One accepted answer per line')"
                    class="border-line text-ink bg-surface min-h-[58px] w-full resize-y rounded-md border px-3 py-2 text-[12.5px]"
                    @input="
                        setAcceptedAnswers(
                            question,
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
                <Button
                    type="button"
                    variant="ghost"
                    class="ms-auto h-8 px-2 text-[11.5px]"
                    @click="saveQuestion(question)"
                    >{{ $t('Save question') }}</Button
                >
            </div>

            <div
                v-else-if="questionKind(question) === 'writing'"
                class="mt-3 grid gap-3"
            >
                <LessonsField
                    v-model="questionDrafts[question.id].requestText"
                    :label="$t('Guest email or message')"
                    type="textarea"
                    :rows="4"
                />
                <LessonsField
                    :model-value="
                        questionDrafts[question.id].information.join('\n')
                    "
                    :label="$t('Information the employee should use')"
                    type="textarea"
                    :rows="3"
                    @update:model-value="
                        questionDrafts[question.id].information = String($event)
                            .split('\n')
                            .map((line) => line.trim())
                            .filter(Boolean)
                    "
                />
                <p class="text-ink-slate text-[11.5px]">
                    {{
                        $t(
                            'The employee response is stored verbatim and sent for AI evaluation after submission.',
                        )
                    }}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="ms-auto h-8 px-2 text-[11.5px]"
                    @click="saveQuestion(question)"
                    >{{ $t('Save question') }}</Button
                >
            </div>

            <div
                v-else-if="questionKind(question) === 'speaking'"
                class="mt-3 grid gap-2 sm:grid-cols-2"
            >
                <LessonsField
                    v-model="questionDrafts[question.id].speakingSeconds"
                    :label="$t('Recording time (seconds)')"
                    type="number"
                    :min="5"
                    :max="180"
                />
                <p class="text-ink-slate self-end pb-2 text-[11.5px]">
                    {{
                        $t(
                            'Employees record an answer, then receive the configured pronunciation and speaking assessment.',
                        )
                    }}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    class="ms-auto h-8 px-2 text-[11.5px] sm:col-span-2"
                    @click="saveQuestion(question)"
                    >{{ $t('Save question') }}</Button
                >
            </div>

            <div v-else class="mt-3 grid gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-brand-900 text-[12px] font-semibold"
                        >{{
                            questionKind(question) === 'ordering'
                                ? $t('Items in the correct sequence')
                                : $t('Answer options')
                        }}
                        <span
                            v-if="questionKind(question) !== 'ordering'"
                            class="text-danger"
                            >*</span
                        ></span
                    >
                    <p
                        v-if="questionKind(question) === 'ordering'"
                        class="text-ink-slate text-[11px]"
                    >
                        {{
                            $t(
                                'Reorder these rows to set the correct sequence.',
                            )
                        }}
                    </p>
                </div>

                <div
                    v-for="(option, optionIndex) in questionOptions(question)"
                    :key="option.id"
                    class="border-line grid gap-2 rounded-md border p-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <button
                            v-if="questionKind(question) === 'ordering'"
                            type="button"
                            :disabled="optionIndex === 0"
                            class="text-ink-muted grid size-7 shrink-0 place-items-center rounded-md disabled:opacity-30"
                            :aria-label="$t('Move up')"
                            @click="moveOrderingOption(question, option.id, -1)"
                        >
                            <ArrowUp class="size-4" aria-hidden="true" />
                        </button>
                        <span
                            v-else
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
                            :aria-label="
                                $t('Mark :option as correct', {
                                    option: option.id,
                                })
                            "
                            @click="markCorrect(question, option.id)"
                            @keydown.enter.prevent="
                                markCorrect(question, option.id)
                            "
                            ><span
                                v-if="option.correct"
                                class="bg-brand-600 size-2 rounded-full"
                        /></span>
                        <span
                            class="text-ink-slate w-5 shrink-0 text-[12px] font-semibold"
                            >{{ option.id }}</span
                        >
                        <div class="relative min-w-0 flex-1">
                            <Input
                                :model-value="option.text"
                                @update:model-value="
                                    setOptionText(
                                        question,
                                        option.id,
                                        String($event),
                                    )
                                "
                                class="border-line text-ink bg-surface h-9 w-full rounded-md ps-3 pe-12 text-[12.5px] shadow-none"
                            />
                            <FieldMeaningButton
                                compact
                                :text="option.text"
                                class="absolute end-1 top-1/2 -translate-y-1/2"
                            />
                        </div>
                        <button
                            type="button"
                            class="border-line text-brand-700 hover:bg-brand-50 grid size-8 shrink-0 place-items-center rounded-md border"
                            :aria-label="
                                $t('Add image to option :option', {
                                    option: option.id,
                                })
                            "
                            @click="
                                openMediaPicker(question, 'image', option.id)
                            "
                        >
                            <Image class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="border-line text-brand-700 hover:bg-brand-50 grid size-8 shrink-0 place-items-center rounded-md border"
                            :aria-label="
                                $t('Add audio to option :option', {
                                    option: option.id,
                                })
                            "
                            @click="
                                openMediaPicker(question, 'audio', option.id)
                            "
                        >
                            <Volume2 class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            v-if="questionKind(question) === 'ordering'"
                            type="button"
                            :disabled="
                                optionIndex ===
                                questionOptions(question).length - 1
                            "
                            class="text-ink-muted grid size-7 shrink-0 place-items-center rounded-md disabled:opacity-30"
                            :aria-label="$t('Move down')"
                            @click="moveOrderingOption(question, option.id, 1)"
                        >
                            <ArrowDown class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="flex items-center gap-2 sm:justify-end">
                        <span
                            v-if="
                                option.correct &&
                                questionKind(question) !== 'ordering'
                            "
                            class="bg-success-tint text-success-text inline-flex items-center gap-1 rounded-md px-2 py-1 text-[10.5px] font-semibold whitespace-nowrap"
                            ><Check class="size-3.5" aria-hidden="true" />{{
                                $t('Correct answer')
                            }}</span
                        >
                        <Input
                            :model-value="option.audioText ?? ''"
                            :placeholder="$t('Audio pronunciation (optional)')"
                            class="border-line text-ink h-8 min-w-0 text-[11px] sm:w-48"
                            @update:model-value="
                                setOptionAudioText(
                                    question,
                                    option.id,
                                    String($event),
                                )
                            "
                        />
                        <button
                            type="button"
                            class="text-ink-faint hover:bg-brand-50 grid size-7 shrink-0 place-items-center rounded-md"
                            :aria-label="
                                $t('Delete option :option', {
                                    option: option.id,
                                })
                            "
                            @click="removeOption(question, option.id)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <img
                        v-if="option.image"
                        :src="option.image.thumbUrl || option.image.url"
                        :alt="option.image.alt || option.image.label"
                        class="border-line ms-7 max-h-20 rounded-md border object-cover sm:col-span-2"
                    />
                    <audio
                        v-if="option.audio"
                        controls
                        preload="none"
                        :src="option.audio.url"
                        class="ms-7 max-h-8 max-w-full sm:col-span-2"
                    />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="addOption(question)"
                        ><CirclePlus class="size-4" aria-hidden="true" />{{
                            questionKind(question) === 'ordering'
                                ? $t('Add item')
                                : $t('Add Option')
                        }}</Button
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-8 px-2 text-[11.5px] font-semibold"
                        @click="saveQuestion(question)"
                        >{{ $t('Save question') }}</Button
                    >
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
            :kind="mediaPickerTarget?.kind ?? 'image'"
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
