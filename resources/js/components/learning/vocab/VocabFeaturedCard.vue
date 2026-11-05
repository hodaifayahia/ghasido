<script setup lang="ts">
import { useId } from 'vue';
import AudioButton from '@/components/learning/AudioButton.vue';
import PhrasebookButton from '@/components/learning/PhrasebookButton.vue';
import ShowMeaningButton from '@/components/learning/ShowMeaningButton.vue';
import StoredMeaningPanel from '@/components/learning/meaning/StoredMeaningPanel.vue';
import { useShowMeaning } from '@/composables/useShowMeaning';
import type { LexiconEntry } from '@/types';

/*
 * The featured word card of the Vocabulary step (photo_2, x 32 → 848):
 * a 412×292 rounded-xl image, the word 34px bold ink-royal with an inline
 * speaker, the IPA, the Normal | Slow pair, Save to Phrasebook and Show
 * Meaning (CTRL-01..03, PHRASE-02). Keyed by item id in the parent so the
 * Show Meaning panel resets when a related word swaps the feature in. The
 * meaning is the stored translation of the word in the learner's helper
 * language, fetched on the tap (client request 2026-11-05).
 */
type Props = {
    item: LexiconEntry;
    lessonId: number;
};

const props = defineProps<Props>();

const meaning = useShowMeaning();
const panelId = `vocab-meaning-${useId()}`;
</script>

<template>
    <div class="border-line bg-surface shadow-card rounded-lg border p-5">
        <div class="flex flex-col gap-5 md:flex-row md:gap-6">
            <img
                v-if="item.image"
                :src="item.image.url"
                :alt="item.image.alt ?? item.text"
                loading="lazy"
                decoding="async"
                class="h-56 w-full rounded-xl object-cover md:h-[292px] md:w-[412px] md:shrink-0"
            />

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="flex items-start gap-3">
                    <h3
                        class="font-heading text-ink-royal min-w-0 text-3xl leading-tight font-bold md:text-[34px]"
                    >
                        {{ item.text }}
                    </h3>
                    <AudioButton
                        size="sm"
                        :src="item.audio.normal"
                        :text="item.text"
                        class="mt-1 shrink-0"
                    />
                </div>

                <p v-if="item.ipa" class="text-ink-slate mt-1 text-lg">
                    {{ item.ipa }}
                </p>

                <div class="mt-4 flex gap-3">
                    <AudioButton
                        :src="item.audio.normal"
                        variant="normal"
                        :text="item.text"
                        class="min-w-0 flex-1"
                    />
                    <AudioButton
                        :src="item.audio.slow"
                        variant="slow"
                        :text="item.text"
                        class="min-w-0 flex-1"
                    />
                </div>

                <PhrasebookButton
                    :saved="item.saved"
                    :lexicon-item-id="item.id"
                    :source-lesson-id="lessonId"
                    class="mt-3 w-full"
                />

                <template v-if="item.showMeaning">
                    <ShowMeaningButton
                        :shown="meaning.shown.value"
                        :controls="panelId"
                        class="mt-3 w-full"
                        @toggle="meaning.toggle()"
                    />
                    <StoredMeaningPanel
                        :id="panelId"
                        :text="item.text"
                        :shown="meaning.shown.value"
                        :explanation="item.meaning?.explanation ?? null"
                        class="mt-3"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
