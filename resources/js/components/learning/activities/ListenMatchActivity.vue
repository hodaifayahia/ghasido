<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, FilePenLine, Link2, Volume2, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActivityFrame from '@/components/learning/practice/ActivityFrame.vue';
import SpeedRow from '@/components/learning/practice/SpeedRow.vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import TaskCard from '@/components/learning/TaskCard.vue';
import WaveformGlyph from '@/components/learning/WaveformGlyph.vue';
import { cn } from '@/lib/utils';
import type {
    ActivityResult,
    ActivityViewOf,
    LessonSummary,
    ListenMatchItem,
} from '@/types';

/*
 * Listen & Match (PRAC-01, PRAC-04; photo_11): this activity has a pair-map
 * answer, not one option per item. A learner selects an audio row, then its
 * picture; the answer is posted as `{ promptId: targetId }` for each item so
 * the server can preserve the complete matching attempt (TEST-06, DATA-01).
 */
type Props = {
    lesson: LessonSummary;
    activity: ActivityViewOf<'listen_match'>;
    result: ActivityResult | null;
    answerUrl: string;
    backUrl: string;
    number?: number;
};

type PairMap = Record<string, string>;
type PairState = 'idle' | 'selected' | 'correct' | 'incorrect';

const props = defineProps<Props>();

const answers = reactive<Record<string, PairMap>>({});
const current = ref(0);
const activePrompt = ref<string | null>(null);
const speed = ref<'normal' | 'slow'>('normal');
const startedAt = new Date().toISOString();
const form = useForm<{ answers: Record<string, PairMap>; started_at: string }>({
    answers: {},
    started_at: startedAt,
});

const items = computed(() => props.activity.items);
const total = computed(() => items.value.length);
const item = computed<ListenMatchItem | null>(
    () => items.value[current.value] ?? null,
);
const locked = computed(
    () =>
        props.result !== null ||
        (props.activity.attemptsLeft !== null &&
            props.activity.attemptsLeft <= 0),
);
const itemAnswer = computed<PairMap>(() => {
    const id = item.value?.id;

    return id ? (answers[id] ?? {}) : {};
});

const answeredIndexes = computed(() =>
    items.value
        .map((entry, index) =>
            isComplete(entry, answers[entry.id] ?? {}) ? index : -1,
        )
        .filter((index) => index >= 0),
);

const canCheck = computed(
    () =>
        total.value > 0 &&
        props.result === null &&
        !locked.value &&
        items.value.every((entry) =>
            isComplete(entry, answers[entry.id] ?? {}),
        ),
);

function isComplete(entry: ListenMatchItem, pairMap: PairMap): boolean {
    return (
        entry.prompts.length > 0 &&
        entry.prompts.every((prompt) =>
            Object.prototype.hasOwnProperty.call(pairMap, prompt.id),
        )
    );
}

function goto(index: number): void {
    current.value = Math.min(Math.max(index, 0), total.value - 1);
    activePrompt.value = null;
}

function previous(): void {
    if (current.value > 0) {
        goto(current.value - 1);
    }
}

function selectPrompt(promptId: string): void {
    if (locked.value) {
        return;
    }

    activePrompt.value = promptId;
}

function selectTarget(targetId: string): void {
    if (locked.value) {
        return;
    }

    const entry = item.value;
    const promptId = activePrompt.value;

    if (!entry || promptId === null) {
        return;
    }

    const next = { ...itemAnswer.value };

    // A picture can only belong to one audio row. Reassigning it clears the
    // previous row instead of silently posting duplicate matches.
    Object.entries(next).forEach(([existingPrompt, existingTarget]) => {
        if (existingPrompt !== promptId && existingTarget === targetId) {
            delete next[existingPrompt];
        }
    });

    next[promptId] = targetId;
    answers[entry.id] = next;

    const nextPrompt = entry.prompts.find(
        (prompt) => !Object.prototype.hasOwnProperty.call(next, prompt.id),
    );
    activePrompt.value = nextPrompt?.id ?? null;
}

function selectedTarget(promptId: string): string | null {
    return itemAnswer.value[promptId] ?? null;
}

function targetOwner(targetId: string): string | null {
    return (
        Object.entries(itemAnswer.value).find(
            ([, selected]) => selected === targetId,
        )?.[0] ?? null
    );
}

