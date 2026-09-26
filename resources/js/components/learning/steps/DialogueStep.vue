<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import SidePhotoCard from '@/components/learning/SidePhotoCard.vue';
import TipCard from '@/components/learning/TipCard.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import { cn } from '@/lib/utils';
import type { LessonSummary, StepBlockOf } from '@/types';
import MeaningText from '@/components/learning/meaning/MeaningText.vue';

/*
 * Step 5, "Dialogue" (LESSON-06, CTRL-01..03; photo_5): the scene photo on
 * the left, a line-by-line conversation on the right — Step i/n with dots
 * and an advance arrow, the current line as a bubble with its speaker, the
 * Normal / Slow controls and Show Meaning. The last line reached lets the
 * footer Next complete the step.
 */
type Props = {
    lesson: LessonSummary;
    block: StepBlockOf<'dialogue'>;
};

const props = defineProps<Props>();

const lines = computed(() => props.block.settings.lines ?? []);
const index = ref(0);
const current = computed(() => lines.value[index.value] ?? null);
const total = computed(() => lines.value.length);

const subtitle = computed(() => props.block.settings.subtitle ?? null);
const caption = computed(() => props.block.settings.situation_caption ?? null);
const image = computed(() => props.block.settings.image ?? null);
const tip = computed(() => props.block.settings.tip ?? null);

const meaning = useShowMeaning();
const panelId = `dialogue-meaning-${useId()}`;

watch(index, () => meaning.hide());

function go(to: number): void {
    index.value = Math.min(Math.max(to, 0), total.value - 1);
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
            <SidePhotoCard
                :image="image"
                :caption="caption"
                class="h-72 md:h-[420px]"
            />

            <div class="flex min-w-0 flex-col gap-4">
                <div
                    class="border-line bg-surface shadow-card flex flex-col gap-4 rounded-lg border p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink font-heading font-semibold">
                            Step {{ index + 1 }} / {{ total }}
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                v-for="(line, i) in lines"
                                :key="i"
                                type="button"
                                :aria-label="`Line ${i + 1}`"
                                :aria-current="i === index"
                                class="focus-visible:ring-brand-600/40 size-2.5 rounded-full focus-visible:ring-3 focus-visible:outline-none"
                                :class="
                                    i === index
                                        ? 'bg-brand-600'
                                        : 'bg-brand-100'
                                "
                                @click="go(i)"
                            />
                        </div>
                        <button
                            type="button"
                            aria-label="Next line"
                            class="bg-brand-50 text-brand-600 hover:bg-brand-100 focus-visible:ring-brand-600/40 grid size-10 shrink-0 place-items-center rounded-full transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
                            :disabled="index >= total - 1"
                            @click="go(index + 1)"
                        >
                            <ArrowRight class="size-5" aria-hidden="true" />
                        </button>
                    </div>

                    <div
                        v-if="current"
                        class="bg-brand-50 flex items-start gap-3 rounded-xl p-4"
                    >
                        <img
                            v-if="image"
                            :src="image.url"
                            :alt="
                                current.speaker === 'guest' ? 'Guest' : 'Staff'
                            "
                            loading="lazy"
                            decoding="async"
                            class="size-16 shrink-0 rounded-full object-cover md:size-20"
                        />
                        <p
                            class="text-ink min-w-0 flex-1 text-lg leading-8 whitespace-pre-line md:text-xl"
                        >
                            {{ current.text }}
                        </p>
                        <AudioButton
                            size="sm"
                            :src="current.text_audio?.normal ?? null"
                            :text="current.text"
                            class="shrink-0"
                        />
                    </div>

                    <div v-if="current" class="flex gap-3">
                        <AudioButton
                            :src="current.text_audio?.normal ?? null"
                            variant="normal"
                            :text="current.text"
                            class="min-w-0 flex-1"
                        />
                        <AudioButton
                            :src="current.text_audio?.slow ?? null"
                            variant="slow"
                            :text="current.text"
                            class="min-w-0 flex-1"
                        />
                    </div>

                    <template v-if="current?.arabic">
                        <ShowMeaningButton
                            :shown="meaning.shown.value"
                            :controls="panelId"
                            :class="cn('mx-auto')"
                            @toggle="meaning.toggle()"
                        />
                        <ShowMeaningPanel
                            :id="panelId"
                            :shown="meaning.shown.value"
                            :arabic="current.arabic"
                        />
                    </template>
                </div>

                <TipCard v-if="tip" title="Tip" :text="tip" />
            </div>
        </div>
    </div>
</template>
