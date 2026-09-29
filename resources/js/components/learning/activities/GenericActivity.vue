<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Check, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import ActivityFeedbackPanel from '@/components/learning/activities/ActivityFeedbackPanel.vue';
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
 * The client's ten types (client report 2026-09-29) add a flexible prompt
 * (picture, uploaded clip, generated audio, video), picture answers with an
 * optional pronunciation, matching pairs, a typed short answer and typed
 * blanks inside the sentence; a speaking item with a sentence to say is
 * checked for pronunciation. "Check" posts every answer verbatim as one
 * attempt; the page comes back with `result` and the rows show correct /
 * not quite with icon and text, and a spoken or written answer shows its
 * evaluation once the queued job has run (PERF-04). In test mode there is
 * no Show Meaning and no correctness (TEST-03).
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
    text_audio?: AudioPair;
    image?: MediaRef | null;
    audio?: MediaRef | null;
};

/** The words shown for an option, a matching side or a target. */
function wording(option: Option): string {
    return option.label ?? option.text ?? option.audio_text ?? '';
}

/** Answers drawn as pictures (an image-style flexible question). */
function pictureOptions(item: ActivityItem): boolean {
    return loose(item)['option_style'] === 'image';
}

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
        ? (stored.text ?? '')
        : '';
}

function setWritten(item: ActivityItem, value: string): void {
    answers[item.id] = { text: value };
}

// ------------------------------------------------------ typed answers

function typed(item: ActivityItem): string {
    const stored = answers[item.id];

    return typeof stored === 'string' ? stored : '';
}

function setTyped(item: ActivityItem, value: string): void {
    answers[item.id] = value;
}

type Segment = { kind: 'text'; text: string } | { kind: 'blank'; id: string };

/** The fill-in sentence cut at its `[[id]]` blanks. */
function segments(item: ActivityItem): Segment[] {
    const sentence = text(item, 'sentence') ?? '';
    const parts: Segment[] = [];
    let last = 0;

    for (const match of sentence.matchAll(/\[\[([A-Za-z0-9_-]+)\]\]/gu)) {
        const index = match.index ?? 0;

        if (index > last) {
            parts.push({ kind: 'text', text: sentence.slice(last, index) });
        }

        parts.push({ kind: 'blank', id: match[1] ?? '' });
        last = index + match[0].length;
    }

    if (last < sentence.length) {
        parts.push({ kind: 'text', text: sentence.slice(last) });
    }

    return parts;
}

function blankIds(item: ActivityItem): string[] {
    return segments(item)
        .filter(
            (part): part is { kind: 'blank'; id: string } =>
                part.kind === 'blank',
        )
        .map((part) => part.id);
}

function blankValue(item: ActivityItem, id: string): string {
    const stored = answers[item.id];

    return stored &&
        typeof stored === 'object' &&
        !Array.isArray(stored) &&
        !('recording_media_id' in stored) &&
        !('text' in stored)
        ? ((stored as Record<string, string>)[id] ?? '')
        : '';
}

function setBlank(item: ActivityItem, id: string, value: string): void {
    const stored = answers[item.id];
    const current =
        stored &&
        typeof stored === 'object' &&
        !Array.isArray(stored) &&
        !('recording_media_id' in stored) &&
        !('text' in stored)
            ? (stored as Record<string, string>)
            : {};

    answers[item.id] = { ...current, [id]: value };
}

/** The accepted answer revealed after a wrong practice answer. */
function revealed(item: ActivityItem): string | null {
    if (isTest.value || props.result === null) {
        return null;
    }

    const correct = props.result.correct[item.id];

    if (Array.isArray(correct)) {
        return correct.filter((entry) => typeof entry === 'string').join(' / ');
    }

    if (
        props.activity.type === 'fill_blank' &&
        correct !== null &&
        typeof correct === 'object'
    ) {
        return Object.values(correct as Record<string, unknown>)
            .filter((entry) => typeof entry === 'string')
            .join(', ');
    }

    return null;
}

// ------------------------------------------------ speaking without a mic

/** Items whose microphone is refused: the learner types instead (RESP-05). */
const typing = reactive<Record<string, boolean>>({});

function spokenText(item: ActivityItem): string {
    const stored = answers[item.id];

    return stored &&
        typeof stored === 'object' &&
        'recording_media_id' in stored
        ? (stored.text ?? '')
        : '';
}

function setSpokenText(item: ActivityItem, value: string): void {
    answers[item.id] = {
        recording_media_id: null,
        duration_ms: 0,
        text: value,
    };
}

const evaluated = computed(
    () =>
        !isTest.value &&
        props.result !== null &&
        (props.activity.type === 'speaking' ||
            props.activity.type === 'writing'),
);

