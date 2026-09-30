<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Brackets,
    Check,
    CirclePlus,
    ImagePlus,
    Trash2,
    Volume2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type {
    ActivityTypeSpec,
    ItemDraft,
    OptionDraft,
    PairDraft,
} from '@/components/activities/activityCatalog';
import {
    nextOptionId,
    syncBlanks,
} from '@/components/activities/activityCatalog';
import {
    addButton,
    inputClass,
    removeButton,
    sectionLabel,
    smallButton,
} from '@/components/activities/activityEditor';
import type { AudioChipOptions } from '@/components/activities/activityEditor';
import ActivityAnswerList from '@/components/activities/ActivityAnswerList.vue';
import ActivityPromptAudio from '@/components/activities/ActivityPromptAudio.vue';
import { cloneData } from '@/components/lessons/lessonsBlocks';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import MeaningFieldButton from '@/components/meaning/MeaningFieldButton.vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    LessonAudioPair,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * One answerable item of an activity, for every type of the shared editor
 * (spec 0003 B.9; client report 2026-09-29): the type's text, number and
 * media fields; what the learner hears (generated voice or an uploaded /
 * recorded / library clip); then the answers — options with the correct one
 * marked (text or pictures, each with an optional pronunciation), pairs to
 * match, lines in the correct order, accepted answers, a sentence with its
 * blanks, or a writing rubric. Every change writes a new draft object, so
 * the parent's list is the one state.
 */
type Props = {
    spec: ActivityTypeSpec;
    number: number;
    library: LessonsImageLibrary;
    audio: Record<string, LessonAudioPair>;
    lookup: (id: unknown) => LessonMediaRef | null;
    chips: AudioChipOptions;
    error?: string;
    readOnly: boolean;
};

const props = defineProps<Props>();

const draft = defineModel<ItemDraft>('draft', { required: true });

const emit = defineEmits<{
    remember: [media: LessonMediaRef | null];
}>();

/** Translated inside LessonsMediaSlot. */
const pictureLabel = tk('Picture');
const audioLabel = tk('Audio clip');

const MAX_OPTIONS = 6;
const MAX_PAIRS = 8;
const MAX_CRITERIA = 6;

/** Rows whose optional pictures / audio are showing. */
const expanded = ref<Set<string>>(new Set());