function correctPairs(entry: ListenMatchItem): PairMap {
    if (props.result === null) {
        return {};
    }

    const correct = props.result.correct[entry.id];

    return correct && typeof correct === 'object' && !Array.isArray(correct)
        ? (correct as PairMap)
        : {};
}

function promptState(entry: ListenMatchItem, promptId: string): PairState {
    const selected = answers[entry.id]?.[promptId];

    if (props.result === null) {
        return selected ? 'selected' : 'idle';
    }

    if (!selected) {
        return 'idle';
    }

    return correctPairs(entry)[promptId] === selected ? 'correct' : 'incorrect';
}

function targetState(entry: ListenMatchItem, targetId: string): PairState {
    const owner = targetOwner(targetId);

    if (props.result === null) {
        return owner === null
            ? activePrompt.value !== null
                ? 'selected'
                : 'idle'
            : activePrompt.value === owner
              ? 'selected'
              : 'idle';
    }

    const expectedOwner = Object.entries(correctPairs(entry)).find(
        ([, target]) => target === targetId,
    )?.[0];

    if (expectedOwner === undefined) {
        return 'idle';
    }

    if (owner === null) {
        return 'correct';
    }

    return owner === expectedOwner ? 'correct' : 'incorrect';
}

function stateClass(state: PairState): string {
    switch (state) {
        case 'correct':
            return 'border-success bg-success-tint';
        case 'incorrect':
            return 'border-danger bg-danger-tint animate-shake motion-reduce:animate-none';
        case 'selected':
            return 'border-brand-600 bg-brand-50';
        default:
            return 'border-line hover:border-brand-300';
    }
}

function promptAudio(
    prompt: ListenMatchItem['prompts'][number],
): string | null {
    const pair = prompt.audio_text_audio;

    return pair ? (speed.value === 'slow' ? pair.slow : pair.normal) : null;
}

function letter(index: number): string {
    return String.fromCharCode(65 + index);
}

function onCheck(): void {
    if (props.result !== null) {
        router.visit(props.backUrl);

        return;
    }

    form.answers = Object.fromEntries(
        Object.entries(answers).map(([id, pairMap]) => [id, { ...pairMap }]),
    );
    form.started_at = startedAt;
    form.post(props.answerUrl, { preserveScroll: true });
}

watch(current, () => {
    activePrompt.value = null;
});
</script>

