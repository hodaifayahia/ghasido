<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Check, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import ActivityDots from '@/components/learning/ActivityDots.vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import CheckButton from '@/components/learning/CheckButton.vue';
import OptionRow from '@/components/learning/OptionRow.vue';
import RecorderButton from '@/components/learning/RecorderButton.vue';
import RecordingPlayer from '@/components/learning/RecordingPlayer.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useI18n } from '@/composables/useI18n';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import { store as storeRecording } from '@/routes/learn/recordings';
import type {
    ActivityItem,
    ActivityResult,
    ActivityView,
    AnswerMap,
    AudioPair,
    MediaRef,
    RawAnswer,
} from '@/types';
import MeaningRow from '@/components/learning/meaning/MeaningRow.vue';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * The fallback runner for any activity type without its own component yet
 * (PRAC-01..04, PRAC-07, TEST-06, DATA-01, CTRL-04; spec 0003 B.9, H.3).
 * It reads every item generically: option lists become OptionRows, a match
 * item becomes one select per prompt, an ordering item a list with move
 * up/down buttons (the keyboard/tap alternative every drag needs, ACC-03),
 * speaking the recorder (uploaded first, DATA-02), writing a textarea.
 * "Check" posts every answer verbatim as one attempt; the page comes back
 * with `result` and the rows show correct / not quite with icon and text.
 * In test mode there is no Show Meaning and no correctness (TEST-03).
 */
type Props = {
    activity: ActivityView;
    result: ActivityResult | null;
    answerUrl: string;
    blockId: number;
    initialAnswers?: AnswerMap;
    // What a spoken answer is recorded for: the lesson step by default, the
    // open test sitting in the test runner (TEST-07).
    recordable?: { type: 'block' | 'test_attempt'; id: number };
};

const props = defineProps<Props>();

const { t } = useI18n();

const isTest = computed(() => props.activity.mode === 'test');
const meaning = useShowMeaning(
    () => !isTest.value && props.activity.showMeaningEnabled,
);

const items = computed((): ActivityItem[] => props.activity.items);
const current = ref(0);
const answers = reactive<AnswerMap>({});

if (props.initialAnswers) {
    Object.assign(answers, props.initialAnswers);
}

// The test runner drives navigation from the parent page; it reads the
// current answer here to save it before moving on (TEST-06, DATA-01).
defineExpose({ collect: (): AnswerMap => ({ ...answers }) });
const recordings = reactive<
    Record<string, { url: string; durationMs: number }>
>({});
const uploading = ref(false);
const startedAt = new Date().toISOString();

const form = useForm<{ answers: AnswerMap; started_at: string }>({
    answers: {},
    started_at: startedAt,
});

type Loose = Record<string, unknown>;

function loose(item: ActivityItem): Loose {
    return item as unknown as Loose;
}

function text(item: ActivityItem, key: string): string | null {
    const value = loose(item)[key];

    return typeof value === 'string' && value !== '' ? value : null;
}

function media(item: ActivityItem, key: string): MediaRef | null {
    const value = loose(item)[key];

    return value !== null && typeof value === 'object' && 'url' in value
        ? (value as MediaRef)
        : null;
}

function audio(item: ActivityItem, key: string): AudioPair | null {
    const value = loose(item)[key];

    return value !== null && typeof value === 'object' && 'normal' in value
        ? (value as AudioPair)
        : null;
}

type Option = {
    id: string;
    label?: string;
    text?: string;
    audio_text?: string;
    audio_text_audio?: AudioPair;
    image?: MediaRef | null;
};

function options(item: ActivityItem): Option[] {
    const value = loose(item)['options'];

    return Array.isArray(value) ? (value as Option[]) : [];
}

type Ordered = {
    id: string;
    text?: string;
    caption?: string;
    image?: MediaRef | null;
    text_audio?: AudioPair;
};

function orderable(item: ActivityItem): Ordered[] {
    const value = loose(item)['sentences'] ?? loose(item)['cards'];

    return Array.isArray(value) ? (value as Ordered[]) : [];
}

function currentOrder(item: ActivityItem): string[] {
    const stored = answers[item.id];

    if (Array.isArray(stored)) {
        return stored;
    }

    const initial = orderable(item).map((entry) => entry.id);
    answers[item.id] = initial;

    return initial;
}

