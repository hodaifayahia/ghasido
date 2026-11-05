<script setup lang="ts">
import { ArrowLeft, ArrowRight, Mic, Volume2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import AttemptHistoryCard from '@/components/learning/attempts/AttemptHistoryCard.vue';
import { fromPronunciation } from '@/components/learning/attempts/attemptHistory';
import type { AttemptHistoryRow } from '@/components/learning/attempts/attemptHistory';
import AudioButton from '@/components/learning/AudioButton.vue';
import PhrasebookButton from '@/components/learning/PhrasebookButton.vue';
import PronunciationResult from '@/components/learning/pronunciation/PronunciationResult.vue';
import PronunciationWordDrill from '@/components/learning/pronunciation/PronunciationWordDrill.vue';
import RecorderButton from '@/components/learning/RecorderButton.vue';
import RecordingPlayer from '@/components/learning/RecordingPlayer.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import SidePhotoCard from '@/components/learning/SidePhotoCard.vue';
import TipCard from '@/components/learning/TipCard.vue';
import { Spinner } from '@/components/ui/spinner';
import { usePronunciationCheck } from '@/composables/usePronunciationCheck';
import { useShowMeaning } from '@/composables/useShowMeaning';
import type {
    LessonSummary,
    PronunciationHistoryEntry,
    PronunciationWord,
    StepBlockOf,
} from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';
import StoredMeaningPanel from '@/components/learning/meaning/StoredMeaningPanel.vue';

/*
 * Step 4, "Listen & Repeat" (LESSON-06, RESP-05, TEST-07, DATA-02; photo_4):
 * the sentence photo with its caption on the left, a two-step card on the
 * right — Listen (Normal / Slow) then Repeat. The recording is uploaded to
 * the pronunciation check (spec 0006), which keeps it (DATA-02) and returns
 * a verdict per word, then the coach's tip; a weak word opens a drill for
 * that word alone. A denied microphone is handled non-blockingly by
 * RecorderButton.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlockOf<'listen_repeat'>;
    /** The learner's saved checks on this step, newest first. */
    history?: PronunciationHistoryEntry[];
};

const props = withDefaults(defineProps<Props>(), { history: () => [] });

const items = computed(() => props.block.settings.items ?? []);
const index = ref(0);
const current = computed(() => items.value[index.value] ?? null);
const subtitle = computed(() => props.block.settings.subtitle ?? null);
const tip = computed(() => props.block.settings.tip ?? null);

const meaning = useShowMeaning();
const check = usePronunciationCheck();

type Recorded = { url: string; durationMs: number };
const recordings = reactive<Record<number, Recorded>>({});
const currentRecording = computed(() => recordings[index.value] ?? null);
const drillWord = ref<PronunciationWord | null>(null);

const checking = computed(
    () => check.busy.value && check.result.value?.status !== 'done',
);

/*
 * Saved checks come back from the server after a logout (client report
 * 2026-11-05; PROG-01, DATA-02): the latest result of each sentence is
 * shown until the learner records again, and every try stays listed to
 * compare. Checks made on this visit join the list as they finish.
 */
const fresh = ref<PronunciationHistoryEntry[]>([]);
const viewing = ref<PronunciationHistoryEntry | null>(null);
const savedForItem = computed(() =>
    [...fresh.value, ...props.history].filter(
        (entry) => entry.item === index.value,
    ),
);
const shownCheck = computed(
    () => check.result.value ?? viewing.value ?? savedForItem.value[0] ?? null,
);
const shownRecording = computed((): Recorded | null => {
    if (check.result.value !== null || checking.value) {
        return currentRecording.value;
    }

    const saved = viewing.value ?? savedForItem.value[0] ?? null;

    return saved?.recordingUrl
        ? { url: saved.recordingUrl, durationMs: 0 }
        : currentRecording.value;
});
const historyRows = computed(() => fromPronunciation(savedForItem.value));

watch(
    () => check.result.value,
    (result) => {
        if (
            result === null ||
            !result.settled ||
            fresh.value.some((entry) => entry.id === result.id) ||
            props.history.some((entry) => entry.id === result.id)
        ) {
            return;
        }

        fresh.value.unshift({
            ...result,
            item: index.value,
            createdAt: new Date().toISOString(),
            recordingUrl: currentRecording.value?.url ?? null,
        });
    },
    { deep: true },
);

function viewSaved(row: AttemptHistoryRow): void {
    const entry = savedForItem.value.find((saved) => saved.id === row.id);

    if (entry) {
        check.reset();
        drillWord.value = null;
        viewing.value = entry;
    }
}

watch(index, () => {
    meaning.hide();
    check.reset();
    drillWord.value = null;
    viewing.value = null;
});

function onRecorded(payload: {
    blob: Blob;
    url: string;
    durationMs: number;
    mimeType: string;
}): void {
    recordings[index.value] = {
        url: payload.url,
        durationMs: payload.durationMs,
    };
    drillWord.value = null;
    viewing.value = null;

    void check.check(
        {
            lessonId: props.lesson.id,
            blockId: props.block.id,
            item: index.value,
        },
        payload,
    );
}