<template>
    <ActivityFrame
        :back-url="backUrl"
        :icon="Link2"
        tone="ai"
        :number="number"
        :label="activity.label"
        subtitle="Listen to the word and match it with the correct picture."
        :department="lesson.department.name"
        :lesson-number="lesson.positionInCourse"
        :lesson-count="lesson.courseLessonCount"
        :side-photo="activity.sideImage ?? lesson.cover"
        tip="Listen carefully. You can play the audio as many times as you need."
        tip-class="p-2 ps-[18px] [&>svg]:size-7 [&_p]:leading-[18px]"
        :total="total"
        :current="current"
        :answered="answeredIndexes"
        :can-check="canCheck"
        :has-result="result !== null"
        :check-label="result !== null ? 'Back to Practice' : 'Check Answers'"
        @check="onCheck"
        @prev="previous"
        @select="goto"
    >
        <template #side>
            <TaskCard
                title="Task"
                text="Listen to each word and click the matching picture."
                :icon="FilePenLine"
                class="p-2 ps-[18px] [&_h2]:text-base [&_h2]:leading-5 [&_p]:text-[13px] [&_p]:leading-[18px] [&>header>span]:size-9 [&>header>span>svg]:size-5"
            />
        </template>

        <div v-if="item" class="flex min-w-0 flex-col">
            <div class="grid min-w-0 gap-3 lg:grid-cols-[344px_minmax(0,1fr)]">
                <section
                    class="bg-app-alt min-w-0 rounded-xl p-4"
                    aria-labelledby="listen-match-prompts"
                >
                    <h2
                        id="listen-match-prompts"
                        class="text-ink flex items-center gap-4 px-[9px] pb-3 text-base font-semibold"
                    >
                        <span
                            class="bg-brand-600 grid size-12 shrink-0 place-items-center rounded-full text-white"
                        >
                            <Volume2
                                class="size-6 [&>path:first-child]:fill-current"
                                aria-hidden="true"
                            />
                        </span>
                        Listen to the words:
                    </h2>

                    <div class="flex flex-col gap-2">
                        <div
                            v-for="(prompt, index) in item.prompts"
                            :key="prompt.id"
                            role="button"
                            :aria-disabled="locked"
                            :aria-pressed="activePrompt === prompt.id"
                            :tabindex="locked ? -1 : 0"
                            class="bg-surface focus-visible:ring-brand-600/40 flex min-h-[60px] min-w-0 cursor-pointer items-center gap-3 rounded-lg ps-4 pe-2 focus-visible:ring-3 focus-visible:outline-none"
                            :class="stateClass(promptState(item, prompt.id))"
                            @click="selectPrompt(prompt.id)"
                            @keydown.enter.prevent="selectPrompt(prompt.id)"
                            @keydown.space.prevent="selectPrompt(prompt.id)"
                        >
                            <span
                                class="bg-brand-50 text-brand-700 grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold"
                            >
                                {{ index + 1 }}
                            </span>
                            <AudioButton
                                :src="promptAudio(prompt)"
                                size="sm"
                                :text="prompt.audio_text"
                                class="bg-brand-600 hover:bg-brand-700 text-white [&>svg]:text-white"
                                :class="
                                    activePrompt === prompt.id
                                        ? 'ring-brand-600/30 ring-3'
                                        : ''
                                "
                            />
                            <span class="text-brand-400 min-w-0 flex-1">
                                <WaveformGlyph class="h-6 w-full" />
                            </span>
                            <span
                                class="grid size-7 shrink-0 place-items-center rounded-full border-2"
                                :class="
                                    activePrompt === prompt.id
                                        ? 'border-brand-600 bg-brand-100'
                                        : selectedTarget(prompt.id)
                                          ? 'border-success bg-success-tint'
                                          : 'border-line-strong'
                                "
                            >
                                <Check
                                    v-if="selectedTarget(prompt.id)"
                                    class="text-success-text size-4"
                                    aria-hidden="true"
                                />
                            </span>
                        </div>
                    </div>
                </section>

                <section
                    class="border-line bg-surface min-w-0 rounded-xl border p-3"
                    aria-label="Pictures"
                >
                    <div class="ms-auto max-w-[463px]">
                        <SpeedRow
                            v-model:speed="speed"
                            :meaning-enabled="false"
                        />
                    </div>

                    <div
                        class="-mx-3 mt-[10px] grid min-w-0 grid-cols-2 gap-x-3 gap-y-[18px] sm:grid-cols-3"
                    >
                        <button
                            v-for="(target, index) in item.targets"
                            :key="target.id"
                            type="button"
                            :disabled="locked || activePrompt === null"
                            :aria-label="`Picture ${letter(index)}: ${target.label}`"
                            :class="
                                cn(
                                    'bg-surface focus-visible:ring-brand-600/40 flex min-w-0 flex-col gap-2 rounded-lg border-2 p-1.5 text-start transition focus-visible:ring-3 focus-visible:outline-none disabled:cursor-default disabled:opacity-70',
                                    stateClass(targetState(item, target.id)),
                                )
                            "
                            @click="selectTarget(target.id)"
                        >
                            <div
                                class="relative aspect-[1.15] overflow-hidden rounded-md"
                            >
                                <img
                                    v-if="target.image"
                                    :src="target.image.url"
                                    :alt="target.image.alt ?? target.label"
                                    loading="lazy"
                                    decoding="async"
                                    class="size-full object-cover"
                                />
                                <span
                                    class="bg-surface/90 text-brand-700 absolute start-1.5 top-1.5 grid size-7 place-items-center rounded-full text-sm font-bold"
                                >
                                    {{ letter(index) }}
                                </span>
                            </div>
                            <span
                                class="text-brand-700 min-h-5 text-center text-sm leading-5 font-semibold sm:text-base"
                            >
                                {{ target.label }}
                            </span>
                            <span
                                v-if="
                                    targetState(item, target.id) === 'correct'
                                "
                                class="text-success-text inline-flex items-center justify-center gap-1 text-xs font-semibold"
                            >
                                <Check class="size-3.5" aria-hidden="true" />
                                Correct
                            </span>
                            <span
                                v-else-if="
                                    targetState(item, target.id) === 'incorrect'
                                "
                                class="text-danger inline-flex items-center justify-center gap-1 text-xs font-semibold"
                            >
                                <X class="size-3.5" aria-hidden="true" />
                                Not quite
                            </span>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </ActivityFrame>
</template>
