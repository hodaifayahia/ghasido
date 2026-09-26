<script setup lang="ts">
import { Gauge, MessageSquare, Play, Turtle } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import TipCard from '@/components/learning/TipCard.vue';
import VideoPlayer from '@/components/learning/video/VideoPlayer.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { LessonSummary, StepBlockOf } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * Step 6, "Video" (LESSON-06, CTRL-01..03, PERF-02; photo_6): the player on
 * the left, Video Controls (Normal / Slower speed → playbackRate) and an
 * Example from the video on the right. The mockup draws the example's Arabic
 * revealed; per CTRL-02 it ships hidden and opens on Show Meaning.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlockOf<'video'>;
};

const props = defineProps<Props>();

const subtitle = computed(() => props.block.settings.subtitle ?? null);
const video = computed(() => props.block.settings.video ?? null);
const poster = computed(() => props.block.settings.poster ?? null);
const controlsNote = computed(
    () =>
        props.block.settings.controls_note ??
        'Watch as many times as you need.',
);
const example = computed(() => props.block.settings.example ?? null);
const tip = computed(() => props.block.settings.tip ?? null);

const speed = ref(1);

const meaning = useShowMeaning();
const panelId = `video-example-${useId()}`;
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <MeaningText
            as="p"
            :text="subtitle"
            v-if="subtitle"
            class="text-ink-slate text-lg leading-7"
        />

        <div class="grid gap-6 md:grid-cols-[minmax(0,54fr)_minmax(0,46fr)]">
            <div class="flex min-w-0 flex-col gap-4">
                <VideoPlayer
                    :video="video"
                    :poster="poster"
                    :speed="speed"
                    class="aspect-video"
                />
                <TipCard v-if="tip" title="Tip" :text="tip" />
            </div>

            <div class="flex min-w-0 flex-col gap-4">
                <div
                    class="border-line bg-surface shadow-card rounded-lg border p-5"
                >
                    <div class="flex items-center gap-2">
                        <Play
                            class="text-brand-600 size-5 fill-current"
                            aria-hidden="true"
                        />
                        <h3 class="text-ink font-heading text-lg font-semibold">
                            Video Controls
                        </h3>
                    </div>
                    <p class="text-ink-slate mt-1">{{ controlsNote }}</p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            :aria-pressed="speed === 1"
                            :class="
                                cn(
                                    'ease-brand flex h-[62px] items-center justify-center gap-2 rounded-md text-base font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none',
                                    'focus-visible:ring-brand-600/40',
                                    speed === 1
                                        ? 'bg-brand-50 text-brand-700'
                                        : 'bg-app-alt text-ink-slate hover:bg-brand-50',
                                )
                            "
                            @click="speed = 1"
                        >
                            <Gauge class="size-5" aria-hidden="true" />
                            Normal Speed
                        </button>
                        <button
                            type="button"
                            :aria-pressed="speed === 0.75"
                            :class="
                                cn(
                                    'ease-brand flex h-[62px] items-center justify-center gap-2 rounded-md text-base font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:transition-none',
                                    'focus-visible:ring-success/40',
                                    speed === 0.75
                                        ? 'bg-success-tint text-success-text'
                                        : 'bg-app-alt text-ink-slate hover:bg-success-tint',
                                )
                            "
                            @click="speed = 0.75"
                        >
                            <Turtle
                                class="size-5 fill-current"
                                aria-hidden="true"
                            />
                            Slower Speed
                        </button>
                    </div>
                </div>

                <div
                    v-if="example"
                    class="border-line bg-surface shadow-card rounded-lg border p-5"
                >
                    <div class="flex items-center gap-2">
                        <MessageSquare
                            class="text-brand-600 size-5"
                            aria-hidden="true"
                        />
                        <h3 class="text-ink font-heading text-lg font-semibold">
                            Example from the Video
                        </h3>
                    </div>
                    <p v-if="example.note" class="text-ink-slate mt-1">
                        {{ example.note }}
                    </p>

                    <div
                        class="bg-brand-50 mt-3 flex items-start gap-3 rounded-xl p-4"
                    >
                        <p
                            class="text-ink min-w-0 flex-1 text-lg font-semibold"
                        >
                            {{ example.text }}
                        </p>
                        <AudioButton
                            size="sm"
                            :src="example.text_audio?.normal ?? null"
                            :text="example.text"
                            class="shrink-0"
                        />
                    </div>

                    <template v-if="example.arabic">
                        <ShowMeaningButton
                            :shown="meaning.shown.value"
                            :controls="panelId"
                            size="sm"
                            class="mt-3"
                            @toggle="meaning.toggle()"
                        />
                        <ShowMeaningPanel
                            :id="panelId"
                            :shown="meaning.shown.value"
                            :arabic="example.arabic"
                            class="mt-3"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