function onRecordAgain(): void {
    check.reset();
    drillWord.value = null;
    viewing.value = null;
}
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <MeaningText
            as="p"
            :text="subtitle"
            v-if="subtitle"
            class="text-ink-slate text-lg leading-7"
        />

        <div class="grid gap-6 md:grid-cols-[minmax(0,48fr)_minmax(0,52fr)]">
            <div class="flex min-w-0 flex-col gap-4">
                <SidePhotoCard
                    v-if="current"
                    :image="current.image ?? null"
                    :caption="current.text"
                    class="h-64 md:h-[326px]"
                />
                <TipCard v-if="tip" :title="$t('Tip')" :text="tip" />
            </div>

            <div
                class="border-line bg-surface shadow-card flex min-w-0 flex-col gap-5 rounded-lg border p-5"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <Volume2
                            class="text-brand-600 size-6"
                            aria-hidden="true"
                        />
                        <h3 class="text-ink font-heading text-lg font-semibold">
                            {{ $t('Step 1: Listen') }}
                        </h3>
                    </div>
                    <p class="text-ink-slate mt-1">
                        {{ $t('Click the button to hear the sentence.') }}
                    </p>
                    <div class="mt-3 flex gap-3">
                        <AudioButton
                            :src="current?.text_audio?.normal ?? null"
                            variant="normal"
                            :text="current?.text"
                            class="h-16 min-w-0 flex-1"
                        />
                        <AudioButton
                            :src="current?.text_audio?.slow ?? null"
                            variant="slow"
                            :text="current?.text"
                            class="h-16 min-w-0 flex-1"
                        />
                    </div>
                </div>

                <hr class="border-line" />

                <div>
                    <div class="flex items-center gap-2">
                        <Mic class="text-brand-600 size-6" aria-hidden="true" />
                        <h3 class="text-ink font-heading text-lg font-semibold">
                            {{ $t('Step 2: Repeat') }}
                        </h3>
                    </div>
                    <p class="text-ink-slate mt-1">
                        {{
                            $t('Click the microphone and repeat the sentence.')
                        }}
                    </p>

                    <div
                        class="mt-4 flex flex-col items-center gap-5 md:flex-row md:items-start"
                    >
                        <RecorderButton
                            :key="index"
                            size="lg"
                            class="shrink-0"
                            @recorded="onRecorded"
                            @reset="onRecordAgain"
                        />

                        <div class="flex w-full min-w-0 flex-1 flex-col gap-3">
                            <div
                                v-if="shownRecording"
                                class="bg-app-alt rounded-lg p-3"
                            >
                                <p
                                    class="text-ink-slate mb-2 text-sm font-medium"
                                >
                                    {{ $t('Your recording') }}
                                </p>
                                <RecordingPlayer
                                    :src="shownRecording.url"
                                    :duration-ms="
                                        shownRecording.durationMs || null
                                    "
                                />
                            </div>

                            <p
                                v-if="checking"
                                class="text-ink-slate flex items-center gap-2 text-sm"
                                aria-live="polite"
                                data-test="pronunciation-checking"
                            >
                                <Spinner class="size-4" />
                                {{ $t('Checking your pronunciation…') }}
                            </p>
                            <p
                                v-else-if="check.error.value"
                                class="bg-danger-tint text-danger-text rounded-lg px-4 py-3 text-sm font-semibold"
                                role="alert"
                            >
                                {{ check.error.value }}
                            </p>
                        </div>
                    </div>

                    <PronunciationResult
                        v-if="shownCheck && !checking"
                        :result="shownCheck"
                        class="mt-4"
                        @drill="drillWord = $event"
                    />

                    <PronunciationWordDrill
                        v-if="drillWord"
                        :key="`${shownCheck?.id}-${drillWord.index}`"
                        :word="drillWord"
                        :lesson-id="lesson.id"
                        :block-id="block.id"
                        :item="index"
                        class="mt-4"
                        @close="drillWord = null"
                    />
                </div>
            </div>
        </div>

        <AttemptHistoryCard
            v-if="historyRows.length > 0"
            :rows="historyRows"
            :current-id="checking ? null : (shownCheck?.id ?? null)"
            @select="viewSaved"
        />

        <!-- Arrows beside the dots, as in the Dialogue step, so learners see
             there is a next sentence (client request 2026-11-05). -->
        <div
            v-if="items.length > 1"
            class="flex items-center justify-center gap-3"
        >
            <button
                type="button"
                :aria-label="$t('Previous sentence')"
                class="bg-brand-50 text-brand-600 hover:bg-brand-100 focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-40"
                :disabled="index === 0"
                data-test="listen-repeat-previous"
                @click="index = Math.max(0, index - 1)"
            >
                <ArrowLeft class="size-5 rtl:rotate-180" aria-hidden="true" />
            </button>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <button
                    v-for="(item, i) in items"
                    :key="i"
                    type="button"
                    :aria-label="$t('Sentence :number', { number: i + 1 })"
                    :aria-current="i === index"
                    class="focus-visible:ring-brand-600/40 size-3 rounded-full focus-visible:ring-3 focus-visible:outline-none"
                    :class="i === index ? 'bg-brand-600' : 'bg-brand-100'"
                    @click="index = i"
                />
            </div>
            <button
                type="button"
                :aria-label="$t('Next sentence')"
                class="bg-brand-50 text-brand-600 hover:bg-brand-100 focus-visible:ring-brand-600/40 grid size-11 shrink-0 place-items-center rounded-full transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-40"
                :disabled="index >= items.length - 1"
                data-test="listen-repeat-next"
                @click="index = Math.min(items.length - 1, index + 1)"
            >
                <ArrowRight class="size-5 rtl:rotate-180" aria-hidden="true" />
            </button>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <!-- The sentence's meaning is its stored translation in the
                 learner's helper language, fetched on the tap (client
                 request 2026-11-05). -->
            <ShowMeaningButton
                v-if="current"
                :shown="meaning.shown.value"
                @toggle="meaning.toggle()"
            />
            <PhrasebookButton
                v-if="current"
                :saved="false"
                :text="current.text"
                :source-lesson-id="lesson.id"
            />
        </div>

        <StoredMeaningPanel
            v-if="current"
            :key="index"
            :text="current.text"
            :shown="meaning.shown.value"
            class="mx-auto w-full max-w-2xl"
        />
    </div>
</template>
