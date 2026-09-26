<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Bot, ListChecks, Trophy } from '@lucide/vue';
import { computed, useId } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import PhrasebookButton from '@/components/learning/PhrasebookButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import SidePhotoCard from '@/components/learning/SidePhotoCard.vue';
import TaskCard from '@/components/learning/TaskCard.vue';
import TipCard from '@/components/learning/TipCard.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { AudioPair, LessonSummary, MediaRef, StepBlock } from '@/types';
import MeaningRow from '@/components/learning/meaning/MeaningRow.vue';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * The fallback renderer for any block type without its own step component
 * yet (LESSON-01, LESSON-06, CTRL-01..03; spec 0003 H.3): the subtitle, the
 * block's picture, its playable sentence with both speeds, its Arabic
 * behind Show Meaning, then whatever the block owns — lexicon items,
 * practice-activity cards, AI scenarios or the lesson summary — as plain
 * lists, so every seeded step is reachable end to end. The dedicated
 * step components replace this type by type.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlock;
};

const props = defineProps<Props>();

const meaning = useShowMeaning();
const panelId = `meaning-${useId()}`;

type Loose = Record<string, unknown>;

const settings = computed((): Loose => props.block.settings as Loose);

function str(key: string): string | null {
    const value = settings.value[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function media(key: string): MediaRef | null {
    const value = settings.value[key];

    return value !== null && typeof value === 'object' && 'url' in value
        ? (value as MediaRef)
        : null;
}

function audio(key: string): AudioPair | null {
    const value = settings.value[key];

    return value !== null && typeof value === 'object' && 'normal' in value
        ? (value as AudioPair)
        : null;
}

const subtitle = computed(() => str('subtitle'));
const body = computed(() => str('body') ?? str('encouragement'));
const arabic = computed(() => str('arabic'));
const sentence = computed(() => str('audio_text') ?? str('text'));
const sentenceAudio = computed(
    () => audio('audio_text_audio') ?? audio('text_audio'),
);
const picture = computed(() => media('image') ?? media('poster'));
const tip = computed(() => str('tip'));
const quote = computed(() => str('quote') ?? str('closing_quote'));

const lines = computed(() => {
    const value = settings.value['lines'];

    return Array.isArray(value)
        ? (value as { speaker: string; text: string; text_audio?: AudioPair }[])
        : [];
});

const items = computed(() => {
    const value = settings.value['items'];

    return Array.isArray(value)
        ? (value as {
              text: string;
              text_audio?: AudioPair;
              image?: MediaRef | null;
          }[])
        : [];
});

const summary = computed(() => props.block.summary);
</script>

<template>
    <div class="mt-3 flex flex-col gap-4">
        <MeaningText
            as="p"
            :text="subtitle"
            v-if="subtitle"
            class="text-ink-graphite text-lg leading-7"
        />

        <div
            :class="
                cn(
                    'grid gap-4',
                    picture && 'md:grid-cols-[minmax(0,5fr)_minmax(0,4fr)]',
                )
            "
        >
            <SidePhotoCard
                v-if="picture"
                :image="picture"
                :caption="str('situation_caption')"
                class="h-64 md:h-[400px]"
            />

            <div class="flex min-w-0 flex-col gap-4">
                <TaskCard
                    v-if="sentence || body"
                    :title="sentence ?? block.heading"
                    :text="sentence ? null : block.heading"
                >
                    <MeaningText
                        as="p"
                        :text="body"
                        v-if="body"
                        class="text-ink-graphite text-lg leading-7 whitespace-pre-line"
                    />
                    <div v-if="sentenceAudio" class="mt-4 flex flex-wrap gap-3">
                        <AudioButton
                            :src="sentenceAudio.normal"
                            variant="normal"
                            :text="sentence ?? undefined"
                        />
                        <AudioButton
                            :src="sentenceAudio.slow"
                            variant="slow"
                            :text="sentence ?? undefined"
                        />
                    </div>
                    <template v-if="sentence">
                        <PhrasebookButton
                            :saved="false"
                            :text="sentence"
                            :arabic="arabic"
                            :source-lesson-id="lesson.id"
                            class="mt-3"
                        />
                    </template>
                    <template v-if="arabic">
                        <ShowMeaningButton
                            :shown="meaning.shown.value"
                            :controls="panelId"
                            class="mt-3"
                            @toggle="meaning.toggle()"
                        />
                        <ShowMeaningPanel
                            :id="panelId"
                            :shown="meaning.shown.value"
                            :arabic="arabic"
                            class="mt-3"
                        />
                    </template>
                </TaskCard>

                <TaskCard v-if="lines.length > 0" title="Dialogue">
                    <ol class="flex list-none flex-col gap-3">
                        <li
                            v-for="(line, index) in lines"
                            :key="index"
                            :class="
                                cn(
                                    'flex items-center gap-3 rounded-lg px-4 py-3',
                                    line.speaker === 'guest'
                                        ? 'bg-tint-grid'
                                        : 'bg-brand-50',
                                )
                            "
                        >
                            <AudioButton
                                size="sm"
                                :src="line.text_audio?.normal ?? null"
                                :text="line.text"
                            />
                            <span class="min-w-0">
                                <span
                                    class="text-ink-slate block text-xs font-semibold uppercase"
                                >
                                    {{ line.speaker }}
                                </span>
                                <span
                                    class="text-ink text-lg leading-7 whitespace-pre-line"
                                >
                                    {{ line.text }}
                                </span>
                            </span>
                        </li>
                    </ol>
                </TaskCard>

                <TaskCard v-if="items.length > 0" title="Listen and repeat">
                    <ul class="flex list-none flex-col gap-3">
                        <li
                            v-for="(item, index) in items"
                            :key="index"
                            class="flex flex-wrap items-center gap-3"
                        >
                            <MeaningText
                                as="span"
                                :text="item.text"
                                class="text-ink flex-1 text-lg leading-7"
                            />
                            <AudioButton
                                size="sm"
                                :src="item.text_audio?.normal ?? null"
                                :text="item.text"
                            />
                            <AudioButton
                                size="sm"
                                variant="slow"
                                :src="item.text_audio?.slow ?? null"
                                :text="item.text"
                            />
                        </li>
                    </ul>
                </TaskCard>

                <TaskCard
                    v-if="block.lexicon.length > 0"
                    :title="str('side_title') ?? block.heading"
                >
                    <ul class="flex list-none flex-col gap-2">
                        <li
                            v-for="entry in block.lexicon"
                            :key="entry.id"
                            class="border-line flex items-center gap-3 rounded-md border px-3 py-2"
                        >
                            <img
                                v-if="entry.image"
                                :src="entry.image.url"
                                :alt="entry.image.alt ?? ''"
                                loading="lazy"
                                decoding="async"
                                class="size-14 shrink-0 rounded-sm object-cover"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="text-ink block text-lg font-semibold"
                                >
                                    {{ entry.text }}
                                    <span
                                        v-if="entry.partOfSpeech"
                                        class="text-ink-slate text-sm font-normal"
                                    >
                                        ({{ entry.partOfSpeech }})
                                    </span>
                                </span>
                                <span
                                    v-if="entry.ipa"
                                    class="text-ink-slate block text-sm"
                                >
                                    {{ entry.ipa }}
                                </span>
                            </span>
                            <AudioButton
                                size="sm"
                                :src="entry.audio.normal"
                                :text="entry.text"
                            />
                            <AudioButton
                                size="sm"
                                variant="slow"
                                :src="entry.audio.slow"
                                :text="entry.text"
                            />
                        </li>
                    </ul>
                </TaskCard>

                <TaskCard
                    v-if="block.activities.length > 0"
                    :title="block.heading"
                    :text="str('motto')"
                    :icon="ListChecks"
                    tone="gold"
                >
                    <ul class="grid list-none gap-3 md:grid-cols-2">
                        <li
                            v-for="(card, index) in block.activities"
                            :key="card.id"
                        >
                            <MeaningRow :text="card.description">
                                <Link
                                    :href="card.url"
                                    class="border-line hover:border-brand-300 focus-visible:ring-brand-600/40 flex min-h-14 items-center gap-3 rounded-lg border px-4 py-3 focus-visible:ring-3 focus-visible:outline-none"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="text-ink block text-base font-semibold"
                                        >
                                            {{ index + 1 }}. {{ card.label }}
                                        </span>
                                        <span
                                            class="text-ink-slate block text-sm"
                                        >
                                            {{ card.description }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="card.done"
                                        class="rounded-pill bg-success-tint text-success-text px-2.5 py-1 text-xs font-semibold"
                                    >
                                        Done
                                    </span>
                                    <ArrowRight
                                        class="text-brand-600 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </MeaningRow>
                        </li>
                    </ul>
                </TaskCard>

                <TaskCard
                    v-if="block.scenarios.length > 0"
                    :title="block.heading"
                    :icon="Bot"
                    tone="ai"
                >
                    <ul class="grid list-none gap-3 md:grid-cols-2">
                        <li
                            v-for="scenario in block.scenarios"
                            :key="scenario.id"
                        >
                            <MeaningRow :text="scenario.title">
                                <Link
                                    :href="scenario.url"
                                    class="border-line hover:border-brand-300 focus-visible:ring-brand-600/40 flex min-h-14 items-center gap-3 rounded-lg border px-4 py-3 focus-visible:ring-3 focus-visible:outline-none"
                                >
                                    <img
                                        v-if="scenario.thumbnail"
                                        :src="scenario.thumbnail.url"
                                        :alt="scenario.thumbnail.alt ?? ''"
                                        loading="lazy"
                                        decoding="async"
                                        class="size-14 shrink-0 rounded-sm object-cover"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="text-ink block text-base font-semibold"
                                        >
                                            {{ scenario.title }}
                                        </span>
                                        <span
                                            class="text-ink-slate block text-sm"
                                        >
                                            {{ scenario.attemptsLeft }} of
                                            {{ scenario.attemptsAllowed }}
                                            attempts left
                                        </span>
                                    </span>
                                    <ArrowRight
                                        class="text-brand-600 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </MeaningRow>
                        </li>
                    </ul>
                </TaskCard>

                <TaskCard
                    v-if="summary"
                    :title="block.heading"
                    :icon="Trophy"
                    tone="success"
                >
                    <p class="text-ink text-lg leading-7">
                        You have completed {{ summary.lessonsCompleted }} of
                        {{ summary.lessonsTotal }} lessons.
                    </p>
                    <MeaningText
                        as="p"
                        :text="quote"
                        v-if="quote"
                        class="font-quote text-ink-graphite mt-3 text-lg whitespace-pre-line italic"
                    />
                    <Link
                        v-if="summary.nextLesson"
                        :href="summary.nextLesson.url"
                        class="text-brand-600 mt-4 inline-flex min-h-11 items-center gap-2 font-semibold underline-offset-4 hover:underline"
                    >
                        Next lesson: {{ summary.nextLesson.title }}
                        <ArrowRight class="size-5" aria-hidden="true" />
                    </Link>
                </TaskCard>

                <TipCard v-if="tip" :text="tip" />
            </div>
        </div>
    </div>
</template>