function toggle(key: string): void {
    const next = new Set(expanded.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expanded.value = next;
}

function update(mutate: (copy: ItemDraft) => void): void {
    const copy = cloneData(draft.value);
    mutate(copy);
    draft.value = copy;
}

function setField(key: string, value: string | number | null): void {
    update((copy) => {
        copy.fields[key] = value === null ? '' : String(value);
    });
}

function setNumber(key: string, value: string | number | null): void {
    update((copy) => {
        copy.numbers[key] =
            typeof value === 'number' && Number.isFinite(value) ? value : null;
    });
}

function setMedia(key: string, media: LessonMediaRef | null): void {
    emit('remember', media);
    update((copy) => {
        copy.media[key] = media?.id ?? null;
    });
}

function setAudioText(value: string): void {
    update((copy) => {
        copy.audioText = value;
    });
}

type ListKey = 'options' | 'distractors' | 'lines';

function setEntry(
    list: ListKey,
    index: number,
    patch: Partial<OptionDraft>,
): void {
    update((copy) => {
        const entry = copy[list][index];

        if (entry !== undefined) {
            copy[list][index] = { ...entry, ...patch };
        }
    });
}

function setEntryImage(
    list: ListKey,
    index: number,
    media: LessonMediaRef | null,
): void {
    emit('remember', media);
    setEntry(list, index, { image: media?.id ?? null });
}

function addEntry(list: ListKey): void {
    update((copy) => {
        copy[list].push({
            id:
                list === 'lines'
                    ? ''
                    : nextOptionId([...copy.options, ...copy.distractors]),
            text: '',
            image: null,
            speak: false,
        });
    });
}

function removeEntry(list: ListKey, index: number): void {
    update((copy) => {
        const [removed] = copy[list].splice(index, 1);

        if (list === 'options' && removed?.id === copy.correct) {
            copy.correct = null;
        }
    });
}

function moveLine(index: number, delta: number): void {
    update((copy) => {
        const target = index + delta;
        const [line] = copy.lines.splice(index, 1);

        if (line !== undefined) {
            copy.lines.splice(
                Math.max(0, Math.min(copy.lines.length, target)),
                0,
                line,
            );
        }
    });
}

function markCorrect(id: string): void {
    update((copy) => {
        copy.correct = id;
    });
}

function setOptionStyle(style: 'text' | 'image'): void {
    update((copy) => {
        copy.optionStyle = style;
    });
}

function setPair(index: number, patch: Partial<PairDraft>): void {
    update((copy) => {
        const pair = copy.pairs[index];

        if (pair !== undefined) {
            copy.pairs[index] = { ...pair, ...patch };
        }
    });
}

function setPairMedia(
    index: number,
    key: 'image' | 'promptImage' | 'promptAudio',
    media: LessonMediaRef | null,
): void {
    emit('remember', media);
    setPair(index, { [key]: media?.id ?? null });
}

function addPair(): void {
    update((copy) => {
        copy.pairs.push({
            prompt: '',
            promptImage: null,
            promptAudio: null,
            target: '',
            image: null,
        });
    });
}

function removePair(index: number): void {
    update((copy) => {
        copy.pairs.splice(index, 1);
    });
}

function setAccepted(answers: string[]): void {
    update((copy) => {
        copy.accepted = answers;
    });
}

// ------------------------------------------------------ fill in the blank

const sentenceInput = ref<HTMLInputElement | null>(null);

function setSentence(value: string): void {
    update((copy) => {
        copy.sentence = value;
        copy.blanks = syncBlanks(copy);
    });
}

/** Wrap the selected word of the sentence in [brackets]: a new blank. */
function markBlank(): void {
    const input = sentenceInput.value;
    const sentence = draft.value.sentence;
    const start = input?.selectionStart ?? sentence.length;
    const end = input?.selectionEnd ?? sentence.length;
    const selected = sentence.slice(start, end).trim();

    setSentence(
        `${sentence.slice(0, start)}[${selected === '' ? 'word' : selected}]${sentence.slice(end)}`,
    );
}

function setAlternatives(index: number, alternatives: string[]): void {
    update((copy) => {
        const blank = copy.blanks[index];

        if (blank !== undefined) {
            copy.blanks[index] = { ...blank, alternatives };
        }
    });
}

// ---------------------------------------------------------------- rubric

function setCriterion(index: number, label: string): void {
    update((copy) => {
        const criterion = copy.criteria[index];

        if (criterion !== undefined) {
            copy.criteria[index] = { key: '', label };
        }
    });
}

function addCriterion(): void {
    update((copy) => {
        copy.criteria.push({ key: '', label: '' });
    });
}

function removeCriterion(index: number): void {
    update((copy) => {
        copy.criteria.splice(index, 1);
    });
}

/** Sentences the learner hears, for the Generate Audio chips (CTRL-05). */
function audioTexts(value: string | undefined): string[] {
    return typeof value === 'string' && value.trim() !== ''
        ? [value.trim()]
        : [];
}

const spokenOptions = computed(() =>
    draft.value.options
        .filter((option) => option.speak && option.text.trim() !== '')
        .map((option) => option.text.trim()),
);

const legacyPairAudio = computed(() =>
    props.spec.matching === 'legacy'
        ? draft.value.pairs
              .map((pair) => pair.prompt.trim())
              .filter((text) => text !== '')
        : [],
);

const pictureAnswers = computed(
    () =>
        props.spec.options !== null &&
        (props.spec.options.flexible
            ? draft.value.optionStyle === 'image'
            : props.spec.options.image),
);
</script>

<template>
    <div class="grid gap-4">
        <template v-for="field in spec.fields" :key="field.key">
            <div v-if="field.kind === 'audio'" class="grid gap-2">
                <LessonsField
                    :model-value="draft.fields[field.key] ?? ''"
                    :label="$t(field.label)"
                    :hint="field.hint ? $t(field.hint) : undefined"
                    @update:model-value="setField(field.key, $event)"
                />
                <LessonsAudioChips
                    v-if="audioTexts(draft.fields[field.key]).length > 0"
                    :texts="audioTexts(draft.fields[field.key])"
                    :audio="audio"
                    :read-only="readOnly"
                    :lesson-id="chips.lessonId"
                    :reload-only="chips.reloadOnly"
                />
            </div>
            <LessonsField
                v-else
                :model-value="draft.fields[field.key] ?? ''"
                :label="$t(field.label)"
                :type="field.kind === 'text' ? 'text' : 'textarea'"
                :rows="field.kind === 'lines' ? 3 : 2"
                :hint="field.hint ? $t(field.hint) : undefined"
                :required="field.required"
                :meaning="
                    field.kind !== 'lines' && field.key !== 'model_answer'
                "
                :data-test="`activity-field-${field.key}`"
                @update:model-value="setField(field.key, $event)"
            />
        </template>

        <!-- Fill in the blank: the sentence with its [blanks] -->
        <fieldset v-if="spec.blanks" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Sentence with blanks') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    $t(
                        'Put each missing word in [square brackets], or select a word and tap Mark as blank.',
                    )
                }}
            </p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    ref="sentenceInput"
                    :value="draft.sentence"
                    type="text"
                    :aria-label="$t('Sentence with blanks')"
                    :placeholder="$t('May I see your [passport], please?')"
                    :class="inputClass"
                    :readonly="readOnly"
                    data-test="activity-blank-sentence"
                    @input="
                        setSentence(($event.target as HTMLInputElement).value)
                    "
                />
                <button
                    v-if="!readOnly"
                    type="button"
                    :class="cn(addButton, 'shrink-0 border-solid')"
                    data-test="activity-mark-blank"
                    @mousedown.prevent
                    @click="markBlank"
                >
                    <Brackets class="size-4" aria-hidden="true" />
                    {{ $t('Mark as blank') }}
                </button>
            </div>
            <div
                v-for="(blank, index) in draft.blanks"
                :key="`blank-${index}`"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
                :data-test="`activity-blank-${index + 1}`"
            >
                <p class="text-ink text-[12.5px]">
                    <span class="text-brand-700 font-semibold">
                        {{ $t('Blank :number', { number: index + 1 }) }}
                    </span>
                    ·
                    <span class="text-success-text font-semibold">
                        {{ blank.word || $t('(empty)') }}
                    </span>
                </p>
                <ActivityAnswerList
                    :model-value="blank.alternatives"
                    :label="$t('Other accepted answer')"
                    :placeholder="$t('Other accepted answer (optional)')"
                    :read-only="readOnly"
                    :min="0"
                    :test-id="`activity-blank-${index + 1}-alt`"
                    @update:model-value="setAlternatives(index, $event)"
                />
            </div>
        </fieldset>

        <LessonsField
            v-for="field in spec.numbers"
            :key="field.key"
            :model-value="draft.numbers[field.key] ?? null"
            :label="$t(field.label)"
            type="number"
            :min="field.min"
            :max="field.max"
            @update:model-value="setNumber(field.key, $event)"
        />

        <div v-if="spec.media.length > 0" class="grid gap-3 md:grid-cols-2">
            <LessonsMediaSlot
                v-for="slot in spec.media"
                :key="slot.key"
                :label="slot.label"
                :kind="slot.kind"
                :media="lookup(draft.media[slot.key])"
                :tabs="library.tabs"
                :categories="library.categories"
                compact
                :data-test="`activity-media-${slot.key}`"
                @change="setMedia(slot.key, $event)"
            />
        </div>

        <ActivityPromptAudio
            v-if="spec.promptAudio !== null"
            :audio-text="draft.audioText"
            :media="lookup(draft.media.audio)"
            :required="spec.promptAudio === 'required'"
            :audio="audio"
            :library="library"
            :chips="chips"
            :read-only="readOnly"
            @update:audio-text="setAudioText"
            @change="setMedia('audio', $event)"
        />

        <!-- Answers with one correct (option-shaped types) -->
        <fieldset v-if="spec.options !== null" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Answers') }}
            </legend>
            <div
                v-if="spec.options.flexible"
                class="flex flex-wrap items-center gap-2"
                role="radiogroup"
                :aria-label="$t('Answer type')"
            >
                <span class="text-ink-slate text-[11.5px]">
                    {{ $t('Answers are') }}
                </span>
                <button
                    v-for="style in ['text', 'image'] as const"
                    :key="style"
                    type="button"
                    role="radio"
                    :aria-checked="draft.optionStyle === style"
                    :disabled="readOnly"
                    :data-test="`activity-option-style-${style}`"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600/15 inline-flex h-8 items-center rounded-md border px-3 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none',
                            draft.optionStyle === style
                                ? 'border-brand-600 bg-brand-50 text-brand-700'
                                : 'border-line text-ink-slate hover:bg-brand-50/60',
                        )
                    "
                    @click="setOptionStyle(style)"
                >
                    {{ style === 'text' ? $t('Text') : $t('Pictures') }}
                </button>
            </div>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{ $t('Tap the circle next to the correct answer.') }}
            </p>

            <div
                v-for="(option, index) in draft.options"
                :key="option.id"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
                :data-test="`activity-item-${number}-option-${option.id}`"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :aria-pressed="draft.correct === option.id"
                        :aria-label="
                            $t('Mark answer :letter as correct', {
                                letter: option.id.toUpperCase(),
                            })
                        "
                        :data-test="`activity-item-${number}-correct-${option.id}`"
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/15 inline-flex size-8 shrink-0 items-center justify-center rounded-full border-2 transition-colors focus-visible:ring-3 focus-visible:outline-none',
                                draft.correct === option.id
                                    ? 'border-success bg-success text-white'
                                    : 'border-line-strong text-ink-faint hover:border-success bg-surface',
                            )
                        "
                        :disabled="readOnly"
                        @click="markCorrect(option.id)"
                    >
                        <Check class="size-4" aria-hidden="true" />
                    </button>
                    <span
                        class="font-heading text-brand-700 w-5 shrink-0 text-center text-[13px] font-semibold"
                        aria-hidden="true"
                    >
                        {{ option.id.toUpperCase() }}
                    </span>
                    <input
                        :value="option.text"
                        type="text"
                        :aria-label="
                            $t(':label :letter', {
                                label: $t(spec.options.label),
                                letter: option.id.toUpperCase(),
                            })
                        "
                        :placeholder="
                            pictureAnswers && spec.options.flexible
                                ? $t('Caption (optional)')
                                : $t(spec.options.label)
                        "
                        :class="inputClass"
                        :readonly="readOnly"
                        :data-test="`activity-item-${number}-option-${option.id}-text`"
                        @input="
                            setEntry('options', index, {
                                text: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
                    <MeaningFieldButton
                        v-if="!readOnly"
                        compact
                        :text="option.text"
                        :label="
                            $t(':label :letter', {
                                label: $t(spec.options.label),
                                letter: option.id.toUpperCase(),
                            })
                        "
                    />
                    <button
                        v-if="spec.options.flexible && !readOnly"
                        type="button"
                        :aria-pressed="option.speak"
                        :aria-label="
                            $t('Play the pronunciation of answer :letter', {
                                letter: option.id.toUpperCase(),
                            })
                        "
                        :title="$t('Pronunciation')"
                        :data-test="`activity-item-${number}-option-${option.id}-speak`"
                        :class="
                            cn(
                                smallButton,
                                option.speak && 'bg-brand-50 text-brand-600',
                            )
                        "
                        @click="
                            setEntry('options', index, { speak: !option.speak })
                        "
                    >
                        <Volume2 class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        v-if="!readOnly"
                        type="button"
                        :class="removeButton"
                        :disabled="draft.options.length <= 2"
                        :aria-label="$t('Remove answer')"
                        @click="removeEntry('options', index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <span
                    v-if="draft.correct === option.id"
                    class="text-success-text flex items-center gap-1 ps-10 text-[11.5px] font-semibold"
                >
                    <Check class="size-3.5" aria-hidden="true" />
                    {{ $t('Correct answer') }}
                </span>
                <LessonsAudioChips
                    v-if="
                        spec.options.key === 'audio_text' &&
                        option.text.trim() !== ''
                    "
                    :texts="[option.text.trim()]"
                    :audio="audio"
                    :read-only="readOnly"
                    :lesson-id="chips.lessonId"
                    :reload-only="chips.reloadOnly"
                    class="ps-10"
                />
                <LessonsMediaSlot
                    v-if="pictureAnswers"
                    :label="pictureLabel"
                    :media="lookup(option.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    compact
                    class="ps-10"
                    @change="setEntryImage('options', index, $event)"
                />
            </div>

            <LessonsAudioChips
                v-if="spokenOptions.length > 0"
                :texts="spokenOptions"
                :audio="audio"
                :read-only="readOnly"
                :lesson-id="chips.lessonId"
                :reload-only="chips.reloadOnly"
            />

            <button
                v-if="!readOnly"
                type="button"
                :class="addButton"
                :disabled="draft.options.length >= MAX_OPTIONS"
                :data-test="`activity-item-${number}-add-option`"
                @click="addEntry('options')"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add answer') }}
            </button>
        </fieldset>

        <!-- Matching: each word with its pair, plus extra answers -->
        <fieldset v-if="spec.matching !== null" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Pairs to match') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    spec.matching === 'legacy'
                        ? $t(
                              'Each row is one correct pair: the word the learner hears and its answer.',
                          )
                        : $t(
                              'Each row is one correct pair. Learners see the right-hand side mixed up.',
                          )
                }}
            </p>
            <div
                v-for="(pair, index) in draft.pairs"
                :key="`pair-${index}`"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
                :data-test="`activity-pair-${index + 1}`"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="font-heading text-brand-700 w-5 shrink-0 text-center text-[13px] font-semibold"
                        aria-hidden="true"
                    >
                        {{ index + 1 }}
                    </span>
                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                        <div class="flex min-w-0 items-center gap-1">
                            <input
                                :value="pair.prompt"
                                type="text"
                                :aria-label="
                                    spec.matching === 'legacy'
                                        ? $t('Word the learner hears')
                                        : $t('Word')
                                "
                                :placeholder="
                                    spec.matching === 'legacy'
                                        ? $t('Word the learner hears')
                                        : $t('Word')
                                "
                                :class="inputClass"
                                :readonly="readOnly"
                                :data-test="`activity-pair-${index + 1}-left`"
                                @input="
                                    setPair(index, {
                                        prompt: (
                                            $event.target as HTMLInputElement
                                        ).value,
                                    })
                                "
                            />
                            <MeaningFieldButton
                                v-if="!readOnly"
                                compact
                                :text="pair.prompt"
                                :label="$t('Word')"
                            />
                        </div>
                        <div class="flex min-w-0 items-center gap-1">
                            <input
                                :value="pair.target"
                                type="text"
                                :aria-label="$t('Matching answer')"
                                :placeholder="$t('Matching answer')"
                                :class="inputClass"
                                :readonly="readOnly"
                                :data-test="`activity-pair-${index + 1}-right`"
                                @input="
                                    setPair(index, {
                                        target: (
                                            $event.target as HTMLInputElement
                                        ).value,
                                    })
                                "
                            />
                            <MeaningFieldButton
                                v-if="!readOnly"
                                compact
                                :text="pair.target"
                                :label="$t('Matching answer')"
                            />
                        </div>
                    </div>
                    <button
                        v-if="spec.matching === 'flexible'"
                        type="button"
                        :class="
                            cn(
                                smallButton,
                                expanded.has(`pair-${index}`) &&
                                    'bg-brand-50 text-brand-600',
                            )
                        "
                        :aria-expanded="expanded.has(`pair-${index}`)"
                        :aria-label="
                            $t('Pictures and audio of pair :number', {
                                number: index + 1,
                            })
                        "
                        @click="toggle(`pair-${index}`)"
                    >
                        <ImagePlus class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        v-if="!readOnly"
                        type="button"
                        :class="removeButton"
                        :disabled="draft.pairs.length <= 2"
                        :aria-label="$t('Remove pair')"
                        @click="removePair(index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <LessonsMediaSlot
                    v-if="spec.matching === 'legacy'"
                    :label="pictureLabel"
                    :media="lookup(pair.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    compact
                    class="ps-7"
                    @change="setPairMedia(index, 'image', $event)"
                />
                <div
                    v-else-if="
                        expanded.has(`pair-${index}`) ||
                        pair.promptImage !== null ||
                        pair.promptAudio !== null ||
                        pair.image !== null
                    "
                    class="grid gap-2 ps-7 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <LessonsMediaSlot
                            :label="pictureLabel"
                            :media="lookup(pair.promptImage)"
                            :tabs="library.tabs"
                            :categories="library.categories"
                            compact
                            @change="setPairMedia(index, 'promptImage', $event)"
                        />
                        <LessonsMediaSlot
                            :label="audioLabel"
                            kind="audio"
                            :media="lookup(pair.promptAudio)"
                            :tabs="library.tabs"
                            :categories="library.categories"
                            compact
                            @change="setPairMedia(index, 'promptAudio', $event)"
                        />
                    </div>
                    <LessonsMediaSlot
                        :label="pictureLabel"
                        :media="lookup(pair.image)"
                        :tabs="library.tabs"
                        :categories="library.categories"
                        compact
                        @change="setPairMedia(index, 'image', $event)"
                    />
                </div>
            </div>
            <button
                v-if="!readOnly"
                type="button"
                :class="addButton"
                :disabled="draft.pairs.length >= MAX_PAIRS"
                data-test="activity-add-pair"
                @click="addPair"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add pair') }}
            </button>
            <LessonsAudioChips
                v-if="legacyPairAudio.length > 0"
                :texts="legacyPairAudio"
                :audio="audio"
                :read-only="readOnly"
                :lesson-id="chips.lessonId"
                :reload-only="chips.reloadOnly"
            />

            <p :class="cn(sectionLabel, 'mt-2')">
                {{ $t('Extra answers (optional)') }}
            </p>
            <div
                v-for="(option, index) in draft.distractors"
                :key="option.id"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
            >
                <div class="flex items-center gap-2">
                    <input
                        :value="option.text"
                        type="text"
                        :aria-label="$t('Extra answer')"
                        :placeholder="$t('Extra answer')"
                        :class="inputClass"
                        :readonly="readOnly"
                        @input="
                            setEntry('distractors', index, {
                                text: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
                    <MeaningFieldButton
                        v-if="!readOnly"
                        compact
                        :text="option.text"
                        :label="$t('Extra answer')"
                    />
                    <button
                        v-if="!readOnly"
                        type="button"
                        :class="removeButton"
                        :aria-label="$t('Remove answer')"
                        @click="removeEntry('distractors', index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <LessonsMediaSlot
                    :label="pictureLabel"
                    :media="lookup(option.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    compact
                    @change="setEntryImage('distractors', index, $event)"
                />
            </div>
            <button
                v-if="!readOnly"
                type="button"
                :class="addButton"
                :disabled="draft.distractors.length >= MAX_OPTIONS"
                @click="addEntry('distractors')"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add extra answer') }}
            </button>
        </fieldset>

        <!-- Ordering: the lines in the correct order -->
        <fieldset v-if="spec.ordering !== null" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Items in the correct order') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    $t(
                        'Write the words or sentences in the right order; learners see them mixed up.',
                    )
                }}
            </p>
            <div
                v-for="(line, index) in draft.lines"
                :key="`line-${index}`"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
                :data-test="`activity-line-${index + 1}`"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="font-heading text-brand-700 w-5 shrink-0 text-center text-[13px] font-semibold"
                        aria-hidden="true"
                    >
                        {{ index + 1 }}
                    </span>
                    <input
                        :value="line.text"
                        type="text"
                        :aria-label="$t('Line :number', { number: index + 1 })"
                        :placeholder="$t('Line :number', { number: index + 1 })"
                        :class="inputClass"
                        :readonly="readOnly"
                        :data-test="`activity-line-${index + 1}-text`"
                        @input="
                            setEntry('lines', index, {
                                text: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
                    <MeaningFieldButton
                        v-if="!readOnly"
                        compact
                        :text="line.text"
                        :label="$t('Line :number', { number: index + 1 })"
                    />
                    <template v-if="!readOnly">
                        <button
                            v-if="spec.ordering.image && !spec.legacy"
                            type="button"
                            :class="
                                cn(
                                    smallButton,
                                    expanded.has(`line-${index}`) &&
                                        'bg-brand-50 text-brand-600',
                                )
                            "
                            :aria-expanded="expanded.has(`line-${index}`)"
                            :aria-label="
                                $t('Picture of line :number', {
                                    number: index + 1,
                                })
                            "
                            @click="toggle(`line-${index}`)"
                        >
                            <ImagePlus class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :class="smallButton"
                            :disabled="index === 0"
                            :aria-label="$t('Move up')"
                            @click="moveLine(index, -1)"
                        >
                            <ArrowUp class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :class="smallButton"
                            :disabled="index === draft.lines.length - 1"
                            :aria-label="$t('Move down')"
                            @click="moveLine(index, 1)"
                        >
                            <ArrowDown class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :class="removeButton"
                            :disabled="draft.lines.length <= 2"
                            :aria-label="$t('Remove line')"
                            @click="removeEntry('lines', index)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </template>
                </div>
                <LessonsMediaSlot
                    v-if="
                        spec.ordering.image &&
                        (spec.legacy ||
                            expanded.has(`line-${index}`) ||
                            line.image !== null)
                    "
                    :label="pictureLabel"
                    :media="lookup(line.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    compact
                    class="ps-7"
                    @change="setEntryImage('lines', index, $event)"
                />
            </div>
            <button
                v-if="!readOnly"
                type="button"
                :class="addButton"
                :disabled="draft.lines.length >= MAX_PAIRS"
                data-test="activity-add-line"
                @click="addEntry('lines')"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add line') }}
            </button>
        </fieldset>

        <!-- Short answer: the accepted answers -->
        <fieldset v-if="spec.accepted" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Accepted answers') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    $t(
                        'Any of them counts as correct. Capital letters, spaces and punctuation are ignored.',
                    )
                }}
            </p>
            <ActivityAnswerList
                :model-value="draft.accepted"
                :label="$t('Accepted answer')"
                :placeholder="$t('Accepted answer')"
                :read-only="readOnly"
                test-id="activity-accepted"
                @update:model-value="setAccepted"
            />
        </fieldset>

        <!-- Writing: what the AI scores -->
        <fieldset v-if="spec.rubric" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('AI evaluation criteria') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    $t(
                        'After Submit the AI gives a score per criterion, feedback, corrections and an improved answer.',
                    )
                }}
            </p>
            <div
                v-for="(criterion, index) in draft.criteria"
                :key="`criterion-${index}`"
                class="flex items-center gap-2"
            >
                <input
                    :value="criterion.label"
                    type="text"
                    :aria-label="$t('Criterion :number', { number: index + 1 })"
                    :placeholder="
                        $t('Criterion :number', { number: index + 1 })
                    "
                    :class="inputClass"
                    :readonly="readOnly"
                    @input="
                        setCriterion(
                            index,
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <button
                    v-if="!readOnly"
                    type="button"
                    :class="removeButton"
                    :disabled="draft.criteria.length <= 1"
                    :aria-label="$t('Remove criterion')"
                    @click="removeCriterion(index)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </div>
            <button
                v-if="!readOnly"
                type="button"
                :class="addButton"
                :disabled="draft.criteria.length >= MAX_CRITERIA"
                @click="addCriterion"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add criterion') }}
            </button>
        </fieldset>

        <p
            v-if="error"
            class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
