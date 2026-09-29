<script setup lang="ts">
import { useId } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import PhrasebookButton from '@/components/learning/PhrasebookButton.vue';
import ShowMeaningPanel from '@/components/learning/ShowMeaningPanel.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import type { LexiconEntry } from '@/types';

/*
 * The featured expression card (photo_3): a wide photo on top, then the
 * sentence with an inline speaker, the IPA, the Normal | Slow | Save row and
 * Show Meaning (CTRL-01..03, PHRASE-02). Keyed by id so Show Meaning resets
 * when the side list swaps the feature.
 */
type Props = {
    item: LexiconEntry;
    lessonId: number;
};

const props = defineProps<Props>();

const meaning = useShowMeaning();
const panelId = `expr-meaning-${useId()}`;
</script>

<template>
    <div class="border-line bg-surface shadow-card rounded-lg border p-5">
        <img
            v-if="item.image"
            :src="item.image.url"
            :alt="item.image.alt ?? item.text"
            loading="lazy"
            decoding="async"
            class="h-52 w-full rounded-xl object-cover md:h-[260px]"
        />

        <div class="mt-4 flex flex-col items-center gap-1 text-center">
            <div class="flex items-center gap-3">
                <AudioButton
                    size="sm"
                    :src="item.audio.normal"
                    :text="item.text"
                    class="shrink-0"
                />
                <p
                    class="text-ink text-2xl leading-tight font-bold md:text-[30px]"
                >
                    {{ item.text }}
                </p>
                <button
                    v-if="item.showMeaning && item.meaning"
                    type="button"
                    :aria-label="
                        meaning.shown.value
                            ? $t('Hide Meaning')
                            : $t('Show the meaning of :text', {
                                  text: item.text,
                              })
                    "
                    :aria-pressed="meaning.shown.value"
                    :aria-controls="panelId"
                    class="focus-visible:ring-brand-600/40 bg-tint-grid text-ink-slate hover:bg-brand-50 grid size-11 shrink-0 place-items-center rounded-full focus-visible:ring-3 focus-visible:outline-none"
                    @click="meaning.toggle()"
                >
                    <component
                        :is="meaning.shown.value ? EyeOff : Eye"
                        class="size-5"
                        aria-hidden="true"
                    />
                </button>
            </div>
            <p v-if="item.ipa" class="text-ink-slate text-base">
                {{ item.ipa }}
            </p>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <AudioButton
                :src="item.audio.normal"
                variant="normal"
                :text="item.text"
                class="flex-1"
            />
            <AudioButton
                :src="item.audio.slow"
                variant="slow"
                :text="item.text"
                class="flex-1"
            />
            <PhrasebookButton
                :saved="item.saved"
                :lexicon-item-id="item.id"
                :source-lesson-id="lessonId"
                class="flex-1"
            />
        </div>

        <template v-if="item.showMeaning && item.meaning">
            <ShowMeaningPanel
                :id="panelId"
                :shown="meaning.shown.value"
                :arabic="item.meaning.arabic"
                :explanation="item.meaning.explanation"
                class="mt-3"
            />
        </template>
    </div>
</template>
