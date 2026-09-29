<script setup lang="ts">
import { ArrowDown, ArrowUp, Check, CirclePlus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import type {
    ActivityTypeSpec,
    ItemDraft,
    OptionDraft,
} from '@/components/lessons/activities/activityCatalog';
import { nextOptionId } from '@/components/lessons/activities/activityCatalog';
import { cloneData } from '@/components/lessons/lessonsBlocks';
import LessonsAudioChips from '@/components/lessons/LessonsAudioChips.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import LessonsMediaSlot from '@/components/lessons/LessonsMediaSlot.vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    LessonAudioPair,
    LessonMediaRef,
    LessonsImageLibrary,
} from '@/types';

/**
 * One answerable item of an activity (spec 0003 B.9): the type's text,
 * media and number fields, then its answers with the correct one marked,
 * its word/answer pairs, or its lines in the correct order. Every change
 * writes a new draft object, so the parent's list is the one state.
 */
type Props = {
    spec: ActivityTypeSpec;
    number: number;
    library: LessonsImageLibrary;
    audio: Record<string, LessonAudioPair>;
    lookup: (id: unknown) => LessonMediaRef | null;
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

const MAX_OPTIONS = 6;
const MAX_PAIRS = 8;

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

function setPair(
    index: number,
    patch: Partial<{ prompt: string; target: string; image: number | null }>,
): void {
    update((copy) => {
        const pair = copy.pairs[index];

        if (pair !== undefined) {
            copy.pairs[index] = { ...pair, ...patch };
        }
    });
}

function setPairImage(index: number, media: LessonMediaRef | null): void {
    emit('remember', media);
    setPair(index, { image: media?.id ?? null });
}

function addPair(): void {
    update((copy) => {
        copy.pairs.push({ prompt: '', target: '', image: null });
    });
}

function removePair(index: number): void {
    update((copy) => {
        copy.pairs.splice(index, 1);
    });
}

/** Sentences the learner hears, for the Generate Audio chips (CTRL-05). */
function audioTexts(value: string | undefined): string[] {
    return typeof value === 'string' && value.trim() !== ''
        ? [value.trim()]
        : [];
}

const pairAudio = computed(() =>
    draft.value.pairs
        .map((pair) => pair.prompt.trim())
        .filter((text) => text !== ''),
);

const smallButton =
    'text-brand-800 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex size-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';
const removeButton =
    'text-danger-text hover:bg-danger-tint focus-visible:border-danger focus-visible:ring-danger/15 inline-flex size-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';
const addButton =
    'border-line text-brand-700 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-9 items-center gap-1.5 self-start rounded-md border border-dashed px-3 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';
const inputClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full min-w-0 rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none';
const sectionLabel =
    'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
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
                />
            </div>
            <LessonsField
                v-else
                :model-value="draft.fields[field.key] ?? ''"
                :label="$t(field.label)"
                :type="field.kind === 'text' ? 'text' : 'textarea'"
                :rows="field.kind === 'lines' ? 3 : 2"
                :hint="field.hint ? $t(field.hint) : undefined"
                @update:model-value="setField(field.key, $event)"
            />
        </template>

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
                @change="setMedia(slot.key, $event)"
            />
        </div>

        <!-- Answers with one correct (option-shaped types) -->
        <fieldset v-if="spec.options !== null" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Answers') }}
            </legend>
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
                        :placeholder="$t(spec.options.label)"
                        :class="inputClass"
                        :readonly="readOnly"
                        @input="
                            setEntry('options', index, {
                                text: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
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
                    class="ps-10"
                />
                <LessonsMediaSlot
                    v-if="spec.options.image"
                    :label="pictureLabel"
                    :media="lookup(option.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    compact
                    class="ps-10"
                    @change="setEntryImage('options', index, $event)"
                />
            </div>

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

        <!-- Matching: each word heard with its answer, plus extra answers -->
        <fieldset v-if="spec.matching" class="grid gap-2">
            <legend :class="cn(sectionLabel, 'mb-2')">
                {{ $t('Pairs to match') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    $t(
                        'Each row is one correct pair: the word the learner hears and its answer.',
                    )
                }}
            </p>
            <div
                v-for="(pair, index) in draft.pairs"
                :key="`pair-${index}`"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="font-heading text-brand-700 w-5 shrink-0 text-center text-[13px] font-semibold"
                        aria-hidden="true"
                    >
                        {{ index + 1 }}
                    </span>
                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                        <input
                            :value="pair.prompt"
                            type="text"
                            :aria-label="$t('Word the learner hears')"
                            :placeholder="$t('Word the learner hears')"
                            :class="inputClass"
                            :readonly="readOnly"
                            @input="
                                setPair(index, {
                                    prompt: ($event.target as HTMLInputElement)
                                        .value,
                                })
                            "
                        />
                        <input
                            :value="pair.target"
                            type="text"
                            :aria-label="$t('Matching answer')"
                            :placeholder="$t('Matching answer')"
                            :class="inputClass"
                            :readonly="readOnly"
                            @input="
                                setPair(index, {
                                    target: ($event.target as HTMLInputElement)
                                        .value,
                                })
                            "
                        />
                    </div>
                    <button
                        v-if="!readOnly"
                        type="button"
                        :class="removeButton"
                        :disabled="draft.pairs.length <= 1"
                        :aria-label="$t('Remove pair')"
                        @click="removePair(index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <LessonsMediaSlot
                    :label="pictureLabel"
                    :media="lookup(pair.image)"
                    :tabs="library.tabs"
                    :categories="library.categories"
                    compact
                    class="ps-7"
                    @change="setPairImage(index, $event)"
                />
            </div>
            <button
                v-if="!readOnly"
                type="button"
                :class="addButton"
                :disabled="draft.pairs.length >= MAX_PAIRS"
                @click="addPair"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add pair') }}
            </button>
            <LessonsAudioChips
                v-if="pairAudio.length > 0"
                :texts="pairAudio"
                :audio="audio"
                :read-only="readOnly"
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
                {{ $t('Lines in the correct order') }}
            </legend>
            <p class="text-ink-slate -mt-1 text-[11.5px]">
                {{
                    $t(
                        'Write them in the right order; learners see them mixed up.',
                    )
                }}
            </p>
            <div
                v-for="(line, index) in draft.lines"
                :key="`line-${index}`"
                class="border-line bg-surface grid gap-2 rounded-md border p-2"
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
                        @input="
                            setEntry('lines', index, {
                                text: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
                    <template v-if="!readOnly">
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
                    v-if="spec.ordering.image"
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
                @click="addEntry('lines')"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add line') }}
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
