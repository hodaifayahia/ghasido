<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import {
    CirclePlus,
    Image,
    LoaderCircle,
    Trash2,
    Video,
    Volume2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type {
    LessonBlockRow,
    LessonLibraryImage,
    LessonsImageLibrary,
} from '@/types';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaPicker from '@/components/lessons/LessonsMediaPicker.vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { store as storeActivity } from '@/routes/activities';

/** Manual lesson activity authoring (PRAC-01, PRAC-05, PRAC-06, WRITE-05). */
type ActivityKind =
    | 'multiple_choice'
    | 'ordering'
    | 'matching'
    | 'short_answer'
    | 'audio'
    | 'image'
    | 'video'
    | 'speaking'
    | 'fill_blank'
    | 'writing';
type Pair = { left: string; right: string };

const props = defineProps<{
    block: LessonBlockRow;
    library: LessonsImageLibrary;
}>();

const open = defineModel<boolean>('open', { required: true });

const kinds: { value: ActivityKind; label: string }[] = [
    { value: 'multiple_choice', label: 'Multiple Choice' },
    { value: 'ordering', label: 'Ordering' },
    { value: 'matching', label: 'Matching' },
    { value: 'short_answer', label: 'Short Answer' },
    { value: 'audio', label: 'Audio Question' },
    { value: 'image', label: 'Image Question' },
    { value: 'video', label: 'Video Question' },
    { value: 'speaking', label: 'Speaking' },
    { value: 'fill_blank', label: 'Fill in the Blank' },
    { value: 'writing', label: 'Writing Activity' },
];

const kind = ref<ActivityKind>('multiple_choice');
const title = ref('');
const prompt = ref('');
const question = ref('');
const optionText = ref('Option A\nOption B');
const correctOption = ref(0);
const acceptedAnswersText = ref('');
const requestText = ref('');
const informationText = ref('');
const audioText = ref('');
const speakingSeconds = ref(30);
const attemptsAllowed = ref(0);
const showMeaning = ref(true);
const pairs = ref<Pair[]>([{ left: '', right: '' }]);
const optionImages = ref<Record<number, number | null>>({});
const optionAudios = ref<Record<number, number | null>>({});
const optionAudioTexts = ref<Record<number, string>>({});
const questionMedia = ref<{
    image: number | null;
    audio: number | null;
    video: number | null;
}>({ image: null, audio: null, video: null });
const mediaNames = ref<Record<string, string>>({});
const pickerTarget = ref<{
    kind: 'image' | 'audio' | 'video';
    optionIndex: number | null;
} | null>(null);
const pickerOpen = computed({
    get: () => pickerTarget.value !== null,
    set: (value: boolean) => {
        if (!value) pickerTarget.value = null;
    },
});
const saving = ref(false);
const error = ref('');

const optionLines = computed(() =>
    optionText.value
        .split(/\r?\n/u)
        .map((line) => line.trim())
        .filter(Boolean),
);
const acceptedAnswers = computed(() =>
    acceptedAnswersText.value
        .split(/[,\n]/u)
        .map((line) => line.trim())
        .filter(Boolean),
);
const information = computed(() =>
    informationText.value
        .split(/\r?\n/u)
        .map((line) => line.trim())
        .filter(Boolean),
);
const pairRows = computed(() => pairs.value);
const mediaTabs = computed(() => props.library.tabs);
const mediaCategories = computed(() => props.library.categories);

watch(open, (visible) => {
    if (visible) {
        kind.value = 'multiple_choice';
        title.value = '';
        prompt.value = '';
        question.value = '';
        optionText.value = 'Option A\nOption B';
        correctOption.value = 0;
        acceptedAnswersText.value = '';
        requestText.value = '';
        informationText.value = '';
        audioText.value = '';
        speakingSeconds.value = 30;
        attemptsAllowed.value = 0;
        showMeaning.value = true;
        pairs.value = [{ left: '', right: '' }];
        optionImages.value = {};
        optionAudios.value = {};
        optionAudioTexts.value = {};
        questionMedia.value = { image: null, audio: null, video: null };
        mediaNames.value = {};
        error.value = '';
    }
});

function openPicker(
    mediaKind: 'image' | 'audio' | 'video',
    optionIndex: number | null = null,
): void {
    pickerTarget.value = { kind: mediaKind, optionIndex };
}

function chooseMedia(asset: LessonLibraryImage): void {
    const target = pickerTarget.value;
    if (!target) return;

    if (target.optionIndex !== null) {
        if (target.kind === 'image') {
            optionImages.value[target.optionIndex] = Number(asset.id);
        } else if (target.kind === 'audio') {
            optionAudios.value[target.optionIndex] = Number(asset.id);
        }
    } else {
        questionMedia.value[target.kind] = Number(asset.id);
    }
    mediaNames.value[`${target.kind}:${target.optionIndex ?? 'question'}`] =
        asset.label;
    pickerTarget.value = null;
}

function addPair(): void {
    pairs.value.push({ left: '', right: '' });
}

function removePair(index: number): void {
    pairs.value.splice(index, 1);
}

function item(): Record<string, unknown> {
    const media = questionMedia.value;
    const choices = optionLines.value.map((text, index) => ({
        id: `o${index + 1}`,
        text,
        label: text,
        image: optionImages.value[index] ?? null,
        audio: optionAudios.value[index] ?? null,
        audio_text: optionAudioTexts.value[index]?.trim() || undefined,
    }));
    const accepted = acceptedAnswers.value;

    switch (kind.value) {
        case 'matching': {
            const prompts = pairs.value.map((pair, index) => ({
                id: `p${index + 1}`,
                audio_text: pair.left.trim(),
            }));
            const targets = pairs.value.map((pair, index) => ({
                id: `t${index + 1}`,
                label: pair.right.trim(),
                image: null,
            }));
            const matches = Object.fromEntries(
                pairs.value.map((_, index) => [
                    `p${index + 1}`,
                    `t${index + 1}`,
                ]),
            );
            return { id: 'i1', prompts, targets, pairs: matches, ...media };
        }
        case 'ordering': {
            const ordered = optionLines.value.map((text, index) => ({
                id: `s${index + 1}`,
                text,
                image: optionImages.value[index] ?? null,
                audio: optionAudios.value[index] ?? null,
                audio_text: optionAudioTexts.value[index]?.trim() || undefined,
            }));
            return {
                id: 'i1',
                question: question.value.trim(),
                sentences: [...ordered].reverse(),
                order: ordered.map((line) => line.id),
                audio_text: audioText.value.trim() || undefined,
                ...media,
            };
        }
        case 'short_answer':
            return {
                id: 'i1',
                question: question.value.trim(),
                accepted_answers: accepted,
                ...media,
            };
        case 'fill_blank':
            return {
                id: 'i1',
                sentence: question.value.trim(),
                accepted_answers: accepted,
                options: [],
                ...media,
            };
        case 'audio':
            return {
                id: 'i1',
                audio_text: audioText.value.trim() || question.value.trim(),
                options: choices,
                correct: `o${correctOption.value + 1}`,
                ...media,
            };
        case 'image':
        case 'multiple_choice':
            return {
                id: 'i1',
                question: question.value.trim(),
                options: choices,
                correct: `o${correctOption.value + 1}`,
                ...media,
            };
        case 'video':
            return {
                id: 'i1',
                question: question.value.trim(),
                subtitle: prompt.value.trim(),
                options: choices,
                correct: `o${correctOption.value + 1}`,
                ...media,
            };
        case 'speaking':
            return {
                id: 'i1',
                question: question.value.trim(),
                situation: prompt.value.trim(),
                instruction: 'Record a short response.',
                max_seconds: speakingSeconds.value,
                ...media,
            };
        case 'writing':
            return {
                id: 'i1',
                scenario: question.value.trim(),
                request_text: requestText.value.trim(),
                information: information.value,
                min_words: 20,
                ...media,
            };
    }
}

function save(): void {
    if (saving.value) return;
    error.value = '';

    const hasQuestionMedia = Object.values(questionMedia.value).some(
        (mediaId) => mediaId !== null,
    );
    const hasQuestionContent = question.value.trim() !== '' || hasQuestionMedia;

    if (!hasQuestionContent && kind.value !== 'matching') {
        error.value = 'Add a question or scenario before saving.';
        return;
    }
    if (
        ['multiple_choice', 'audio', 'image', 'video'].includes(kind.value) &&
        optionLines.value.length < 2
    ) {
        error.value = 'Add at least two answer options.';
        return;
    }
    if (
        ['short_answer', 'fill_blank'].includes(kind.value) &&
        acceptedAnswers.value.length === 0
    ) {
        error.value = 'Add at least one accepted answer.';
        return;
    }
    if (
        kind.value === 'matching' &&
        pairs.value.some(
            (pair) => pair.left.trim() === '' || pair.right.trim() === '',
        )
    ) {
        error.value = 'Complete both sides of each pair.';
        return;
    }

    saving.value = true;
    const itemPayload = item();
    const activityType: Record<ActivityKind, string> = {
        multiple_choice: 'multiple_choice',
        ordering: 'dialogue_order',
        matching: 'listen_match',
        short_answer: 'short_answer',
        audio: 'listen_choose',
        image: 'multiple_choice',
        video: 'watch_respond',
        speaking: 'speaking',
        fill_blank: 'words_sentences',
        writing: 'writing',
    };
    const skillLabel: Record<ActivityKind, string> = {
        multiple_choice: 'Multiple Choice',
        ordering: 'Ordering',
        matching: 'Matching',
        short_answer: 'Short Answer',
        audio: 'Audio Question',
        image: 'Image Question',
        video: 'Video Question',
        speaking: 'Speaking',
        fill_blank: 'Fill in the Blank',
        writing: 'Writing Activity',
    };

    router.post(
        storeActivity.url(),
        {
            type: activityType[kind.value],
            title: title.value.trim() || skillLabel[kind.value],
            skill_label: skillLabel[kind.value],
            prompt:
                prompt.value.trim() ||
                question.value.trim() ||
                skillLabel[kind.value],
            payload: { items: [itemPayload] } as unknown as FormDataConvertible,
            block_id: props.block.id,
            attempts_allowed: attemptsAllowed.value,
            show_meaning_enabled: showMeaning.value,
            status: 'published',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onError: (bag) => {
                error.value =
                    Object.values(bag)[0] ?? 'The activity could not be saved.';
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
        :title="$t('Add Activity')"
        :description="
            $t(
                'Choose an exercise type and add the content employees will practise.',
            )
        "
        size="xl"
    >
        <div class="mt-2 grid gap-4">
            <label class="grid gap-1.5">
                <span class="text-brand-900 text-[12px] font-semibold">{{
                    $t('Exercise Type')
                }}</span>
                <select
                    v-model="kind"
                    class="border-line text-ink bg-surface h-10 rounded-md border px-3 text-[13px]"
                >
                    <option
                        v-for="item in kinds"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ $t(item.label) }}
                    </option>
                </select>
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <LessonsField v-model="title" :label="$t('Activity title')" />
                <LessonsField
                    v-model="prompt"
                    :label="$t('Instructions')"
                    :placeholder="$t('Short instructions for the learner')"
                />
            </div>
            <div class="grid items-end gap-3 sm:grid-cols-2">
                <LessonsField
                    v-model="attemptsAllowed"
                    :label="$t('Attempts allowed (0 = unlimited)')"
                    type="number"
                    :min="0"
                    :max="99"
                />
                <label
                    class="text-brand-900 flex min-h-10 items-center gap-2 text-[12px] font-medium"
                >
                    <Checkbox v-model:checked="showMeaning" />
                    {{ $t('Enable Show Meaning') }}
                </label>
            </div>
            <LessonsField
                v-model="question"
                :label="
                    kind === 'writing'
                        ? $t('Scenario')
                        : $t('Question or sentence')
                "
                type="textarea"
                :rows="3"
                :placeholder="
                    kind === 'fill_blank'
                        ? $t('Use ___ for the missing word')
                        : ''
                "
            />

            <div v-if="kind === 'matching'" class="grid gap-2">
                <div class="flex items-center justify-between">
                    <span class="text-brand-900 text-[12px] font-semibold">{{
                        $t('Matching pairs')
                    }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-8 gap-1 px-2.5 text-[11px]"
                        @click="addPair"
                        ><CirclePlus class="size-3.5" aria-hidden="true" />{{
                            $t('Add Pair')
                        }}</Button
                    >
                </div>
                <div
                    v-for="(pair, index) in pairRows"
                    :key="index"
                    class="grid gap-2 sm:grid-cols-[1fr_1fr_32px]"
                >
                    <Input
                        v-model="pair.left"
                        :placeholder="$t('Word or phrase')"
                        class="border-line h-9 text-[12px]"
                    />
                    <Input
                        v-model="pair.right"
                        :placeholder="$t('Matching item')"
                        class="border-line h-9 text-[12px]"
                    />
                    <button
                        type="button"
                        class="text-ink-muted hover:bg-danger-tint grid size-8 place-items-center rounded-md"
                        :aria-label="$t('Remove pair')"
                        @click="removePair(index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div
                v-else-if="['short_answer', 'fill_blank'].includes(kind)"
                class="grid gap-1.5"
            >
                <LessonsField
                    v-model="acceptedAnswersText"
                    :label="$t('Accepted answer(s)')"
                    type="textarea"
                    :rows="2"
                    :hint="
                        $t(
                            'Separate accepted answers with a comma or put each answer on a new line.',
                        )
                    "
                />
            </div>

            <template v-else-if="kind === 'writing'">
                <LessonsField
                    v-model="requestText"
                    :label="$t('Guest email or message')"
                    type="textarea"
                    :rows="4"
                />
                <LessonsField
                    v-model="informationText"
                    :label="$t('Information to include')"
                    type="textarea"
                    :rows="3"
                    :hint="$t('Put each detail on a separate line.')"
                />
            </template>

            <div v-else-if="kind === 'speaking'" class="max-w-56">
                <LessonsField
                    v-model="speakingSeconds"
                    :label="$t('Recording time (seconds)')"
                    type="number"
                    :min="5"
                    :max="180"
                />
            </div>

            <template
                v-if="
                    [
                        'multiple_choice',
                        'audio',
                        'image',
                        'video',
                        'ordering',
                    ].includes(kind)
                "
            >
                <div class="grid gap-2">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span
                            class="text-brand-900 text-[12px] font-semibold"
                            >{{
                                kind === 'ordering'
                                    ? $t('Items in the correct sequence')
                                    : $t('Answer options')
                            }}</span
                        >
                        <label
                            v-if="kind !== 'ordering'"
                            class="text-ink-slate flex items-center gap-2 text-[11.5px]"
                        >
                            {{ $t('Correct answer') }}
                            <select
                                v-model.number="correctOption"
                                class="border-line bg-surface h-8 rounded-md border px-2"
                            >
                                <option
                                    v-for="(option, index) in optionLines"
                                    :key="index"
                                    :value="index"
                                >
                                    {{
                                        $t('Option :letter', {
                                            letter: String.fromCharCode(
                                                65 + index,
                                            ),
                                        })
                                    }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <textarea
                        v-model="optionText"
                        rows="4"
                        class="border-line text-ink bg-surface w-full rounded-md border px-3 py-2 text-[12.5px]"
                        :placeholder="
                            $t(
                                'One option per line. For ordering, enter lines in the correct order.',
                            )
                        "
                    />
                    <div class="grid gap-2">
                        <div
                            v-for="(line, index) in optionLines"
                            :key="`${line}-${index}`"
                            class="flex items-center gap-2"
                        >
                            <span class="text-ink-slate w-7 text-[11px]">{{
                                String.fromCharCode(65 + index)
                            }}</span>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-8 gap-1.5 px-2 text-[10.5px]"
                                @click="openPicker('image', index)"
                                ><Image class="size-3.5" aria-hidden="true" />{{
                                    mediaNames[`image:${index}`] ||
                                    $t('Option image')
                                }}</Button
                            >
                            <Button
                                type="button"
                                variant="outline"
                                class="h-8 gap-1.5 px-2 text-[10.5px]"
                                @click="openPicker('audio', index)"
                                ><Volume2
                                    class="size-3.5"
                                    aria-hidden="true"
                                />{{
                                    mediaNames[`audio:${index}`] ||
                                    $t('Option audio')
                                }}</Button
                            >
                            <Input
                                v-model="optionAudioTexts[index]"
                                :placeholder="
                                    $t('Audio pronunciation (optional)')
                                "
                                class="border-line h-8 text-[11px]"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <template v-if="kind === 'audio'">
                <LessonsField
                    v-model="audioText"
                    :label="$t('Listening script')"
                    :hint="
                        $t(
                            'The text is converted to stored audio unless you attach an audio file below.',
                        )
                    "
                />
            </template>

            <div class="grid gap-2 sm:grid-cols-3">
                <Button
                    v-for="mediaKind in ['image', 'audio', 'video'] as const"
                    :key="mediaKind"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 h-9 gap-1.5 px-2 text-[11px]"
                    @click="openPicker(mediaKind)"
                >
                    <Image
                        v-if="mediaKind === 'image'"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <Volume2
                        v-else-if="mediaKind === 'audio'"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <Video v-else class="size-3.5" aria-hidden="true" />
                    {{
                        mediaNames[`${mediaKind}:question`] ||
                        $t('Choose :kind', { kind: $t(mediaKind) })
                    }}
                </Button>
            </div>

            <p
                v-if="error"
                role="alert"
                class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12px]"
            >
                {{ $t(error) }}
            </p>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    @click="open = false"
                    >{{ $t('Cancel') }}</Button
                >
                <Button
                    type="button"
                    :disabled="saving"
                    class="bg-brand-600 h-10 gap-2 px-4 text-white"
                    @click="save"
                >
                    <LoaderCircle
                        v-if="saving"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ saving ? $t('Saving…') : $t('Save Activity') }}
                </Button>
            </div>
        </div>
        <LessonsMediaPicker
            v-model:open="pickerOpen"
            :tabs="mediaTabs"
            :categories="mediaCategories"
            :kind="pickerTarget?.kind ?? 'image'"
            @choose="chooseMedia"
        />
    </LessonsModal>
</template>