function isAnswered(item: ActivityItem): boolean {
    const stored: RawAnswer | undefined = answers[item.id];

    if (stored === undefined) {
        return false;
    }

    switch (props.activity.type) {
        case 'listen_match':
        case 'matching':
            return (
                typeof stored === 'object' &&
                !Array.isArray(stored) &&
                Object.keys(stored).length === prompts(item).length
            );
        case 'short_answer':
            return typeof stored === 'string' && stored.trim() !== '';
        case 'fill_blank':
            return blankIds(item).every(
                (id) => blankValue(item, id).trim() !== '',
            );
        case 'writing':
            return (
                typeof stored === 'object' &&
                'text' in stored &&
                (stored.text ?? '').trim() !== ''
            );
        case 'speaking':
            return (
                typeof stored === 'object' &&
                'recording_media_id' in stored &&
                (stored.recording_media_id !== null ||
                    (stored.text ?? '').trim() !== '')
            );
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
                    v-if="
                        text(item, key) &&
                        !(key === 'sentence' && activity.type === 'fill_blank')
                    "
                    :text="text(item, key) ?? ''"
                    :class="
                        cn(
                            'text-ink text-lg leading-7',
                            key === 'instruction' && 'text-ink-slate text-base',
                        )
                    "
                />
            </template>
            <video
                v-if="media(item, 'video')"
                :src="media(item, 'video')?.url"
                :poster="media(item, 'poster')?.url"
                controls
                playsinline
                preload="metadata"
                class="max-h-80 w-full rounded-md bg-black"
                data-test="activity-video"
            />
            <img
                v-else-if="media(item, 'image') || media(item, 'poster')"
                :src="(media(item, 'image') ?? media(item, 'poster'))?.url"
                :alt="
                    (media(item, 'image') ?? media(item, 'poster'))?.alt ?? ''
                "
                decoding="async"
                class="max-h-72 w-full rounded-md object-cover"
            />
            <img
                v-if="media(item, 'video') && media(item, 'image')"
                :src="media(item, 'image')?.url"
                :alt="media(item, 'image')?.alt ?? ''"
                decoding="async"
                class="max-h-72 w-full rounded-md object-cover"
            />
            <div
                v-if="media(item, 'audio')"
                class="flex items-center gap-3"
                data-test="activity-prompt-clip"
            >
                <AudioButton :src="media(item, 'audio')?.url ?? null" />
            </div>
            <!-- Speaking: the sentence to say, with its audio. -->
            <div
                v-if="text(item, 'expected_text')"
                class="bg-brand-50 flex flex-wrap items-center gap-3 rounded-lg p-4"
            >
                <p class="text-ink min-w-0 flex-1 text-lg leading-7">
                    <span class="text-brand-700 block text-sm font-semibold">
                        {{ $t('Say this sentence:') }}
                    </span>
                    {{ text(item, 'expected_text') }}
                </p>
                <AudioButton
                    v-if="audio(item, 'expected_text_audio')"
                    :src="audio(item, 'expected_text_audio')?.normal"
                    :text="text(item, 'expected_text') ?? undefined"
                />
                <AudioButton
                    v-if="audio(item, 'expected_text_audio')"
                    variant="slow"
                    :src="audio(item, 'expected_text_audio')?.slow"
                    :text="text(item, 'expected_text') ?? undefined"
                />
            </div>
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
                    :text="wording(option)"
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
                                :alt="option.image.alt ?? wording(option)"
                                loading="lazy"
                                decoding="async"
                                :class="
                                    cn(
                                        'shrink-0 rounded-sm object-cover',
                                        pictureOptions(item)
                                            ? 'size-24 md:size-28'
                                            : 'size-16',
                                    )
                                "
                            />
                            <AudioButton
                                v-if="option.audio_text_audio"
                                size="sm"
                                :src="option.audio_text_audio.normal"
                                :text="option.audio_text"
                            />
                            <span>{{ wording(option) }}</span>
                        </span>
                    </OptionRow>
                </MeaningRow>
            </div>

            <!-- Matching: the right-hand sides, lettered -->
            <ul
                v-if="
                    prompts(item).length > 0 &&
                    targets(item).some((target) => target.image)
                "
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                :aria-label="$t('Answers to match')"
            >
                <li
                    v-for="(target, targetIndex) in targets(item)"
                    :key="target.id"
                    class="border-line flex flex-col items-center gap-2 rounded-lg border p-2 text-center"
                >
                    <img
                        v-if="target.image"
                        :src="target.image.url"
                        :alt="target.image.alt ?? wording(target)"
                        loading="lazy"
                        decoding="async"
                        class="aspect-[4/3] w-full rounded-sm object-cover"
                    />
                    <span class="text-ink text-sm font-semibold">
                        {{ letter(targetIndex) }}
                        <template v-if="wording(target)">
                            — {{ wording(target) }}
                        </template>
                    </span>
                </li>
            </ul>

            <!-- Listen & match / Matching -->
            <ol
                v-if="prompts(item).length > 0"
                class="flex list-none flex-col gap-3"
            >
                <li
                    v-for="prompt in prompts(item)"
                    :key="prompt.id"
                    class="border-line flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3"
                >
                    <img
                        v-if="prompt.image"
                        :src="prompt.image.url"
                        :alt="prompt.image.alt ?? wording(prompt)"
                        loading="lazy"
                        decoding="async"
                        class="size-16 shrink-0 rounded-sm object-cover"
                    />
                    <AudioButton
                        v-if="prompt.audio"
                        size="sm"
                        :src="prompt.audio.url"
                    />
                    <AudioButton
                        v-else-if="
                            prompt.audio_text_audio?.normal ||
                            prompt.text_audio?.normal ||
                            prompt.audio_text
                        "
                        size="sm"
                        :src="
                            prompt.audio_text_audio?.normal ??
                            prompt.text_audio?.normal
                        "
                        :text="prompt.audio_text"
                    />
                    <MeaningText
                        as="span"
                        :text="wording(prompt)"
                        class="text-ink text-base"
                        wrapper-class="flex-1"
                    />
                    <select
                        :value="pairs(item)[prompt.id] ?? ''"
                        :disabled="locked"
                        class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface min-h-11 rounded-sm border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
                        :aria-label="
                            $t('Match :word', {
                                word: wording(prompt),
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
                            v-for="(target, targetIndex) in targets(item)"
                            :key="target.id"
                            :value="target.id"
                        >
                            {{
                                targets(item).some((entry) => entry.image)
                                    ? `${letter(targetIndex)}${wording(target) ? ` — ${wording(target)}` : ''}`
                                    : wording(target)
                            }}
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

            <!-- Short answer -->
            <div
                v-if="activity.type === 'short_answer'"
                class="flex flex-col gap-2"
            >
                <label
                    :for="`typed-${item.id}`"
                    class="text-ink text-sm font-semibold"
                >
                    {{ $t('Your answer') }}
                </label>
                <input
                    :id="`typed-${item.id}`"
                    :value="typed(item)"
                    type="text"
                    :disabled="locked"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    data-test="short-answer-input"
                    class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface min-h-12 rounded-sm border px-3 text-base focus-visible:ring-3 focus-visible:outline-none"
                    @input="
                        setTyped(
                            item,
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </div>

            <!-- Fill in the blank: an input in every blank -->
            <p
                v-if="activity.type === 'fill_blank'"
                class="text-ink text-lg leading-[2.6]"
                data-test="fill-blank-sentence"
            >
                <template
                    v-for="(part, partIndex) in segments(item)"
                    :key="partIndex"
                >
                    <span v-if="part.kind === 'text'">{{ part.text }}</span>
                    <input
                        v-else
                        :value="blankValue(item, part.id)"
                        type="text"
                        :disabled="locked"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        :aria-label="
                            $t('Blank :number', {
                                number: blankIds(item).indexOf(part.id) + 1,
                            })
                        "
                        :data-test="`fill-blank-${part.id}`"
                        class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface mx-1 inline-block min-h-11 w-32 max-w-full rounded-sm border px-2 align-middle text-base focus-visible:ring-3 focus-visible:outline-none"
                        @input="
                            setBlank(
                                item,
                                part.id,
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </template>
            </p>

            <!-- Speaking -->
            <div
                v-if="activity.type === 'speaking'"
                class="flex flex-col items-center gap-4"
            >
                <RecorderButton
                    v-if="!locked"
                    :max-seconds="Number(loose(item)['max_seconds'] ?? 20)"
                    size="md"
                    @recorded="onRecorded(item, $event)"
                    @reset="onRecordingReset(item)"
                    @unavailable="typing[item.id] = true"
                />
                <div
                    v-if="typing[item.id]"
                    class="flex w-full max-w-md flex-col gap-2"
                >
                    <label
                        :for="`spoken-${item.id}`"
                        class="text-ink text-sm font-semibold"
                    >
                        {{ $t('Type what you would say') }}
                    </label>
                    <textarea
                        :id="`spoken-${item.id}`"
                        :value="spokenText(item)"
                        :disabled="locked"
                        rows="3"
                        class="border-line-strong text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 bg-surface rounded-sm border px-3 py-2 text-base leading-6 focus-visible:ring-3 focus-visible:outline-none"
                        @input="
                            setSpokenText(
                                item,
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </div>
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
            <p
                v-if="
                    result &&
                    result.perItem[item.id] === false &&
                    revealed(item)
                "
                class="text-ink-slate text-base"
            >
                {{ $t('Correct answer') }}:
                <span class="text-success-text font-semibold">
                    {{ revealed(item) }}
                </span>
            </p>
        </div>

        <ActivityFeedbackPanel
            v-if="evaluated && result"
            :status="result.aiStatus"
            :feedback="result.feedback"
        />

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