function move(item: ActivityItem, id: string, delta: -1 | 1): void {
    const order = [...currentOrder(item)];
    const index = order.indexOf(id);
    const target = index + delta;

    if (index === -1 || target < 0 || target >= order.length) {
        return;
    }

    order.splice(index, 1);
    order.splice(target, 0, id);
    answers[item.id] = order;
}

function orderedEntries(item: ActivityItem): Ordered[] {
    const entries = orderable(item);

    return currentOrder(item)
        .map((id) => entries.find((entry) => entry.id === id))
        .filter((entry): entry is Ordered => entry !== undefined);
}

function pairs(item: ActivityItem): Record<string, string> {
    const stored = answers[item.id];

    if (
        stored &&
        typeof stored === 'object' &&
        !Array.isArray(stored) &&
        !('recording_media_id' in stored) &&
        !('text' in stored)
    ) {
        return stored;
    }

    const initial: Record<string, string> = {};
    answers[item.id] = initial;

    return initial;
}

function setPair(item: ActivityItem, promptId: string, targetId: string): void {
    answers[item.id] = { ...pairs(item), [promptId]: targetId };
}

function prompts(item: ActivityItem): Option[] {
    const value = loose(item)['prompts'];

    return Array.isArray(value) ? (value as Option[]) : [];
}

function targets(item: ActivityItem): Option[] {
    const value = loose(item)['targets'];

    return Array.isArray(value) ? (value as Option[]) : [];
}

function selected(item: ActivityItem): string | null {
    const stored = answers[item.id];

    return typeof stored === 'string' ? stored : null;
}

function written(item: ActivityItem): string {
    const stored = answers[item.id];

    return stored && typeof stored === 'object' && 'text' in stored
        ? stored.text
        : '';
}

function setWritten(item: ActivityItem, value: string): void {
    answers[item.id] = { text: value };
}

function isAnswered(item: ActivityItem): boolean {
    const stored: RawAnswer | undefined = answers[item.id];

    if (stored === undefined) {
        return false;
    }

    switch (props.activity.type) {
        case 'listen_match':
            return (
                typeof stored === 'object' &&
                !Array.isArray(stored) &&
                Object.keys(stored).length === prompts(item).length
            );
        case 'writing':
            return (
                typeof stored === 'object' &&
                'text' in stored &&
                stored.text.trim() !== ''
            );
        case 'speaking':
            return typeof stored === 'object' && 'recording_media_id' in stored;
        default:
            return true;
    }
}

const answeredIndexes = computed(() =>
    items.value
        .map((item, index) => (isAnswered(item) ? index : -1))
        .filter((index) => index >= 0),
);
const allAnswered = computed(
    () => items.value.length > 0 && items.value.every(isAnswered),
);
const locked = computed(
    () =>
        props.result !== null ||
        (props.activity.attemptsLeft !== null &&
            props.activity.attemptsLeft <= 0),
);

function rowState(
    item: ActivityItem,
    optionId: string,
): 'idle' | 'correct' | 'incorrect' {
    if (isTest.value || props.result === null) {
        return 'idle';
    }

    const verdict = props.result.perItem[item.id];
    const chosen = selected(item) ?? props.result.correct[item.id];

    if (verdict === true && chosen === optionId) {
        return 'correct';
    }

    if (verdict === false && chosen === optionId) {
        return 'incorrect';
    }

    if (verdict === false && props.result.correct[item.id] === optionId) {
        return 'correct';
    }

    return 'idle';
}

const overall = computed((): 'idle' | 'correct' | 'incorrect' | 'submitted' => {
    if (props.result === null) {
        return 'idle';
    }

    if (props.result.isCorrect === null) {
        return 'submitted';
    }

    return props.result.isCorrect ? 'correct' : 'incorrect';
});

function xsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function onRecorded(
    item: ActivityItem,
    payload: { blob: Blob; url: string; durationMs: number; mimeType: string },
): Promise<void> {
    uploading.value = true;
    recordings[item.id] = { url: payload.url, durationMs: payload.durationMs };

    const body = new FormData();
    const extension = payload.mimeType.includes('mp4')
        ? 'm4a'
        : payload.mimeType.includes('ogg')
          ? 'ogg'
          : 'webm';
    body.append('audio', payload.blob, `answer.${extension}`);
    body.append('recordable_type', props.recordable?.type ?? 'block');
    body.append('recordable_id', String(props.recordable?.id ?? props.blockId));
    body.append('duration_ms', String(payload.durationMs));

    try {
        const response = await fetch(storeRecording().url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const data = (await response.json()) as {
            id: number;
            duration_ms: number;
        };
        answers[item.id] = {
            recording_media_id: data.id,
            duration_ms: data.duration_ms ?? payload.durationMs,
        };
    } catch {
        form.setError(
            'answers',
            t('The recording could not be uploaded. Please try again.'),
        );
    } finally {
        uploading.value = false;
    }
}

function onRecordingReset(item: ActivityItem): void {
    delete answers[item.id];
    delete recordings[item.id];
}

function submit(): void {
    form.answers = { ...answers };
    form.started_at = startedAt;
    form.post(props.answerUrl, { preserveScroll: true });
}

function letter(index: number): string {
    return String.fromCharCode(65 + index);
}
</script>

<template>
    <section
        class="border-line bg-surface shadow-card mt-3 flex flex-col gap-5 rounded-lg border p-5 md:p-6"
        :aria-label="activity.label"
    >
        <header v-if="activity.prompt || activity.title">
            <p
                v-if="activity.skillLabel"
                class="text-brand-700 text-xs font-semibold tracking-[0.1em] uppercase"
            >
                {{ activity.skillLabel }}
            </p>
            <h2
                v-if="!isTest && activity.promptArabic"
                class="font-heading text-ink-night text-xl leading-7 font-semibold"
            >
                {{ activity.prompt ?? activity.title }}
            </h2>
            <!-- Every other prompt, in lessons and in tests, gets the
                 on-demand Show Meaning (client decision 2026-09-26). -->
            <MeaningText
                v-else
                as="h2"
                :text="activity.prompt ?? activity.title ?? ''"
                class="font-heading text-ink-night text-xl leading-7 font-semibold"
            />
            <template v-if="!isTest && activity.promptArabic">
                <ShowMeaningButton
                    size="sm"
                    :shown="meaning.shown.value"
                    :disabled="!meaning.enabled.value"
                    class="mt-3"
                    @toggle="meaning.toggle()"
                />
                <ShowMeaningPanel
                    :shown="meaning.shown.value"
                    :arabic="activity.promptArabic"
                    class="mt-3"
                />
            </template>
        </header>

        <div
            v-for="(item, index) in items"
            v-show="index === current"
            :key="item.id"
            class="flex flex-col gap-4"
        >
            <!-- Prompt media: picture, video poster, guest audio. -->
            <template
                v-for="key in [
                    'situation',
                    'context',
                    'question',
                    'sentence',
                    'subtitle',
                    'scenario',
                    'instruction',
                ]"
                :key="key"
            >
                <MeaningText
                    v-if="text(item, key)"
                    :text="text(item, key) ?? ''"
                    :class="
                        cn(
                            'text-ink text-lg leading-7',
                            key === 'instruction' && 'text-ink-slate text-base',
                        )
                    "
                />
            </template>
            <img
                v-if="media(item, 'image') || media(item, 'poster')"
                :src="(media(item, 'image') ?? media(item, 'poster'))?.url"
                :alt="
                    (media(item, 'image') ?? media(item, 'poster'))?.alt ?? ''
                "
                decoding="async"
                class="max-h-72 w-full rounded-md object-cover"
            />
            <div
                v-if="
                    audio(item, 'audio_text_audio') ||
                    audio(item, 'guest_audio_text_audio')
                "
                class="flex flex-wrap gap-3"
            >
                <AudioButton
                    :src="
                        (
                            audio(item, 'audio_text_audio') ??
                            audio(item, 'guest_audio_text_audio')
                        )?.normal
                    "
                    :text="
                        text(item, 'audio_text') ??
                        text(item, 'guest_audio_text') ??
                        undefined
                    "
                />
                <AudioButton
                    variant="slow"
                    :src="
                        (
                            audio(item, 'audio_text_audio') ??
                            audio(item, 'guest_audio_text_audio')
                        )?.slow
                    "
                    :text="
                        text(item, 'audio_text') ??
                        text(item, 'guest_audio_text') ??
                        undefined
                    "
                />
            </div>
            <ul
                v-if="Array.isArray(loose(item)['information'])"
                class="text-ink-graphite list-disc ps-5 text-base"
            >
                <li
                    v-for="(line, i) in loose(item)['information'] as string[]"
                    :key="i"
                >
                    <MeaningText as="span" :text="line" />
                </li>
            </ul>

            <!-- Option lists -->
            <div
                v-if="options(item).length > 0"
                class="flex flex-col gap-3"
                role="radiogroup"
                :aria-label="text(item, 'question') ?? activity.label"
            >
                <MeaningRow
                    v-for="(option, optionIndex) in options(item)"
                    :key="option.id"
                    :text="
                        option.label ?? option.text ?? option.audio_text ?? ''
                    "
                >
                    <OptionRow
                        :id="option.id"
                        :name="`item-${item.id}`"
                        :letter="letter(optionIndex)"
                        :selected="selected(item) === option.id"
                        :disabled="locked"
                        :state="rowState(item, option.id)"
                        @select="answers[item.id] = $event"
                    >
                        <span class="flex items-center gap-3">
                            <img
                                v-if="option.image"
                                :src="option.image.url"
                                :alt="option.image.alt ?? ''"
                                loading="lazy"
                                decoding="async"
                                class="size-16 shrink-0 rounded-sm object-cover"
                            />
                            <AudioButton
                                v-if="option.audio_text_audio"
                                size="sm"
                                :src="option.audio_text_audio.normal"
                                :text="option.audio_text"
                            />
                            <span>{{
                                option.label ?? option.text ?? option.audio_text
                            }}</span>
                        </span>
                    </OptionRow>
                </MeaningRow>
            </div>

            <!-- Listen & match -->
            <ol
                v-if="prompts(item).length > 0"
                class="flex list-none flex-col gap-3"
            >
                <li
                    v-for="prompt in prompts(item)"
                    :key="prompt.id"
                    class="border-line flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3"
                >
                    <AudioButton
                        size="sm"
                        :src="prompt.audio_text_audio?.normal"
                        :text="prompt.audio_text"
                    />
                    <MeaningText
                        as="span"
                        :text="prompt.audio_text ?? ''"
                        class="text-ink text-base"
                        wrapper-class="flex-1"
                    />
                    <select
                        :value="pairs(item)[prompt.id] ?? ''"
                        :disabled="locked"
                        class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface min-h-11 rounded-sm border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
                        :aria-label="
                            $t('Match :word', {
                                word: prompt.audio_text ?? '',
                            })
                        "
                        @change="
                            setPair(
                                item,
                                prompt.id,
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="" disabled>{{ $t('Choose…') }}</option>
                        <option
                            v-for="target in targets(item)"
                            :key="target.id"
                            :value="target.id"
                        >
                            {{ target.label }}
                        </option>
                    </select>
                </li>
            </ol>

            <!-- Ordering -->
            <ol
                v-if="orderable(item).length > 0"
                class="flex list-none flex-col gap-3"
            >
                <li
                    v-for="(entry, position) in orderedEntries(item)"
                    :key="entry.id"
                    class="border-line flex items-center gap-3 rounded-lg border px-4 py-3"
                >
                    <span
                        class="bg-brand-50 text-brand-700 grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold"
                    >
                        {{ position + 1 }}
                    </span>
                    <img
                        v-if="entry.image"
                        :src="entry.image.url"
                        :alt="entry.image.alt ?? ''"
                        loading="lazy"
                        decoding="async"
                        class="size-16 shrink-0 rounded-sm object-cover"
                    />
                    <MeaningText
                        as="span"
                        :text="entry.text ?? entry.caption ?? ''"
                        class="text-ink text-base"
                        wrapper-class="flex-1"
                    />
                    <AudioButton
                        v-if="entry.text_audio"
                        size="sm"
                        :src="entry.text_audio.normal"
                        :text="entry.text"
                    />
                    <button
                        type="button"
                        :disabled="locked || position === 0"
                        :aria-label="$t('Move up')"
                        class="bg-tint-grid text-ink hover:bg-line focus-visible:ring-brand-600/40 grid size-11 place-items-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40"
                        @click="move(item, entry.id, -1)"
                    >
                        <ArrowUp class="size-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        :disabled="
                            locked ||
                            position === orderedEntries(item).length - 1
                        "
                        :aria-label="$t('Move down')"
                        class="bg-tint-grid text-ink hover:bg-line focus-visible:ring-brand-600/40 grid size-11 place-items-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40"
                        @click="move(item, entry.id, 1)"
                    >
                        <ArrowDown class="size-5" aria-hidden="true" />
                    </button>
                </li>
            </ol>

            <!-- Speaking -->
            <div
                v-if="activity.type === 'speaking'"
                class="flex flex-col items-center gap-4"
            >
                <RecorderButton
                    :max-seconds="Number(loose(item)['max_seconds'] ?? 20)"
                    size="md"
                    @recorded="onRecorded(item, $event)"
                    @reset="onRecordingReset(item)"
                />
                <RecordingPlayer
                    v-if="recordings[item.id]"
                    :src="recordings[item.id]?.url ?? null"
                    :duration-ms="recordings[item.id]?.durationMs"
                    class="w-full max-w-md"
                />
                <p
                    v-if="uploading"
                    class="text-ink-slate text-sm"
                    aria-live="polite"
                >
                    {{ $t('Uploading your recording…') }}
                </p>
            </div>

            <!-- Writing -->
            <div v-if="activity.type === 'writing'" class="flex flex-col gap-2">
                <label
                    :for="`writing-${item.id}`"
                    class="text-ink text-sm font-semibold"
                >
                    {{ $t('Your reply') }}
                    <span class="text-ink-slate font-normal">
                        {{
                            $t('(at least :count words)', {
                                count: String(loose(item)['min_words'] ?? 20),
                            })
                        }}
                    </span>
                </label>
                <textarea
                    :id="`writing-${item.id}`"
                    :value="written(item)"
                    :disabled="locked"
                    rows="6"
                    class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface rounded-sm border px-3 py-2 text-base leading-6 focus-visible:ring-3 focus-visible:outline-none"
                    @input="
                        setWritten(
                            item,
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
            </div>

            <p
                v-if="
                    !isTest && result && result.perItem[item.id] !== undefined
                "
                :class="
                    cn(
                        'flex min-h-11 items-center gap-2 text-base font-semibold',
                        result.perItem[item.id] === true
                            ? 'text-success-text'
                            : result.perItem[item.id] === false
                              ? 'text-danger-text'
                              : 'text-brand-700',
                    )
                "
                aria-live="polite"
            >
                <Check
                    v-if="result.perItem[item.id] !== false"
                    class="size-5 shrink-0 stroke-[3]"
                    aria-hidden="true"
                />
                <X
                    v-else
                    class="size-5 shrink-0 stroke-[3]"
                    aria-hidden="true"
                />
                {{
                    result.perItem[item.id] === true
                        ? $t('Correct')
                        : result.perItem[item.id] === false
                          ? $t('Not quite')
                          : $t('Answer saved')
                }}
            </p>
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button
                    v-if="items.length > 1"
                    type="button"
                    :disabled="current === 0"
                    class="text-brand-600 min-h-11 px-2 text-sm font-semibold disabled:opacity-40"
                    @click="current = Math.max(0, current - 1)"
                >
                    {{ $t('Previous item') }}
                </button>
                <ActivityDots
                    v-if="items.length > 1"
                    :count="items.length"
                    :current="current"
                    :answered="answeredIndexes"
                />
                <button
                    v-if="items.length > 1"
                    type="button"
                    :disabled="current === items.length - 1"
                    class="text-brand-600 min-h-11 px-2 text-sm font-semibold disabled:opacity-40"
                    @click="current = Math.min(items.length - 1, current + 1)"
                >
                    {{ $t('Next item') }}
                </button>
            </div>

            <div class="flex items-center gap-4">
                <span
                    v-if="activity.attemptsLeft !== null"
                    class="text-ink-slate text-sm"
                >
                    {{
                        $t(':left of :total attempts left', {
                            left: activity.attemptsLeft,
                            total: activity.attemptsAllowed,
                        })
                    }}
                </span>
                <CheckButton
                    v-if="!isTest"
                    :disabled="!allAnswered || locked || uploading"
                    :loading="form.processing"
                    :state="overall"
                    @click="submit"
                />
            </div>
        </footer>

        <p
            v-if="form.errors.answers"
            class="text-danger-text text-sm"
            role="alert"
        >
            {{ form.errors.answers }}
        </p>
    </section>
</template>
