<script setup lang="ts">
import { Mic, Volume2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import PhrasebookButton from '@/components/learning/PhrasebookButton.vue';
import PronunciationResult from '@/components/learning/pronunciation/PronunciationResult.vue';
import PronunciationWordDrill from '@/components/learning/pronunciation/PronunciationWordDrill.vue';
import RecorderButton from '@/components/learning/RecorderButton.vue';
import RecordingPlayer from '@/components/learning/RecordingPlayer.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import SidePhotoCard from '@/components/learning/SidePhotoCard.vue';
import TipCard from '@/components/learning/TipCard.vue';
import { Spinner } from '@/components/ui/spinner';
import { usePronunciationCheck } from '@/composables/usePronunciationCheck';
import { useShowMeaning } from '@/composables/useShowMeaning';
import type { LessonSummary, PronunciationWord, StepBlockOf } from '@/types';

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
};

const props = defineProps<Props>();

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

watch(index, () => {
    meaning.hide();
    check.reset();
    drillWord.value = null;
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
}
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <p v-if="subtitle" class="text-ink-slate text-lg leading-7">
            {{ subtitle }}
        </p>

        <div class="grid gap-6 md:grid-cols-[minmax(0,48fr)_minmax(0,52fr)]">
            <div class="flex min-w-0 flex-col gap-4">
                <SidePhotoCard
                    v-if="current"
                    :image="current.image ?? null"
                    :caption="current.text"
                    class="h-64 md:h-[326px]"
                />
                <TipCard v-if="tip" title="Tip" :text="tip" />
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
                            Step 1: Listen
                        </h3>
                    </div>
                    <p class="text-ink-slate mt-1">
                        Click the button to hear the sentence.
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
                            Step 2: Repeat
                        </h3>
                    </div>
                    <p class="text-ink-slate mt-1">
                        Click the microphone and repeat the sentence.
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
                                v-if="currentRecording"
                                class="bg-app-alt rounded-lg p-3"
                            >
                                <p
                                    class="text-ink-slate mb-2 text-sm font-medium"
                                >
                                    Your recording
                                </p>
                                <RecordingPlayer
                                    :src="currentRecording.url"
                                    :duration-ms="currentRecording.durationMs"
                                />
                            </div>

                            <p
                                v-if="checking"
                                class="text-ink-slate flex items-center gap-2 text-sm"
                                aria-live="polite"
                                data-test="pronunciation-checking"
                            >
                                <Spinner class="size-4" />
                                Checking your pronunciation…
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
                        v-if="check.result.value && !checking"
                        :result="check.result.value"
                        class="mt-4"
                        @drill="drillWord = $event"
                    />

                    <PronunciationWordDrill
                        v-if="drillWord"
                        :key="`${check.result.value?.id}-${drillWord.index}`"
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

        <div
            v-if="items.length > 1"
            class="flex items-center justify-center gap-2"
        >
            <button
                v-for="(item, i) in items"
                :key="i"
                type="button"
                :aria-label="`Sentence ${i + 1}`"
                :aria-current="i === index"
                class="focus-visible:ring-brand-600/40 size-3 rounded-full focus-visible:ring-3 focus-visible:outline-none"
                :class="i === index ? 'bg-brand-600' : 'bg-brand-100'"
                @click="index = i"
            />
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <ShowMeaningButton
                v-if="current?.arabic"
                :shown="meaning.shown.value"
                @toggle="meaning.toggle()"
            />
            <PhrasebookButton
                v-if="current"
                :saved="false"
                :text="current.text"
                :arabic="current.arabic ?? null"
                :source-lesson-id="lesson.id"
            />
        </div>

        <ShowMeaningPanel
            v-if="current?.arabic"
            :shown="meaning.shown.value"
            :arabic="current.arabic"
            class="mx-auto w-full max-w-2xl"
        />
    </div>
</template>
